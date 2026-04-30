<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$title   = get_option( 'sitechat_full_page_title', 'Chat with AI' );
$layout  = get_option( 'sitechat_full_page_layout', 'centered' );
$width   = get_option( 'sitechat_widget_width', '420' );
$height  = get_option( 'sitechat_widget_height', '600' );

wp_enqueue_style(  'sitechat-widget', SITECHAT_PLUGIN_URL . 'public/css/sitechat-widget.css', [], SITECHAT_VERSION );
wp_enqueue_script( 'sitechat-widget', SITECHAT_PLUGIN_URL . 'public/js/sitechat-widget.js',  [], SITECHAT_VERSION, true );

$primary   = get_option( 'sitechat_primary_color', '#2563eb' );
$secondary = get_option( 'sitechat_secondary_color', '#1e40af' );

wp_localize_script( 'sitechat-widget', 'sitechatConfig', [
	'apiUrl'         => rest_url( 'sitechat/v1/chat' ),
	'feedbackUrl'    => rest_url( 'sitechat/v1/feedback' ),
	'nonce'          => wp_create_nonce( 'wp_rest' ),
	'displayMode'    => 'embedded',
	'title'          => get_option( 'sitechat_widget_title', 'Ask AI Assistant' ),
	'welcomeMessage' => get_option( 'sitechat_welcome_message', 'Hi! How can I help you?' ),
	'placeholder'    => get_option( 'sitechat_placeholder', 'Type your question...' ),
	'botName'        => get_option( 'sitechat_bot_name', 'AI Assistant' ),
	'botAvatar'      => get_option( 'sitechat_bot_avatar', '' ),
	'showSources'    => get_option( 'sitechat_show_sources', '1' ) === '1',
	'showPoweredBy'  => get_option( 'sitechat_show_powered_by', '1' ) === '1',
	'primaryColor'   => $primary,
	'secondaryColor' => $secondary,
	'widgetWidth'    => $layout === 'full_width' ? '100%' : $width,
	'widgetHeight'   => $layout === 'full_width' ? '100vh' : $height,
	'strings'        => [
		'typing'     => __( 'AI is thinking…', 'sitechat-ai' ),
		'error'      => __( 'Something went wrong. Please try again.', 'sitechat-ai' ),
		'sources'    => __( 'Sources', 'sitechat-ai' ),
		'helpful'    => __( 'Helpful', 'sitechat-ai' ),
		'notHelpful' => __( 'Not helpful', 'sitechat-ai' ),
		'poweredBy'  => __( 'Powered by SiteChat AI', 'sitechat-ai' ),
		'close'      => __( 'Close', 'sitechat-ai' ),
		'send'       => __( 'Send', 'sitechat-ai' ),
		'clearChat'  => __( 'Clear conversation', 'sitechat-ai' ),
	],
] );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
	<?php wp_head(); ?>
	<style>
		*, *::before, *::after { box-sizing: border-box; }
		html, body { margin: 0; padding: 0; height: 100%; }

		body {
			background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
			min-height: 100vh;
			display: flex;
			flex-direction: column;
		}

		.sitechat-fp-header {
			padding: 16px 24px;
			background: #fff;
			border-bottom: 1px solid #e5e7eb;
			display: flex;
			align-items: center;
			gap: 12px;
		}

		.sitechat-fp-header a {
			color: #6b7280;
			text-decoration: none;
			font-size: 13px;
			display: flex;
			align-items: center;
			gap: 4px;
		}

		.sitechat-fp-header a:hover { color: #111827; }

		.sitechat-fp-title {
			flex: 1;
			font-size: 18px;
			font-weight: 700;
			color: #111827;
			text-align: center;
		}

		.sitechat-fp-main {
			flex: 1;
			display: flex;
			align-items: <?php echo $layout === 'full_width' ? 'stretch' : 'center'; ?>;
			justify-content: center;
			padding: <?php echo $layout === 'full_width' ? '0' : '32px 16px'; ?>;
		}

		<?php if ( $layout === 'full_width' ) : ?>
		.sitechat-fp-main #sitechat-root {
			width: 100%;
			flex: 1;
		}
		.sitechat-fp-main #sitechat-root .sc-widget {
			width: 100%;
			height: 100%;
			max-height: none;
			border-radius: 0;
			box-shadow: none;
		}
		<?php else : ?>
		.sitechat-fp-main #sitechat-root {
			width: min(<?php echo absint( $width ); ?>px, 100%);
			height: min(<?php echo absint( $height ); ?>px, calc(100vh - 180px));
		}
		.sitechat-fp-main #sitechat-root .sc-widget {
			width: 100%;
			height: 100%;
			max-height: none;
			box-shadow: 0 16px 60px rgba(0,0,0,.15);
		}
		<?php endif; ?>
	</style>
</head>
<body <?php body_class( 'sitechat-full-page-body' ); ?>>

<header class="sitechat-fp-header">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
		&#8592; <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
	</a>
	<span class="sitechat-fp-title"><?php echo esc_html( $title ); ?></span>
	<span style="min-width:80px;"></span>
</header>

<main class="sitechat-fp-main">
	<div id="sitechat-root" data-mode="embedded"></div>
</main>

<?php wp_footer(); ?>
</body>
</html>
