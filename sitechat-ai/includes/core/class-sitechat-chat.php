<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Chat {

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
	 * @param  array   $history  [{'role':'user'|'assistant','content':string},…]
	 * @return array{answer:string,sources:array,similarity_avg:float,response_time_ms:int}|WP_Error
	 */
	public function answer( string $question, array $history = [] ): array|WP_Error {
		$embed_key = (string) get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $embed_key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$start = microtime( true );
		do_action( 'sitechat_before_chat', $question, $history );

		// 1. Embed query (always via Gemini)
		$query_embedding = $this->embeddings->embed_text( $question );
		if ( is_wp_error( $query_embedding ) ) {
			return $query_embedding;
		}

		// 2. Retrieve relevant chunks
		$chunks = $this->search->search_by_embedding( $query_embedding, 5 );
		if ( empty( $chunks ) ) {
			return [
				'answer'           => __( "I couldn't find specific information about that on this website. Please try rephrasing your question or browse the site directly.", 'sitechat-ai' ),
				'sources'          => [],
				'similarity_avg'   => 0.0,
				'response_time_ms' => (int) round( ( microtime( true ) - $start ) * 1000 ),
			];
		}

		// 3. Generate answer via configured provider
		$result = $this->generate_answer( $question, $chunks, $history );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['response_time_ms'] = (int) round( ( microtime( true ) - $start ) * 1000 );
		$result = apply_filters( 'sitechat_after_chat', $result, $question, $history );
		return $result;
	}

	// ── Generation ────────────────────────────────────────────────────────────

	/**
	 * Generate an answer from pre-retrieved context chunks using the configured provider.
	 *
	 * @param  array $chunks  From SiteChat_Search::search()
	 * @param  array $history [{'role':string,'content':string},…]
	 * @return array{answer:string,sources:array,similarity_avg:float}|WP_Error
	 */
	public function generate_answer( string $query, array $chunks, array $history = [] ): array|WP_Error {
		[ $provider_id, $model, $api_key, $base_url ] = $this->get_provider_config();

		[ $context, $sources, $similarity_avg ] = $this->build_context( $chunks );

		$system_prompt = $this->build_system_prompt_with_context( $context, $sources );
		$max_history   = (int) get_option( 'sitechat_chat_max_history', 5 );
		$messages      = $this->build_messages( $query, array_slice( $history, -( $max_history * 2 ) ) );

		$result = SiteChat_AI_Provider::chat( $provider_id, $model, $system_prompt, $messages, $api_key, $base_url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'answer'         => $result['text'],
			'sources'        => $sources,
			'similarity_avg' => $similarity_avg,
		];
	}

	/**
	 * SSE streaming answer. Outputs Server-Sent Events directly and exits.
	 * The caller (REST endpoint) must set headers before calling this.
	 */
	public function stream_answer( string $question, array $history = [] ): void {
		$embed_key = (string) get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $embed_key ) {
			$this->sse_event( [ 'error' => __( 'Gemini API key is not configured.', 'sitechat-ai' ) ] );
			return;
		}

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
		[ $provider_id, $model, $api_key, $base_url ] = $this->get_provider_config();

		$system_prompt = $this->build_system_prompt_with_context( $context, $sources );
		$max_history   = (int) get_option( 'sitechat_chat_max_history', 5 );
		$messages      = $this->build_messages( $question, array_slice( $history, -( $max_history * 2 ) ) );

		SiteChat_AI_Provider::stream_chat( $provider_id, $model, $system_prompt, $messages, $api_key, $base_url );

		$this->sse_event( [ 'done' => true, 'sources' => $sources ] );
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/** Resolve active provider, model, API key, and base URL from options. */
	private function get_provider_config(): array {
		$provider_id = (string) get_option( 'sitechat_chat_provider', 'gemini' );
		$model       = (string) get_option( 'sitechat_chat_model', 'gemini-2.0-flash' );
		$api_key     = SiteChat_AI_Provider::get_api_key( $provider_id );
		$base_url    = (string) get_option( 'sitechat_ollama_base_url', 'http://localhost:11434' );

		// Gemini provider shares the embedding key
		if ( $provider_id === 'gemini' && ! $api_key ) {
			$api_key = (string) get_option( 'sitechat_gemini_api_key', '' );
		}

		return [ $provider_id, $model, $api_key, $base_url ];
	}

	/**
	 * Build context string + deduplicated sources from retrieved chunks.
	 *
	 * @return array{0:string,1:array,2:float}  [context, sources, similarity_avg]
	 */
	private function build_context( array $chunks ): array {
		$context_parts = [];
		$sources       = [];
		$seen_urls     = [];

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

		usort( $sources, fn( $a, $b ) => $b['similarity'] <=> $a['similarity'] );

		$similarity_avg = count( $chunks )
			? round( array_sum( array_column( $chunks, 'similarity' ) ) / count( $chunks ), 3 )
			: 0.0;

		return [ implode( "\n\n", $context_parts ), $sources, $similarity_avg ];
	}

	/** Combine base system prompt with retrieved context and source list. */
	private function build_system_prompt_with_context( string $context, array $sources ): string {
		$base     = $this->build_system_prompt();
		$src_list = implode( "\n", array_map(
			fn( $s ) => "- [{$s['title']}]({$s['url']})",
			$sources
		) );

		return $base
			. "\n\n## Context from " . get_bloginfo( 'name' ) . ":\n\n" . $context
			. "\n\n## Available sources:\n" . $src_list;
	}

	/** Build the base system prompt, substituting site-specific placeholders. */
	private function build_system_prompt(): string {
		$raw = (string) get_option(
			'sitechat_system_prompt',
			'You are a helpful AI assistant for {site_name} ({site_url}). Answer questions based ONLY on the provided context. Always cite sources with links. If you cannot find the answer in the context, say so honestly and suggest the visitor explore the website or contact support. Keep answers concise (2-4 paragraphs max).'
		);

		$prompt = str_replace(
			[ '{site_name}', '{site_url}' ],
			[ get_bloginfo( 'name' ), home_url() ],
			$raw
		);

		return (string) apply_filters( 'sitechat_system_prompt', $prompt );
	}

	/** Build a normalized messages array from history + current query. */
	private function build_messages( string $query, array $history ): array {
		$messages = [];
		foreach ( $history as $turn ) {
			if ( ! isset( $turn['role'], $turn['content'] ) ) {
				continue;
			}
			$messages[] = [
				'role'    => $turn['role'] === 'assistant' ? 'assistant' : 'user',
				'content' => (string) $turn['content'],
			];
		}
		$messages[] = [ 'role' => 'user', 'content' => $query ];
		return $messages;
	}

	private function sse_event( array $data ): void {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}
}
