<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin_Appearance {

	public function render(): void {
		$settings = $this->get_settings();
		include SITECHAT_PLUGIN_DIR . 'admin/views/appearance.php';
	}

	public function save(): void {
		$fields = [
			'sitechat_display_mode'     => 'sanitize_text_field',
			'sitechat_widget_title'     => 'sanitize_text_field',
			'sitechat_welcome_message'  => 'sanitize_textarea_field',
			'sitechat_placeholder'      => 'sanitize_text_field',
			'sitechat_primary_color'    => 'sanitize_hex_color',
			'sitechat_secondary_color'  => 'sanitize_hex_color',
			'sitechat_bubble_position'  => 'sanitize_text_field',
			'sitechat_bubble_size'      => 'absint',
			'sitechat_widget_width'     => 'absint',
			'sitechat_widget_height'    => 'absint',
			'sitechat_bot_name'         => 'sanitize_text_field',
			'sitechat_bot_avatar'       => 'esc_url_raw',
			'sitechat_custom_css'       => 'wp_strip_all_tags',
			'sitechat_full_page_slug'   => 'sanitize_title',
			'sitechat_full_page_title'  => 'sanitize_text_field',
			'sitechat_full_page_layout' => 'sanitize_text_field',
			'sitechat_slidein_side'     => 'sanitize_text_field',
			'sitechat_slidein_width'    => 'absint',
			'sitechat_show_on'          => 'sanitize_text_field',
		];

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, $sanitizer( $_POST[ $key ] ) );
			}
		}

		// Checkboxes
		update_option( 'sitechat_show_sources', isset( $_POST['sitechat_show_sources'] ) ? '1' : '0' );
		update_option( 'sitechat_show_powered_by', isset( $_POST['sitechat_show_powered_by'] ) ? '1' : '0' );

		// Page list (array of IDs)
		$page_list = isset( $_POST['sitechat_page_list'] )
			? array_map( 'absint', (array) $_POST['sitechat_page_list'] )
			: [];
		update_option( 'sitechat_page_list', $page_list );

		// Flush rewrite rules if slug changed
		flush_rewrite_rules();
	}

	private function get_settings(): array {
		$keys = [
			'sitechat_display_mode', 'sitechat_widget_title', 'sitechat_welcome_message',
			'sitechat_placeholder', 'sitechat_primary_color', 'sitechat_secondary_color',
			'sitechat_bubble_position', 'sitechat_bubble_size', 'sitechat_widget_width',
			'sitechat_widget_height', 'sitechat_show_sources', 'sitechat_show_powered_by',
			'sitechat_bot_name', 'sitechat_bot_avatar', 'sitechat_custom_css',
			'sitechat_full_page_slug', 'sitechat_full_page_title', 'sitechat_full_page_layout',
			'sitechat_slidein_side', 'sitechat_slidein_width', 'sitechat_show_on', 'sitechat_page_list',
		];
		$settings = [];
		foreach ( $keys as $key ) {
			$settings[ $key ] = get_option( $key );
		}
		return $settings;
	}
}
