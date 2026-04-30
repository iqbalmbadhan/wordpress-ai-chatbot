<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Chat {

	private const CHAT_MODEL   = 'gemini-2.0-flash';
	private const STREAM_MODEL = 'gemini-2.0-flash';
	private const API_BASE     = 'https://generativelanguage.googleapis.com/v1beta/models/';

	private SiteChat_Search     $search;
	private SiteChat_Embeddings $embeddings;

	public function __construct( SiteChat_Search $search, SiteChat_Embeddings $embeddings ) {
		$this->search     = $search;
		$this->embeddings = $embeddings;
	}

	// ── High-level orchestrator ───────────────────────────────────────────────

	/**
	 * Full RAG pipeline: embed query → retrieve chunks → generate answer.
	 *
	 * @param  string  $question
	 * @param  array   $history  [{'role':'user'|'assistant', 'content':string}, ...]
	 * @return array{answer:string, sources:array, similarity_avg:float, response_time_ms:int}|WP_Error
	 */
	public function answer( string $question, array $history = [] ): array|WP_Error {
		$api_key = get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$start = microtime( true );

		// 1. Embed query + retrieve relevant chunks
		$query_embedding = $this->embeddings->embed_text( $question );
		if ( is_wp_error( $query_embedding ) ) {
			return $query_embedding;
		}

		$chunks = $this->search->search_by_embedding( $query_embedding, 5 );

		if ( empty( $chunks ) ) {
			return [
				'answer'          => __( "I couldn't find specific information about that on this website. Please try rephrasing your question or browse the site directly.", 'sitechat-ai' ),
				'sources'         => [],
				'similarity_avg'  => 0.0,
				'response_time_ms'=> (int) round( ( microtime( true ) - $start ) * 1000 ),
			];
		}

		// 2. Generate answer from retrieved chunks
		$result = $this->generate_answer( $question, $chunks, $history );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['response_time_ms'] = (int) round( ( microtime( true ) - $start ) * 1000 );
		return $result;
	}

	// ── Generation ────────────────────────────────────────────────────────────

	/**
	 * Generate an answer from pre-retrieved context chunks.
	 *
	 * @param  array $chunks  From SiteChat_Search::search()
	 * @param  array $history [{'role':string, 'content':string}, ...]
	 * @return array{answer:string, sources:array, similarity_avg:float}|WP_Error
	 */
	public function generate_answer( string $query, array $chunks, array $history = [] ): array|WP_Error {
		$api_key = get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		[ $context, $sources, $similarity_avg ] = $this->build_context( $chunks );

		$system_prompt = $this->build_system_prompt();
		$max_history   = (int) get_option( 'sitechat_chat_max_history', 5 );
		$history       = array_slice( $history, -( $max_history * 2 ) );

		$contents = $this->build_contents( $system_prompt, $context, $sources, $query, $history );

		$url  = self::API_BASE . self::CHAT_MODEL . ':generateContent?key=' . rawurlencode( $api_key );
		$body = wp_json_encode( [
			'contents'         => $contents,
			'generationConfig' => [
				'temperature'     => 0.3,
				'maxOutputTokens' => 1024,
				'topP'            => 0.8,
			],
		] );

		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 45,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			$msg = $data['error']['message'] ?? 'Gemini chat API error';
			return new WP_Error( 'chat_failed', $msg, [ 'status' => $code ] );
		}

		return [
			'answer'         => $data['candidates'][0]['content']['parts'][0]['text'],
			'sources'        => $sources,
			'similarity_avg' => $similarity_avg,
		];
	}

	/**
	 * SSE streaming answer. Outputs Server-Sent Events directly and exits.
	 * The caller (REST endpoint) must set headers before calling this.
	 *
	 * Each SSE event:
	 *   data: {"token":"partial text"}\n\n      — incremental token
	 *   data: {"done":true,"sources":[...]}\n\n  — final event
	 *   data: {"error":"message"}\n\n            — on failure
	 */
	public function stream_answer( string $question, array $history = [] ): void {
		$api_key = get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $api_key ) {
			$this->sse_event( [ 'error' => __( 'Gemini API key is not configured.', 'sitechat-ai' ) ] );
			return;
		}

		// Embed + search
		$query_embedding = $this->embeddings->embed_text( $question );
		if ( is_wp_error( $query_embedding ) ) {
			$this->sse_event( [ 'error' => $query_embedding->get_error_message() ] );
			return;
		}

		$chunks = $this->search->search_by_embedding( $query_embedding, 5 );

		if ( empty( $chunks ) ) {
			$no_result = __( "I couldn't find specific information about that on this website. Please try rephrasing your question or browse the site directly.", 'sitechat-ai' );
			$this->sse_event( [ 'token' => $no_result ] );
			$this->sse_event( [ 'done' => true, 'sources' => [] ] );
			return;
		}

		[ $context, $sources ] = $this->build_context( $chunks );

		$system_prompt = $this->build_system_prompt();
		$max_history   = (int) get_option( 'sitechat_chat_max_history', 5 );
		$history       = array_slice( $history, -( $max_history * 2 ) );
		$contents      = $this->build_contents( $system_prompt, $context, $sources, $question, $history );

		$url  = self::API_BASE . self::STREAM_MODEL
			. ':streamGenerateContent?alt=sse&key=' . rawurlencode( $api_key );
		$body = wp_json_encode( [
			'contents'         => $contents,
			'generationConfig' => [
				'temperature'     => 0.3,
				'maxOutputTokens' => 1024,
				'topP'            => 0.8,
			],
		] );

		// Open a streaming HTTP connection
		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 60,
			'stream'  => true,
		] );

		if ( is_wp_error( $response ) ) {
			$this->sse_event( [ 'error' => $response->get_error_message() ] );
			return;
		}

		$body_str = wp_remote_retrieve_body( $response );
		$lines    = explode( "\n", $body_str );

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! str_starts_with( $line, 'data: ' ) ) {
				continue;
			}
			$json = substr( $line, 6 );
			if ( $json === '[DONE]' ) {
				break;
			}
			$event = json_decode( $json, true );
			$token = $event['candidates'][0]['content']['parts'][0]['text'] ?? null;
			if ( $token !== null ) {
				$this->sse_event( [ 'token' => $token ] );
			}
		}

		$this->sse_event( [ 'done' => true, 'sources' => $sources ] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Build the context string + deduplicated sources list from retrieved chunks.
	 *
	 * @return array{0:string, 1:array, 2:float}  [context, sources, similarity_avg]
	 */
	private function build_context( array $chunks ): array {
		$context_parts  = [];
		$sources        = [];
		$seen_urls      = [];

		foreach ( $chunks as $chunk ) {
			$context_parts[] = sprintf(
				"--- Source: %s (%s) ---\n%s",
				$chunk['title'],
				$chunk['url'],
				$chunk['content']
			);

			if ( ! in_array( $chunk['url'], $seen_urls, true ) ) {
				$sources[]   = [
					'title'      => $chunk['title'],
					'url'        => $chunk['url'],
					'similarity' => round( $chunk['similarity'], 3 ),
				];
				$seen_urls[] = $chunk['url'];
			}
		}

		// Sort sources by descending similarity
		usort( $sources, fn( $a, $b ) => $b['similarity'] <=> $a['similarity'] );

		$similarity_avg = count( $chunks )
			? round( array_sum( array_column( $chunks, 'similarity' ) ) / count( $chunks ), 3 )
			: 0.0;

		return [ implode( "\n\n", $context_parts ), $sources, $similarity_avg ];
	}

	/** Build the system prompt, substituting site-specific placeholders. */
	private function build_system_prompt(): string {
		$raw = (string) get_option( 'sitechat_system_prompt',
			'You are a helpful AI assistant for {site_name} ({site_url}). Answer questions based ONLY on the provided context. Always cite sources with links. If you cannot find the answer in the context, say so honestly and suggest the visitor explore the website or contact support. Keep answers concise (2-4 paragraphs max).'
		);

		return str_replace(
			[ '{site_name}', '{site_url}' ],
			[ get_bloginfo( 'name' ), home_url() ],
			$raw
		);
	}

	/**
	 * Build the Gemini `contents` array for a multi-turn conversation.
	 *
	 * The first turn injects the system prompt + context so Gemini has full
	 * grounding. Subsequent history turns follow as alternating user/model
	 * messages, and the current query is the final user turn.
	 */
	private function build_contents( string $system_prompt, string $context, array $sources, string $query, array $history ): array {
		$source_list = implode( "\n", array_map(
			fn( $s ) => "- [{$s['title']}]({$s['url']})",
			$sources
		) );

		$grounding_message = $system_prompt
			. "\n\n## Context from {" . get_bloginfo( 'name' ) . "}:\n\n" . $context
			. "\n\n## Available sources:\n" . $source_list;

		$contents = [];

		if ( empty( $history ) ) {
			// Single-turn: everything in one user message
			$contents[] = [
				'role'  => 'user',
				'parts' => [ [ 'text' => $grounding_message . "\n\nQuestion: " . $query ] ],
			];
		} else {
			// Multi-turn: grounding in first turn, history in middle, current query last
			$first_user    = array_shift( $history );
			$first_model   = array_shift( $history );

			$first_question = is_array( $first_user ) ? ( $first_user['content'] ?? $query ) : $query;

			$contents[] = [
				'role'  => 'user',
				'parts' => [ [ 'text' => $grounding_message . "\n\nQuestion: " . $first_question ] ],
			];

			if ( $first_model ) {
				$contents[] = [
					'role'  => 'model',
					'parts' => [ [ 'text' => is_array( $first_model ) ? ( $first_model['content'] ?? '' ) : '' ] ],
				];
			}

			foreach ( $history as $turn ) {
				if ( ! isset( $turn['role'], $turn['content'] ) ) {
					continue;
				}
				$contents[] = [
					'role'  => $turn['role'] === 'assistant' ? 'model' : 'user',
					'parts' => [ [ 'text' => $turn['content'] ] ],
				];
			}

			$contents[] = [
				'role'  => 'user',
				'parts' => [ [ 'text' => $query ] ],
			];
		}

		return $contents;
	}

	private function sse_event( array $data ): void {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}
}
