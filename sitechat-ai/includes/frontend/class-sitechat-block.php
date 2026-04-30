<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Block {

	public function init(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'sitechat-block-editor',
			SITECHAT_PLUGIN_URL . 'admin/js/sitechat-block.js',
			[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ],
			SITECHAT_VERSION,
			true
		);

		register_block_type( 'sitechat-ai/chatbot', [
			'editor_script'   => 'sitechat-block-editor',
			'render_callback' => [ $this, 'render_block' ],
			'attributes'      => [
				'height' => [
					'type'    => 'number',
					'default' => 600,
				],
			],
		] );
	}

	public function render_block( array $atts ): string {
		$shortcode = new SiteChat_Shortcode();
		return $shortcode->render( [
			'height' => $atts['height'] ?? 600,
		] );
	}
}
