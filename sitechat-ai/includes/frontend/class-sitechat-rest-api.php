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
		register_rest_route( 'sitechat/v1', '/chat', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_chat' ],
			'permission_callback' => '__return_true',
			'args'                => [
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
				'history'    => [
					'required' => false,
					'type'     => 'array',
				],
			],
		] );

		register_rest_route( 'sitechat/v1', '/feedback', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_feedback' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'log_id'   => [
					'required' => true,
					'type'     => 'integer',
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

	public function handle_chat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( get_option( 'sitechat_enabled' ) !== '1' ) {
			return new WP_Error( 'disabled', __( 'Chatbot is not enabled.', 'sitechat-ai' ), [ 'status' => 503 ] );
		}

		// Rate limiting
		$ip_hash    = hash( 'sha256', $request->get_header( 'x-forwarded-for' ) ?: ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		$rate_key   = 'sitechat_rate_' . $ip_hash;
		$rate_limit = (int) get_option( 'sitechat_rate_limit', 20 );
		$count      = (int) get_transient( $rate_key );

		if ( $count >= $rate_limit ) {
			return new WP_Error( 'rate_limit', __( 'Too many requests. Please wait a moment.', 'sitechat-ai' ), [ 'status' => 429 ] );
		}

		set_transient( $rate_key, $count + 1, 60 );

		$message    = $request->get_param( 'message' );
		$session_id = $request->get_param( 'session_id' ) ?: wp_generate_uuid4();
		$history    = $request->get_param( 'history' ) ?: [];

		$start  = microtime( true );
		$result = $this->chat->answer( $message, $history );
		$ms     = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 500 ] );
		}

		// Log to DB
		$log_id = $this->db->insert_log( [
			'session_id'       => $session_id,
			'user_message'     => $message,
			'bot_response'     => $result['answer'],
			'sources_json'     => wp_json_encode( $result['sources'] ),
			'similarity_avg'   => $result['similarity_avg'],
			'response_time_ms' => $ms,
			'ip_hash'          => $ip_hash,
		] );

		// Increment today's query count
		update_option( 'sitechat_queries_today', (int) get_option( 'sitechat_queries_today', 0 ) + 1 );

		return rest_ensure_response( [
			'answer'     => $result['answer'],
			'sources'    => $result['sources'],
			'session_id' => $session_id,
			'log_id'     => $log_id,
			'ms'         => $ms,
		] );
	}

	public function handle_feedback( WP_REST_Request $request ): WP_REST_Response {
		$log_id   = (int) $request->get_param( 'log_id' );
		$feedback = $request->get_param( 'feedback' );
		$this->db->update_log_feedback( $log_id, $feedback );
		return rest_ensure_response( [ 'ok' => true ] );
	}
}
