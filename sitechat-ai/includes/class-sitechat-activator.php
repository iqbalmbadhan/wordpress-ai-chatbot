<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Activator {

	public static function activate(): void {
		self::create_tables();
		self::set_default_options();
		self::schedule_cron();
		flush_rewrite_rules();
		update_option( 'sitechat_db_version', SITECHAT_DB_VERSION );
	}

	private static function create_tables(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$documents_table = $wpdb->prefix . 'sitechat_documents';
		$chunks_table    = $wpdb->prefix . 'sitechat_chunks';
		$logs_table      = $wpdb->prefix . 'sitechat_chat_logs';

		dbDelta( "CREATE TABLE {$documents_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED DEFAULT NULL,
			url VARCHAR(2048) NOT NULL,
			title VARCHAR(500) NOT NULL,
			content_type VARCHAR(50) NOT NULL DEFAULT 'page',
			content_hash VARCHAR(32) DEFAULT NULL,
			word_count INT UNSIGNED DEFAULT 0,
			chunk_count INT UNSIGNED DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'indexed',
			error_message TEXT DEFAULT NULL,
			indexed_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_post (post_id),
			KEY idx_status (status),
			KEY idx_content_type (content_type)
		) {$charset_collate};" );

		dbDelta( "CREATE TABLE {$chunks_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			document_id BIGINT UNSIGNED NOT NULL,
			chunk_index INT UNSIGNED NOT NULL,
			content TEXT NOT NULL,
			heading VARCHAR(500) DEFAULT NULL,
			token_count INT UNSIGNED DEFAULT 0,
			embedding LONGBLOB NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_document (document_id)
		) {$charset_collate};" );

		dbDelta( "CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			user_message TEXT NOT NULL,
			bot_response TEXT NOT NULL,
			sources_json TEXT DEFAULT NULL,
			similarity_avg FLOAT DEFAULT NULL,
			response_time_ms INT UNSIGNED DEFAULT NULL,
			feedback VARCHAR(20) DEFAULT NULL,
			ip_hash VARCHAR(64) DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_session (session_id),
			KEY idx_created (created_at),
			KEY idx_feedback (feedback)
		) {$charset_collate};" );
	}

	private static function set_default_options(): void {
		$defaults = [
			'sitechat_gemini_api_key'      => '',
			'sitechat_display_mode'        => 'bubble',
			'sitechat_enabled'             => '0',
			'sitechat_auto_index'          => '1',
			'sitechat_index_post_types'    => [ 'post', 'page' ],
			'sitechat_excluded_ids'        => [],
			'sitechat_max_chunks_per_doc'  => 20,
			'sitechat_chunk_size'          => 1500,
			'sitechat_chunk_overlap'       => 200,
			'sitechat_chat_max_history'    => 5,
			'sitechat_rate_limit'          => 20,
			'sitechat_total_indexed'       => 0,
			'sitechat_total_chunks'        => 0,
			'sitechat_last_full_index'     => '',
			'sitechat_queries_today'       => 0,
			'sitechat_widget_title'        => 'Ask AI Assistant',
			'sitechat_welcome_message'     => 'Hi! I can help you find information on this website. What would you like to know?',
			'sitechat_placeholder'         => 'Type your question...',
			'sitechat_primary_color'       => '#2563eb',
			'sitechat_secondary_color'     => '#1e40af',
			'sitechat_bubble_position'     => 'bottom-right',
			'sitechat_bubble_size'         => '60',
			'sitechat_widget_width'        => '420',
			'sitechat_widget_height'       => '600',
			'sitechat_show_sources'        => '1',
			'sitechat_show_powered_by'     => '1',
			'sitechat_bot_name'            => 'AI Assistant',
			'sitechat_bot_avatar'          => '',
			'sitechat_custom_css'          => '',
			'sitechat_full_page_slug'      => 'chat',
			'sitechat_full_page_title'     => 'Chat with AI',
			'sitechat_full_page_layout'    => 'centered',
			'sitechat_slidein_side'        => 'right',
			'sitechat_slidein_width'       => '400',
			'sitechat_system_prompt'       => 'You are a helpful AI assistant for this website. Answer questions based ONLY on the provided context. Always cite sources with links. If you cannot find the answer in the context, say so honestly and suggest the visitor explore the website or contact support. Keep answers concise (2-4 paragraphs max).',
			'sitechat_show_on'             => 'all',
			'sitechat_page_list'           => [],
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'sitechat_auto_reindex' ) ) {
			wp_schedule_event( time(), 'daily', 'sitechat_auto_reindex' );
		}
	}
}
