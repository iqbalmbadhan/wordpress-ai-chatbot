<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin_Ajax {

	private SiteChat_DB      $db;
	private SiteChat_Indexer $indexer;
	private SiteChat_Chat    $chat;

	public function __construct( SiteChat_DB $db, SiteChat_Indexer $indexer, SiteChat_Chat $chat ) {
		$this->db      = $db;
		$this->indexer = $indexer;
		$this->chat    = $chat;
	}

	public function init(): void {
		add_action( 'wp_ajax_sitechat_index_all', [ $this, 'handle_index_all' ] );
		add_action( 'wp_ajax_sitechat_index_post', [ $this, 'handle_index_post' ] );
		add_action( 'wp_ajax_sitechat_remove_document', [ $this, 'handle_remove_document' ] );
		add_action( 'wp_ajax_sitechat_clear_index', [ $this, 'handle_clear_index' ] );
		add_action( 'wp_ajax_sitechat_get_stats', [ $this, 'handle_get_stats' ] );
		add_action( 'wp_ajax_sitechat_test_api', [ $this, 'handle_test_api' ] );
		add_action( 'wp_ajax_sitechat_save_settings', [ $this, 'handle_save_settings' ] );
	}

	private function verify_nonce(): void {
		if ( ! check_ajax_referer( 'sitechat_admin_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'sitechat-ai' ) ], 403 );
		}
	}

	public function handle_index_all(): void {
		$this->verify_nonce();
		$result = $this->indexer->index_all();
		wp_send_json_success( $result );
	}

	public function handle_index_post(): void {
		$this->verify_nonce();
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'sitechat-ai' ) ] );
		}
		$result = $this->indexer->index_post( $post_id );
		wp_send_json_success( [ 'result' => $result ] );
	}

	public function handle_remove_document(): void {
		$this->verify_nonce();
		$doc_id = (int) ( $_POST['doc_id'] ?? 0 );
		if ( ! $doc_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid document ID.', 'sitechat-ai' ) ] );
		}
		$this->db->delete_document( $doc_id );
		wp_send_json_success();
	}

	public function handle_clear_index(): void {
		$this->verify_nonce();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->db->chunks}" );
		$wpdb->query( "TRUNCATE TABLE {$this->db->documents}" );
		update_option( 'sitechat_total_indexed', 0 );
		update_option( 'sitechat_total_chunks', 0 );
		wp_send_json_success();
	}

	public function handle_get_stats(): void {
		$this->verify_nonce();
		wp_send_json_success( [
			'total_indexed' => $this->db->count_documents( 'indexed' ),
			'total_chunks'  => $this->db->count_chunks(),
			'total_logs'    => $this->db->count_logs(),
		] );
	}

	public function handle_test_api(): void {
		$this->verify_nonce();
		$api_key = sanitize_text_field( $_POST['api_key'] ?? get_option( 'sitechat_gemini_api_key', '' ) );
		if ( ! $api_key ) {
			wp_send_json_error( [ 'message' => __( 'No API key provided.', 'sitechat-ai' ) ] );
		}

		$url      = 'https://generativelanguage.googleapis.com/v1beta/models/text-embedding-004:embedContent?key=' . rawurlencode( $api_key );
		$body     = wp_json_encode( [
			'model'   => 'models/text-embedding-004',
			'content' => [ 'parts' => [ [ 'text' => 'Test connection' ] ] ],
		] );
		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => $response->get_error_message() ] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 200 && ! empty( $data['embedding']['values'] ) ) {
			wp_send_json_success( [ 'message' => __( 'API key is valid. Connection successful!', 'sitechat-ai' ) ] );
		} else {
			$msg = $data['error']['message'] ?? 'Unknown error';
			wp_send_json_error( [ 'message' => $msg ] );
		}
	}

	public function handle_save_settings(): void {
		$this->verify_nonce();
		$tab = sanitize_key( $_POST['sitechat_tab'] ?? 'settings' );

		$handler = match ( $tab ) {
			'appearance' => new SiteChat_Admin_Appearance(),
			default      => new SiteChat_Admin_Settings(),
		};

		if ( method_exists( $handler, 'save' ) ) {
			$handler->save();
		}

		wp_send_json_success( [ 'message' => __( 'Settings saved.', 'sitechat-ai' ) ] );
	}
}
