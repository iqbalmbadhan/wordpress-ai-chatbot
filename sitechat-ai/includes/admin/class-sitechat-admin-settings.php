<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin_Settings {

	public function render(): void {
		$settings = $this->get_settings();
		include SITECHAT_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function save(): void {
		$fields = [
			'sitechat_gemini_api_key'     => 'sanitize_text_field',
			'sitechat_system_prompt'      => 'sanitize_textarea_field',
			'sitechat_chunk_size'         => 'absint',
			'sitechat_chunk_overlap'      => 'absint',
			'sitechat_max_chunks_per_doc' => 'absint',
			'sitechat_chat_max_history'   => 'absint',
			'sitechat_rate_limit'         => 'absint',
		];

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, $sanitizer( $_POST[ $key ] ) );
			}
		}

		// Checkboxes
		update_option( 'sitechat_enabled', isset( $_POST['sitechat_enabled'] ) ? '1' : '0' );
		update_option( 'sitechat_auto_index', isset( $_POST['sitechat_auto_index'] ) ? '1' : '0' );

		// Index post types (array)
		$post_types = isset( $_POST['sitechat_index_post_types'] )
			? array_map( 'sanitize_key', (array) $_POST['sitechat_index_post_types'] )
			: [];
		update_option( 'sitechat_index_post_types', $post_types );

		// Excluded IDs (array)
		$excluded = isset( $_POST['sitechat_excluded_ids'] )
			? array_map( 'absint', (array) $_POST['sitechat_excluded_ids'] )
			: [];
		update_option( 'sitechat_excluded_ids', $excluded );
	}

	private function get_settings(): array {
		$keys = [
			'sitechat_gemini_api_key', 'sitechat_enabled', 'sitechat_auto_index',
			'sitechat_index_post_types', 'sitechat_excluded_ids', 'sitechat_max_chunks_per_doc',
			'sitechat_chunk_size', 'sitechat_chunk_overlap', 'sitechat_chat_max_history',
			'sitechat_rate_limit', 'sitechat_system_prompt',
		];
		$settings = [];
		foreach ( $keys as $key ) {
			$settings[ $key ] = get_option( $key );
		}
		return $settings;
	}
}
