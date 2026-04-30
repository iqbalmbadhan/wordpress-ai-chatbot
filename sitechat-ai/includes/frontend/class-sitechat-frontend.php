<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Frontend {

	public function init(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_widget' ] );
	}

	public function enqueue_assets(): void {
		if ( ! $this->should_show() ) {
			return;
		}

		wp_enqueue_style(
			'sitechat-widget',
			SITECHAT_PLUGIN_URL . 'public/css/sitechat-widget.css',
			[],
			SITECHAT_VERSION
		);

		wp_enqueue_script(
			'sitechat-widget',
			SITECHAT_PLUGIN_URL . 'public/js/sitechat-widget.js',
			[],
			SITECHAT_VERSION,
			true
		);

		$primary   = get_option( 'sitechat_primary_color', '#2563eb' );
		$secondary = get_option( 'sitechat_secondary_color', '#1e40af' );

		wp_localize_script( 'sitechat-widget', 'sitechatConfig', [
			'apiUrl'          => rest_url( 'sitechat/v1/chat' ),
			'feedbackUrl'     => rest_url( 'sitechat/v1/feedback' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'displayMode'     => get_option( 'sitechat_display_mode', 'bubble' ),
			'title'           => get_option( 'sitechat_widget_title', 'Ask AI Assistant' ),
			'welcomeMessage'  => get_option( 'sitechat_welcome_message', 'Hi! How can I help you?' ),
			'placeholder'     => get_option( 'sitechat_placeholder', 'Type your question...' ),
			'botName'         => get_option( 'sitechat_bot_name', 'AI Assistant' ),
			'botAvatar'       => get_option( 'sitechat_bot_avatar', '' ),
			'showSources'     => get_option( 'sitechat_show_sources', '1' ) === '1',
			'showPoweredBy'   => get_option( 'sitechat_show_powered_by', '1' ) === '1',
			'primaryColor'    => $primary,
			'secondaryColor'  => $secondary,
			'bubblePosition'  => get_option( 'sitechat_bubble_position', 'bottom-right' ),
			'bubbleSize'      => get_option( 'sitechat_bubble_size', '60' ),
			'widgetWidth'     => get_option( 'sitechat_widget_width', '420' ),
			'widgetHeight'    => get_option( 'sitechat_widget_height', '600' ),
			'slideinSide'     => get_option( 'sitechat_slidein_side', 'right' ),
			'slideinWidth'    => get_option( 'sitechat_slidein_width', '400' ),
			'customCss'       => get_option( 'sitechat_custom_css', '' ),
			'strings'         => [
				'typing'     => __( 'AI is thinking…', 'sitechat-ai' ),
				'error'      => __( 'Something went wrong. Please try again.', 'sitechat-ai' ),
				'sources'    => __( 'Sources', 'sitechat-ai' ),
				'helpful'    => __( 'Helpful', 'sitechat-ai' ),
				'notHelpful' => __( 'Not helpful', 'sitechat-ai' ),
				'poweredBy'  => __( 'Powered by SiteChat AI', 'sitechat-ai' ),
				'close'      => __( 'Close chat', 'sitechat-ai' ),
				'open'       => __( 'Open chat', 'sitechat-ai' ),
				'send'       => __( 'Send', 'sitechat-ai' ),
				'clearChat'  => __( 'Clear conversation', 'sitechat-ai' ),
			],
		] );

		// Inject custom CSS
		$custom_css = get_option( 'sitechat_custom_css', '' );
		if ( $custom_css ) {
			wp_add_inline_style( 'sitechat-widget', wp_strip_all_tags( $custom_css ) );
		}
	}

	public function render_widget(): void {
		if ( ! $this->should_show() ) {
			return;
		}
		$mode = get_option( 'sitechat_display_mode', 'bubble' );
		if ( $mode === 'embedded' || $mode === 'full_page' ) {
			return;
		}
		echo '<div id="sitechat-root" data-mode="' . esc_attr( $mode ) . '"></div>';
	}

	private function should_show(): bool {
		if ( get_option( 'sitechat_enabled' ) !== '1' ) {
			return false;
		}

		$show_on   = get_option( 'sitechat_show_on', 'all' );
		$page_list = (array) get_option( 'sitechat_page_list', [] );

		if ( $show_on === 'all' ) {
			return true;
		}

		$post_id = (int) get_the_ID();

		if ( $show_on === 'specific' ) {
			return in_array( $post_id, $page_list, true );
		}

		if ( $show_on === 'exclude' ) {
			return ! in_array( $post_id, $page_list, true );
		}

		return true;
	}
}
