<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Chat {

	private const CHAT_MODEL = 'gemini-2.0-flash';
	private const API_BASE   = 'https://generativelanguage.googleapis.com/v1beta/models/';

	private SiteChat_Search     $search;
	private SiteChat_Embeddings $embeddings;

	public function __construct( SiteChat_Search $search, SiteChat_Embeddings $embeddings ) {
		$this->search     = $search;
		$this->embeddings = $embeddings;
	}

	/**
	 * Generate a chat response. Returns array{answer:string, sources:array, similarity_avg:float}.
	 */
	public function answer( string $question, array $history = [] ): array|WP_Error {
		$api_key = get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		// 1. Embed query
		$query_embedding = $this->embeddings->embed( $question );
		if ( is_wp_error( $query_embedding ) ) {
			return $query_embedding;
		}

		// 2. Retrieve relevant chunks
		$chunks = $this->search->search( $query_embedding, 5, 0.25 );
		if ( empty( $chunks ) ) {
			return [
				'answer'         => __( "I couldn't find specific information about that on this website. Please try rephrasing your question or browse the site directly.", 'sitechat-ai' ),
				'sources'        => [],
				'similarity_avg' => 0.0,
			];
		}

		// 3. Build context
		$context_parts = [];
		$sources        = [];
		$seen_urls      = [];

		foreach ( $chunks as $chunk ) {
			$heading = $chunk['heading'] ? "[{$chunk['heading']}] " : '';
			$context_parts[] = $heading . $chunk['content'];

			if ( ! in_array( $chunk['url'], $seen_urls, true ) ) {
				$sources[]    = [
					'title'      => $chunk['title'],
					'url'        => $chunk['url'],
					'similarity' => round( $chunk['similarity'], 3 ),
				];
				$seen_urls[] = $chunk['url'];
			}
		}

		$context        = implode( "\n\n---\n\n", $context_parts );
		$similarity_avg = count( $chunks ) > 0
			? array_sum( array_column( $chunks, 'similarity' ) ) / count( $chunks )
			: 0.0;

		// 4. Build Gemini chat payload
		$system_prompt = get_option( 'sitechat_system_prompt', '' );
		$max_history   = (int) get_option( 'sitechat_chat_max_history', 5 );

		$contents = [];

		// Inject system context as first user turn
		$contents[] = [
			'role'  => 'user',
			'parts' => [ [
				'text' => $system_prompt
					. "\n\n## Relevant context from this website:\n\n" . $context
					. "\n\n## Sources:\n" . implode( "\n", array_map(
						fn( $s ) => "- [{$s['title']}]({$s['url']})",
						$sources
					) ),
			] ],
		];
		$contents[] = [
			'role'  => 'model',
			'parts' => [ [ 'text' => 'Understood. I will answer based on the provided context and always include source links.' ] ],
		];

		// Add history
		$history = array_slice( $history, -$max_history * 2 );
		foreach ( $history as $turn ) {
			if ( isset( $turn['role'], $turn['content'] ) ) {
				$contents[] = [
					'role'  => $turn['role'] === 'assistant' ? 'model' : 'user',
					'parts' => [ [ 'text' => $turn['content'] ] ],
				];
			}
		}

		// Add current question
		$contents[] = [
			'role'  => 'user',
			'parts' => [ [ 'text' => $question ] ],
		];

		$url      = self::API_BASE . self::CHAT_MODEL . ':generateContent?key=' . rawurlencode( $api_key );
		$body     = wp_json_encode( [
			'contents'         => $contents,
			'generationConfig' => [
				'temperature'     => 0.3,
				'maxOutputTokens' => 1024,
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
			return new WP_Error( 'chat_failed', $msg );
		}

		$answer = $data['candidates'][0]['content']['parts'][0]['text'];

		return [
			'answer'         => $answer,
			'sources'        => $sources,
			'similarity_avg' => round( $similarity_avg, 3 ),
		];
	}
}
