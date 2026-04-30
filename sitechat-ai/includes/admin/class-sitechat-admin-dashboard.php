<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin_Dashboard {

	private SiteChat_DB $db;

	public function __construct( SiteChat_DB $db ) {
		$this->db = $db;
	}

	public function render(): void {
		$total_indexed = (int) get_option( 'sitechat_total_indexed', 0 );
		$total_chunks  = (int) get_option( 'sitechat_total_chunks', 0 );
		$last_index    = get_option( 'sitechat_last_full_index', '' );
		$analytics     = $this->db->get_analytics_summary();
		$api_key       = get_option( 'sitechat_gemini_api_key', '' );
		$enabled       = get_option( 'sitechat_enabled' ) === '1';

		include SITECHAT_PLUGIN_DIR . 'admin/views/dashboard.php';
	}
}
