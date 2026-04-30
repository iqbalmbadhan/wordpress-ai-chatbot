<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin_Content {

	private SiteChat_DB $db;

	public function __construct( SiteChat_DB $db ) {
		$this->db = $db;
	}

	public function render(): void {
		$documents    = $this->db->get_all_documents( [ 'limit' => 50 ] );
		$total        = $this->db->count_documents();
		$indexed      = $this->db->count_documents( 'indexed' );
		$pending      = $this->db->count_documents( 'pending' );
		$failed       = $this->db->count_documents( 'failed' );
		$post_types   = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		$excluded_ids = (array) get_option( 'sitechat_excluded_ids', [] );

		$available_post_types = get_post_types( [ 'public' => true ], 'objects' );

		include SITECHAT_PLUGIN_DIR . 'admin/views/content.php';
	}
}
