<?php
/**
 * Plugin Name:       SiteChat AI
 * Plugin URI:        https://iqbalmahmud.com
 * Description:       AI-powered chatbot trained on your WordPress content. Answers visitor questions with accurate information and source links. Uses Google Gemini AI (free tier).
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Md Iqbal Mahmud
 * Author URI:        https://iqbalmahmud.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sitechat-ai
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SITECHAT_VERSION', '1.1.0' );
define( 'SITECHAT_DB_VERSION', '1.0.0' );
define( 'SITECHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SITECHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SITECHAT_PLUGIN_FILE', __FILE__ );

// Core includes
require_once SITECHAT_PLUGIN_DIR . 'includes/class-sitechat-activator.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/class-sitechat-deactivator.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/core/class-sitechat-db.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/core/class-sitechat-indexer.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/core/class-sitechat-embeddings.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/core/class-sitechat-ai-provider.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/core/class-sitechat-search.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/core/class-sitechat-chat.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/core/class-sitechat-cron.php';

// Admin includes
require_once SITECHAT_PLUGIN_DIR . 'includes/admin/class-sitechat-admin.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/admin/class-sitechat-admin-dashboard.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/admin/class-sitechat-admin-content.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/admin/class-sitechat-admin-appearance.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/admin/class-sitechat-admin-analytics.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/admin/class-sitechat-admin-settings.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/admin/class-sitechat-admin-ajax.php';

// Frontend includes
require_once SITECHAT_PLUGIN_DIR . 'includes/frontend/class-sitechat-frontend.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/frontend/class-sitechat-rest-api.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/frontend/class-sitechat-shortcode.php';
require_once SITECHAT_PLUGIN_DIR . 'includes/frontend/class-sitechat-block.php';

// Loader
require_once SITECHAT_PLUGIN_DIR . 'includes/class-sitechat-loader.php';

register_activation_hook( __FILE__, [ 'SiteChat_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SiteChat_Deactivator', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain( 'sitechat-ai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	$loader = new SiteChat_Loader();
	$loader->run();
} );
