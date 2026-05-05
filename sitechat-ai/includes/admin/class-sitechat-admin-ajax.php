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
		$actions = [
			'sitechat_validate_api_key',
			'sitechat_validate_chat_provider',
			'sitechat_get_post_ids',
			'sitechat_index_single',
			'sitechat_exclude_post',
			'sitechat_include_post',
			'sitechat_delete_index',
			'sitechat_get_chunks',
			'sitechat_test_chat',
			'sitechat_export_logs',
			'sitechat_clear_old_logs',
			'sitechat_delete_all_data',
			'sitechat_reset_settings',
			'sitechat_save_appearance',
			'sitechat_get_daily_stats',
			// Backward-compat aliases
			'sitechat_index_all',
			'sitechat_index_post',
			'sitechat_remove_document',
			'sitechat_clear_index',
			'sitechat_get_stats',
			'sitechat_test_api',
			'sitechat_save_settings',
		];
		foreach ( $actions as $action ) {
			$method = 'handle_' . str_replace( 'sitechat_', '', $action );
			if ( method_exists( $this, $method ) ) {
				add_action( 'wp_ajax_' . $action, [ $this, $method ] );
			}
		}
	}

	// ── Auth guard ────────────────────────────────────────────────────────────

	private function verify_nonce(): void {
		if ( ! check_ajax_referer( 'sitechat_admin_nonce', 'nonce', false ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'sitechat-ai' ) ], 403 );
		}
	}

	// ── API key validation ────────────────────────────────────────────────────

	public function handle_validate_api_key(): void {
		$this->verify_nonce();

		// Mirror SiteChat_Embeddings::provider() logic
		$chat_provider = sanitize_key( $_POST['chat_provider'] ?? get_option( 'sitechat_chat_provider', 'gemini' ) );
		if ( in_array( $chat_provider, [ 'gemini', 'openai' ], true ) ) {
			$embed_provider = $chat_provider;
		} else {
			$stored = (string) get_option( 'sitechat_index_provider', '' );
			$embed_provider = $stored ?: ( get_option( 'sitechat_openai_api_key', '' ) ? 'openai' : 'gemini' );
		}
		$api_key        = sanitize_text_field( $_POST['api_key'] ?? '' );

		if ( ! $api_key ) {
			wp_send_json_error( [ 'message' => __( 'No API key provided.', 'sitechat-ai' ) ] );
		}

		if ( $embed_provider === 'openai' ) {
			// Validate OpenAI embedding key
			$resp = wp_remote_post( 'https://api.openai.com/v1/embeddings', [
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				],
				'body'    => wp_json_encode( [
					'model'      => 'text-embedding-3-small',
					'input'      => 'test',
					'dimensions' => 768,
				] ),
				'timeout' => 15,
			] );

			if ( is_wp_error( $resp ) ) {
				wp_send_json_error( [ 'message' => $resp->get_error_message() ] );
			}

			$code = wp_remote_retrieve_response_code( $resp );
			$data = json_decode( wp_remote_retrieve_body( $resp ), true );

			if ( $code === 200 && ! empty( $data['data'][0]['embedding'] ) ) {
				wp_send_json_success( [ 'message' => __( 'OpenAI API key is valid. Embedding connection successful!', 'sitechat-ai' ) ] );
			}

			$msg = $data['error']['message'] ?? __( 'OpenAI API error.', 'sitechat-ai' );
			wp_send_json_error( [ 'message' => $msg ] );
		}

		// Gemini embedding validation — try v1 then v1beta
		$body     = wp_json_encode( [
			'model'   => 'models/text-embedding-004',
			'content' => [ 'parts' => [ [ 'text' => 'Test' ] ] ],
		] );
		$versions   = [ 'v1', 'v1beta' ];
		$cached_ver = (string) get_option( 'sitechat_embed_api_version', '' );
		if ( $cached_ver && in_array( $cached_ver, $versions, true ) ) {
			$versions = array_merge( [ $cached_ver ], array_diff( $versions, [ $cached_ver ] ) );
		}

		$last_error = __( 'Unknown error', 'sitechat-ai' );
		foreach ( $versions as $version ) {
			$url  = 'https://generativelanguage.googleapis.com/' . $version
				. '/models/text-embedding-004:embedContent?key=' . rawurlencode( $api_key );
			$resp = wp_remote_post( $url, [
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => $body,
				'timeout' => 15,
			] );

			if ( is_wp_error( $resp ) ) {
				$last_error = $resp->get_error_message();
				continue;
			}

			$code = wp_remote_retrieve_response_code( $resp );
			$data = json_decode( wp_remote_retrieve_body( $resp ), true );

			if ( $code === 200 && ! empty( $data['embedding']['values'] ) ) {
				update_option( 'sitechat_embed_api_version', $version );
				wp_send_json_success( [ 'message' => __( 'Gemini API key is valid. Connection successful!', 'sitechat-ai' ) ] );
			}

			$last_error = $data['error']['message'] ?? __( 'Unknown error', 'sitechat-ai' );
		}

		wp_send_json_error( [ 'message' => $last_error ] );
	}

	// ── Chat provider validation ──────────────────────────────────────────────

	public function handle_validate_chat_provider(): void {
		$this->verify_nonce();

		$provider_id = sanitize_key( $_POST['provider'] ?? get_option( 'sitechat_chat_provider', 'gemini' ) );
		$provider    = SiteChat_AI_Provider::get_provider( $provider_id );

		if ( ! $provider ) {
			wp_send_json_error( [ 'message' => __( 'Unknown provider.', 'sitechat-ai' ) ] );
		}

		if ( $provider_id === 'ollama' ) {
			$base_url = sanitize_text_field( $_POST['base_url'] ?? get_option( 'sitechat_ollama_base_url', 'http://localhost:11434' ) );
			$result   = SiteChat_AI_Provider::validate( $provider_id, '', $base_url );
		} else {
			$api_key = sanitize_text_field( $_POST['api_key'] ?? '' );
			if ( ! $api_key && $provider['key_option'] ) {
				$api_key = (string) get_option( $provider['key_option'], '' );
			}
			if ( ! $api_key && $provider['needs_key'] ) {
				wp_send_json_error( [ 'message' => __( 'No API key provided.', 'sitechat-ai' ) ] );
			}
			$result = SiteChat_AI_Provider::validate( $provider_id, $api_key );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'message' => sprintf(
				/* translators: %s provider label */
				__( '✓ %s connection successful!', 'sitechat-ai' ),
				$provider['label']
			),
		] );
	}

	// ── Bulk indexing helpers ─────────────────────────────────────────────────

	public function handle_get_post_ids(): void {
		$this->verify_nonce();
		$post_types  = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		$excluded    = (array) get_option( 'sitechat_excluded_ids', [] );

		$ids = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__not_in'   => $excluded,
		] );

		wp_send_json_success( [ 'ids' => array_map( 'intval', $ids ), 'total' => count( $ids ) ] );
	}

	public function handle_index_single(): void {
		$this->verify_nonce();
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid post ID.', 'sitechat-ai' ) ] );
		}
		$result = $this->indexer->index_post( $post_id );
		wp_send_json_success( $result );
	}

	// ── Exclude / include ─────────────────────────────────────────────────────

	public function handle_exclude_post(): void {
		$this->verify_nonce();
		$post_id  = (int) ( $_POST['post_id'] ?? 0 );
		$excluded = (array) get_option( 'sitechat_excluded_ids', [] );
		if ( ! in_array( $post_id, $excluded, true ) ) {
			$excluded[] = $post_id;
			update_option( 'sitechat_excluded_ids', $excluded );
		}
		$this->indexer->delete_post_index( $post_id );
		wp_send_json_success( [ 'message' => __( 'Post excluded and removed from index.', 'sitechat-ai' ) ] );
	}

	public function handle_include_post(): void {
		$this->verify_nonce();
		$post_id  = (int) ( $_POST['post_id'] ?? 0 );
		$excluded = array_values( array_filter(
			(array) get_option( 'sitechat_excluded_ids', [] ),
			fn( $id ) => (int) $id !== $post_id
		) );
		update_option( 'sitechat_excluded_ids', $excluded );
		wp_send_json_success( [ 'message' => __( 'Post re-included. Re-index to add it back.', 'sitechat-ai' ) ] );
	}

	// ── Delete single document index ──────────────────────────────────────────

	public function handle_delete_index(): void {
		$this->verify_nonce();
		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$doc_id  = (int) ( $_POST['doc_id'] ?? 0 );
		if ( $post_id ) {
			$this->indexer->delete_post_index( $post_id );
		} elseif ( $doc_id ) {
			$this->db->delete_document( $doc_id );
		} else {
			wp_send_json_error( [ 'message' => __( 'Invalid ID.', 'sitechat-ai' ) ] );
		}
		wp_send_json_success();
	}

	// ── Chunk preview ─────────────────────────────────────────────────────────

	public function handle_get_chunks(): void {
		$this->verify_nonce();
		$doc_id = (int) ( $_POST['doc_id'] ?? 0 );
		if ( ! $doc_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid document ID.', 'sitechat-ai' ) ] );
		}

		global $wpdb;
		$table  = $this->db->chunks;
		$chunks = $wpdb->get_results( $wpdb->prepare(
			"SELECT chunk_index, content, LENGTH(embedding) as emb_bytes FROM {$table} WHERE document_id = %d ORDER BY chunk_index",
			$doc_id
		) );

		wp_send_json_success( [ 'chunks' => $chunks ] );
	}

	// ── Test chatbot ──────────────────────────────────────────────────────────

	public function handle_test_chat(): void {
		$this->verify_nonce();
		$message = sanitize_textarea_field( $_POST['message'] ?? '' );
		if ( ! $message ) {
			wp_send_json_error( [ 'message' => __( 'Empty message.', 'sitechat-ai' ) ] );
		}

		$result = $this->chat->answer( $message, [] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [
			'answer'  => $result['answer'],
			'sources' => $result['sources'],
			'ms'      => $result['response_time_ms'],
		] );
	}

	// ── Log management ────────────────────────────────────────────────────────

	public function handle_export_logs(): void {
		$this->verify_nonce();
		$logs = $this->db->get_logs( [ 'limit' => 5000 ] );

		$rows   = [ [ 'Date', 'Session ID', 'Question', 'Answer', 'Similarity', 'Response (ms)', 'Feedback' ] ];
		foreach ( $logs as $log ) {
			$rows[] = [
				$log->created_at,
				$log->session_id,
				$log->user_message,
				$log->bot_response,
				$log->similarity_avg,
				$log->response_time_ms,
				$log->feedback ?? '',
			];
		}

		$csv = '';
		foreach ( $rows as $row ) {
			$csv .= implode( ',', array_map( function ( $v ) {
				$v = str_replace( '"', '""', (string) $v );
				return '"' . $v . '"';
			}, $row ) ) . "\r\n";
		}

		wp_send_json_success( [ 'csv' => $csv, 'count' => count( $logs ) ] );
	}

	public function handle_clear_old_logs(): void {
		$this->verify_nonce();
		$days    = max( 1, (int) ( $_POST['days'] ?? 90 ) );
		$deleted = $this->db->delete_old_logs( $days );
		wp_send_json_success( [ 'deleted' => $deleted ] );
	}

	// ── Danger zone ───────────────────────────────────────────────────────────

	public function handle_delete_all_data(): void {
		$this->verify_nonce();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$this->db->chat_logs}" );
		$wpdb->query( "TRUNCATE TABLE {$this->db->chunks}" );
		$wpdb->query( "TRUNCATE TABLE {$this->db->documents}" );
		delete_transient( 'sitechat_embeddings_cache' );
		delete_transient( 'sitechat_config_cache' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'sitechat_rate_%'" );

		wp_send_json_success( [ 'message' => __( 'All data deleted.', 'sitechat-ai' ) ] );
	}

	public function handle_reset_settings(): void {
		$this->verify_nonce();

		$defaults = [
			'sitechat_enabled'            => '0',
			'sitechat_auto_index'         => '0',
			'sitechat_index_post_types'   => [ 'post', 'page' ],
			'sitechat_excluded_ids'       => [],
			'sitechat_chunk_size'         => 1500,
			'sitechat_chunk_overlap'      => 100,
			'sitechat_max_chunks_per_doc' => 20,
			'sitechat_chat_max_history'   => 5,
			'sitechat_rate_limit'         => 20,
			'sitechat_system_prompt'      => 'You are a helpful AI assistant for {site_name}. Answer questions based only on the provided context. If the context does not contain the answer, politely say so and suggest contacting support.',
			'sitechat_display_mode'       => 'bubble',
			'sitechat_widget_title'       => 'Ask AI Assistant',
			'sitechat_welcome_message'    => 'Hi! How can I help you today?',
			'sitechat_placeholder'        => 'Type your question…',
			'sitechat_primary_color'      => '#2563eb',
			'sitechat_secondary_color'    => '#1e40af',
			'sitechat_bubble_position'    => 'bottom-right',
			'sitechat_bubble_size'        => 60,
			'sitechat_widget_width'       => 420,
			'sitechat_widget_height'      => 600,
			'sitechat_bot_name'           => 'AI Assistant',
			'sitechat_bot_avatar'         => '',
			'sitechat_show_sources'       => '1',
			'sitechat_show_powered_by'    => '1',
			'sitechat_custom_css'         => '',
			'sitechat_full_page_slug'     => 'chat',
			'sitechat_full_page_title'    => 'Chat with AI',
			'sitechat_full_page_layout'   => 'centered',
			'sitechat_slidein_side'       => 'right',
			'sitechat_slidein_width'      => 400,
			'sitechat_show_on'            => 'all',
			'sitechat_page_list'          => [],
			// Indexing fallback provider (for non-native-embed chat providers)
			'sitechat_index_provider'     => '',
			// Chat provider
			'sitechat_chat_provider'      => 'gemini',
			'sitechat_chat_model'         => 'gemini-2.0-flash',
			'sitechat_ollama_base_url'    => 'http://localhost:11434',
		];

		foreach ( $defaults as $key => $value ) {
			update_option( $key, $value );
		}

		delete_transient( 'sitechat_config_cache' );
		wp_send_json_success( [ 'message' => __( 'Settings reset to defaults.', 'sitechat-ai' ) ] );
	}

	// ── Save via AJAX ─────────────────────────────────────────────────────────

	public function handle_save_appearance(): void {
		$this->verify_nonce();
		( new SiteChat_Admin_Appearance() )->save();
		wp_send_json_success( [ 'message' => __( 'Appearance settings saved.', 'sitechat-ai' ) ] );
	}

	// ── Analytics chart data ──────────────────────────────────────────────────

	public function handle_get_daily_stats(): void {
		$this->verify_nonce();
		$days   = max( 1, min( 365, (int) ( $_POST['days'] ?? 30 ) ) );
		$rows   = $this->db->get_daily_query_counts( $days );
		$data   = [];
		foreach ( $rows as $row ) {
			$data[ $row['date'] ] = (int) $row['count'];
		}
		wp_send_json_success( [ 'data' => $data ] );
	}

	// ── Backward-compat aliases ───────────────────────────────────────────────

	public function handle_index_all(): void {
		$this->verify_nonce();
		$result = $this->indexer->index_all();
		wp_send_json_success( $result );
	}

	public function handle_index_post(): void {
		$this->handle_index_single();
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
		delete_transient( 'sitechat_embeddings_cache' );
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
		$this->handle_validate_api_key();
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
