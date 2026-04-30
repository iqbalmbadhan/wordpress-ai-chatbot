<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Shortcode {

	public function init(): void {
		add_shortcode( 'sitechat', [ $this, 'render' ] );
	}

	public function render( array $atts ): string {
		if ( get_option( 'sitechat_enabled' ) !== '1' ) {
			return '';
		}

		$atts = shortcode_atts( [
			'height' => get_option( 'sitechat_widget_height', '600' ),
			'width'  => '100%',
		], $atts, 'sitechat' );

		// Ensure scripts are enqueued for embedded use
		if ( ! wp_script_is( 'sitechat-widget', 'enqueued' ) ) {
			wp_enqueue_script( 'sitechat-widget' );
			wp_enqueue_style( 'sitechat-widget' );
		}

		$height = absint( $atts['height'] );
		$width  = esc_attr( $atts['width'] );

		return sprintf(
			'<div id="sitechat-root" data-mode="embedded" style="height:%dpx;width:%s;"></div>',
			$height,
			$width
		);
	}
}
