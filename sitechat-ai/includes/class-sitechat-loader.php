<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Loader {

	public function run(): void {
		// Core services
		$db         = new SiteChat_DB();
		$embeddings = new SiteChat_Embeddings();
		$indexer    = new SiteChat_Indexer( $db, $embeddings );
		$search     = new SiteChat_Search( $db );
		$chat       = new SiteChat_Chat( $search, $embeddings );
		$cron       = new SiteChat_Cron( $indexer );

		// Admin
		if ( is_admin() ) {
			$admin = new SiteChat_Admin( $db, $indexer );
			$admin->init();

			$admin_ajax = new SiteChat_Admin_Ajax( $db, $indexer, $chat );
			$admin_ajax->init();
		}

		// Frontend
		$rest_api = new SiteChat_REST_API( $chat, $db );
		$rest_api->init();

		$frontend = new SiteChat_Frontend();
		$frontend->init();

		$shortcode = new SiteChat_Shortcode();
		$shortcode->init();

		$block = new SiteChat_Block();
		$block->init();

		// Cron
		$cron->init();

		// Auto-index on post save
		if ( get_option( 'sitechat_auto_index' ) === '1' ) {
			add_action( 'save_post', [ $indexer, 'index_post_on_save' ], 10, 3 );
			add_action( 'delete_post', [ $indexer, 'remove_post_from_index' ] );
		}

		// Add rewrite rules for full-page chat
		add_action( 'init', [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_full_page_template' ] );
	}

	public function add_rewrite_rules(): void {
		$slug = get_option( 'sitechat_full_page_slug', 'chat' );
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?sitechat_page=1', 'top' );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'sitechat_page';
		return $vars;
	}

	public function handle_full_page_template(): void {
		if ( get_query_var( 'sitechat_page' ) && get_option( 'sitechat_display_mode' ) === 'full_page' ) {
			$template = SITECHAT_PLUGIN_DIR . 'public/templates/full-page-chat.php';
			if ( file_exists( $template ) ) {
				include $template;
				exit;
			}
		}
	}
}
