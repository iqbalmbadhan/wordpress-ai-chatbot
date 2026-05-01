<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_REST_API {

	private SiteChat_Chat $chat;
	private SiteChat_DB   $db;

	private const NAMESPACE = 'sitechat/v1';

	public function __construct( SiteChat_Chat $chat, SiteChat_DB $db ) {
		$this->chat = $chat;
		$this->db   = $db;
	}

	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	// ── Route registration ────────────────────────────────────────────────────

	public function register_routes(): void {
		$chat_args = $this->chat_args();

		register_rest_route( self::NAMESPACE, '/chat', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_chat' ],
			'permission_callback' => '__return_true',
			'args'                => $chat_args,
		] );

		register_rest_route( self::NAMESPACE, '/chat/stream', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_stream' ],
			'permission_callback' => '__return_true',
			'args'                => $chat_args,
		] );

		register_rest_route( self::NAMESPACE, '/feedback', [
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

		register_rest_route( self::NAMESPACE, '/config', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_config' ],
			'permission_callback' => '__return_true',
		] );
	}

	// ── POST /chat ────────────────────────────────────────────────────────────

	public function handle_chat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$guard = $this->check_rate_limit( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$message    = $request->get_param( 'message' );
		$session_id = $this->sanitize_session( $request->get_param( 'session_id' ) );
		$history    = $this->sanitize_history( (array) $request->get_param( 'history' ) );

		$result = $this->chat->answer( $message, $history );

		if ( is_wp_error( $result ) ) {
			return $this->api_error( $result->get_error_code(), $result->get_error_message(), 500 );
		}

		$ms      = $result['response_time_ms'] ?? 0;
		$ip_hash = $guard;

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
			'success'    => true,
			'answer'     => $result['answer'],
			'sources'    => $result['sources'],
			'session_id' => $session_id,
			'log_id'     => $log_id,
			'ms'         => $ms,
		] );
	}

	// ── POST /chat/stream ─────────────────────────────────────────────────────

	/**
	 * SSE streaming endpoint. Sets event-stream headers and delegates to
	 * SiteChat_Chat::stream_answer(), then exits.
	 */
	public function handle_stream( WP_REST_Request $request ): void {
		$guard = $this->check_rate_limit( $request );
		if ( is_wp_error( $guard ) ) {
			status_header( (int) ( $guard->get_error_data()['status'] ?? 429 ) );
			header( 'Content-Type: text/event-stream; charset=UTF-8' );
			echo 'data: ' . wp_json_encode( [ 'error' => $guard->get_error_message() ] ) . "\n\n";
			exit;
		}

		$message    = $request->get_param( 'message' );
		$session_id = $this->sanitize_session( $request->get_param( 'session_id' ) );
		$history    = $this->sanitize_history( (array) $request->get_param( 'history' ) );

		// SSE headers must be sent before any output
		status_header( 200 );
		header( 'Content-Type: text/event-stream; charset=UTF-8' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );  // Nginx: disable proxy buffering
		header( 'Connection: keep-alive' );

		// Kill any output buffers so tokens reach the browser immediately
		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		$this->chat->stream_answer( $message, $history );
		exit;
	}

	// ── POST /feedback ────────────────────────────────────────────────────────

	public function handle_feedback( WP_REST_Request $request ): WP_REST_Response {
		$log_id   = (int) $request->get_param( 'log_id' );
		$feedback = $request->get_param( 'feedback' );
		$updated  = $this->db->update_feedback( $log_id, $feedback );
		return rest_ensure_response( [ 'success' => true, 'updated' => $updated ] );
	}

	// ── GET /config ───────────────────────────────────────────────────────────

	/**
	 * Returns the public widget configuration so headless or external clients
	 * can bootstrap the chat widget without embedding WordPress template tags.
	 * Cached for 5 minutes via a transient.
	 */
	public function handle_config( WP_REST_Request $request ): WP_REST_Response {
		$cached = get_transient( 'sitechat_config_cache' );
		if ( is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$config = [
			'success'        => true,
			'enabled'        => get_option( 'sitechat_enabled' ) === '1',
			'displayMode'    => get_option( 'sitechat_display_mode', 'bubble' ),
			'title'          => get_option( 'sitechat_widget_title', 'Ask AI Assistant' ),
			'welcomeMessage' => get_option( 'sitechat_welcome_message', 'Hi! How can I help you?' ),
			'placeholder'    => get_option( 'sitechat_placeholder', 'Type your question...' ),
			'botName'        => get_option( 'sitechat_bot_name', 'AI Assistant' ),
			'botAvatar'      => get_option( 'sitechat_bot_avatar', '' ),
			'primaryColor'   => get_option( 'sitechat_primary_color', '#2563eb' ),
			'secondaryColor' => get_option( 'sitechat_secondary_color', '#1e40af' ),
			'showSources'    => get_option( 'sitechat_show_sources', '1' ) === '1',
			'showPoweredBy'  => get_option( 'sitechat_show_powered_by', '1' ) === '1',
			'bubblePosition' => get_option( 'sitechat_bubble_position', 'bottom-right' ),
			'bubbleSize'     => (int) get_option( 'sitechat_bubble_size', 60 ),
			'widgetWidth'    => (int) get_option( 'sitechat_widget_width', 420 ),
			'widgetHeight'   => (int) get_option( 'sitechat_widget_height', 600 ),
			'slideinSide'    => get_option( 'sitechat_slidein_side', 'right' ),
			'slideinWidth'   => (int) get_option( 'sitechat_slidein_width', 400 ),
			'endpoints'      => [
				'chat'     => rest_url( 'sitechat/v1/chat' ),
				'stream'   => rest_url( 'sitechat/v1/chat/stream' ),
				'feedback' => rest_url( 'sitechat/v1/feedback' ),
			],
		];

		$config = (array) apply_filters( 'sitechat_widget_config', $config );

		set_transient( 'sitechat_config_cache', $config, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $config );
	}

	// ── Shared helpers ────────────────────────────────────────────────────────

	/**
	 * Guard: verify plugin is enabled and enforce per-IP rate limit.
	 * Returns ip_hash string on success, WP_Error on failure.
	 */
	private function check_rate_limit( WP_REST_Request $request ): string|WP_Error {
		if ( get_option( 'sitechat_enabled' ) !== '1' ) {
			return $this->api_error( 'disabled', __( 'Chatbot is not enabled.', 'sitechat-ai' ), 503 );
		}

		// Use the leftmost X-Forwarded-For IP to handle proxies; fall back to REMOTE_ADDR
		$forwarded = $request->get_header( 'x-forwarded-for' );
		$raw_ip    = $forwarded ? trim( explode( ',', $forwarded )[0] ) : ( $_SERVER['REMOTE_ADDR'] ?? '' );
		$ip_hash   = hash( 'sha256', $raw_ip );

		$rate_limit = (int) get_option( 'sitechat_rate_limit', 20 );
		$rate_key   = 'sitechat_rate_' . $ip_hash;
		$count      = (int) get_transient( $rate_key );

		if ( $count >= $rate_limit ) {
			return $this->api_error( 'rate_limit', __( 'Too many requests. Please wait a moment.', 'sitechat-ai' ), 429 );
		}

		set_transient( $rate_key, $count + 1, MINUTE_IN_SECONDS );

		return $ip_hash;
	}

	/** Build a WP_Error with an HTTP status code embedded in its data array. */
	private function api_error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, [ 'status' => $status ] );
	}

	/**
	 * Shared arg schema for /chat and /chat/stream.
	 * Validates: message non-empty and ≤ 1000 chars; history ≤ 10 items with valid shape.
	 */
	private function chat_args(): array {
		return [
			'message' => [
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'validate_callback' => function ( $v ): bool|WP_Error {
					$v = trim( $v );
					if ( $v === '' ) {
						return new WP_Error( 'empty_message', __( 'Message cannot be empty.', 'sitechat-ai' ) );
					}
					if ( strlen( $v ) > 1000 ) {
						return new WP_Error( 'message_too_long', __( 'Message must be 1000 characters or fewer.', 'sitechat-ai' ) );
					}
					return true;
				},
			],
			'session_id' => [
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'history' => [
				'required'          => false,
				'type'              => 'array',
				'default'           => [],
				'validate_callback' => function ( $v ): bool|WP_Error {
					if ( ! is_array( $v ) ) {
						return new WP_Error( 'invalid_history', __( 'History must be an array.', 'sitechat-ai' ) );
					}
					if ( count( $v ) > 10 ) {
						return new WP_Error( 'history_too_long', __( 'History may contain at most 10 items.', 'sitechat-ai' ) );
					}
					foreach ( $v as $item ) {
						if ( ! isset( $item['role'], $item['content'] )
							|| ! in_array( $item['role'], [ 'user', 'assistant' ], true ) ) {
							return new WP_Error( 'invalid_history_item', __( 'Each history item must have a valid role and content.', 'sitechat-ai' ) );
						}
					}
					return true;
				},
			],
		];
	}

	/** Produce a valid session UUID, generating a fresh one when absent. */
	private function sanitize_session( mixed $raw ): string {
		$val = sanitize_text_field( (string) $raw );
		return $val !== '' ? $val : wp_generate_uuid4();
	}

	/**
	 * Clamp history to 10 items and strip any fields that aren't role/content.
	 *
	 * @return array{role:string, content:string}[]
	 */
	private function sanitize_history( array $raw ): array {
		$clean = [];
		foreach ( array_slice( $raw, -10 ) as $item ) {
			if ( ! isset( $item['role'], $item['content'] ) ) {
				continue;
			}
			$role = sanitize_text_field( $item['role'] );
			if ( ! in_array( $role, [ 'user', 'assistant' ], true ) ) {
				continue;
			}
			$clean[] = [
				'role'    => $role,
				'content' => sanitize_textarea_field( $item['content'] ),
			];
		}
		return $clean;
	}
}
