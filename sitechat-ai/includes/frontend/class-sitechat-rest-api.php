<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_REST_API {

	private SiteChat_Chat $chat;
	private SiteChat_DB   $db;

	public function __construct( SiteChat_Chat $chat, SiteChat_DB $db ) {
		$this->chat = $chat;
		$this->db   = $db;
	}

	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$common_args = [
			'message'    => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => fn( $v ) => strlen( trim( $v ) ) > 0,
			],
			'session_id' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'history' => [
				'required' => false,
				'type'     => 'array',
				'default'  => [],
			],
		];

		register_rest_route( 'sitechat/v1', '/chat', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_chat' ],
			'permission_callback' => '__return_true',
			'args'                => $common_args,
		] );

		register_rest_route( 'sitechat/v1', '/chat/stream', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_stream' ],
			'permission_callback' => '__return_true',
			'args'                => $common_args,
		] );

		register_rest_route( 'sitechat/v1', '/feedback', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_feedback' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'log_id'   => [
					'required'          => true,
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
				],
				'feedback' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => fn( $v ) => in_array( $v, [ 'helpful', 'not_helpful' ], true ),
				],
			],
		] );
	}

	// ── /chat ─────────────────────────────────────────────────────────────────

	public function handle_chat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->check_rate_limit( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' ) ?: wp_generate_uuid4();
		$history    = (array) $request->get_param( 'history' );

		$result = $this->chat->answer( $message, $history );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 500 ] );
		}

		$ms     = $result['response_time_ms'] ?? 0;
		$ip_hash = $guard; // returned from check_rate_limit on success

		$log_id = $this->db->insert_chat_log( [
			'session_id'       => $session_id,
			'user_message'     => $message,
			'bot_response'     => $result['answer'],
			'sources_json'     => wp_json_encode( $result['sources'] ),
			'similarity_avg'   => $result['similarity_avg'],
			'response_time_ms' => $ms,
			'ip_hash'          => $ip_hash,
		] );

		update_option( 'sitechat_queries_today', (int) get_option( 'sitechat_queries_today', 0 ) + 1 );

		return rest_ensure_response( [
			'answer'     => $result['answer'],
			'sources'    => $result['sources'],
			'session_id' => $session_id,
			'log_id'     => $log_id,
			'ms'         => $ms,
		] );
	}

	// ── /chat/stream ──────────────────────────────────────────────────────────

	/**
	 * Server-Sent Events streaming endpoint.
	 * Returns 200 immediately with text/event-stream headers, then streams tokens.
	 */
	public function handle_stream( WP_REST_Request $request ): void {
		$guard = $this->check_rate_limit( $request );
		if ( is_wp_error( $guard ) ) {
			status_header( 429 );
			echo 'data: ' . wp_json_encode( [ 'error' => $guard->get_error_message() ] ) . "\n\n";
			exit;
		}

		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' ) ?: wp_generate_uuid4();
		$history    = (array) $request->get_param( 'history' );

		// Send SSE headers before any output
		status_header( 200 );
		header( 'Content-Type: text/event-stream; charset=UTF-8' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' ); // Nginx passthrough
		header( 'Connection: keep-alive' );

		if ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		$this->chat->stream_answer( $message, $history );
		exit;
	}

	// ── /feedback ─────────────────────────────────────────────────────────────

	public function handle_feedback( WP_REST_Request $request ): WP_REST_Response {
		$log_id   = (int) $request->get_param( 'log_id' );
		$feedback = $request->get_param( 'feedback' );
		$this->db->update_feedback( $log_id, $feedback );
		return rest_ensure_response( [ 'ok' => true ] );
	}

	// ── Shared guard ──────────────────────────────────────────────────────────

	/**
	 * Check plugin enabled status and per-IP rate limit.
	 * Returns the hashed IP on success, WP_Error on failure.
	 */
	private function check_rate_limit( WP_REST_Request $request ): string|WP_Error {
		if ( get_option( 'sitechat_enabled' ) !== '1' ) {
			return new WP_Error( 'disabled', __( 'Chatbot is not enabled.', 'sitechat-ai' ), [ 'status' => 503 ] );
		}

		$raw_ip  = $request->get_header( 'x-forwarded-for' ) ?: ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$ip_hash = hash( 'sha256', $raw_ip );

		$rate_limit = (int) get_option( 'sitechat_rate_limit', 20 );
		$rate_key   = 'sitechat_rate_' . $ip_hash;
		$count      = (int) get_transient( $rate_key );

		if ( $count >= $rate_limit ) {
			return new WP_Error( 'rate_limit', __( 'Too many requests. Please wait a moment.', 'sitechat-ai' ), [ 'status' => 429 ] );
		}

		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		return $ip_hash;
	}
}
