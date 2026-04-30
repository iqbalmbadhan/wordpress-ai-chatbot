<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin_Analytics {

	private SiteChat_DB $db;

	public function __construct( SiteChat_DB $db ) {
		$this->db = $db;
	}

	public function render(): void {
		$summary = $this->db->get_analytics_summary();
		$logs    = $this->db->get_logs( [ 'limit' => 50 ] );
		include SITECHAT_PLUGIN_DIR . 'admin/views/analytics.php';
	}
}
