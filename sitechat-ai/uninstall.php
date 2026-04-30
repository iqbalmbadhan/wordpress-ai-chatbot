<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sitechat_chunks" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sitechat_documents" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sitechat_chat_logs" );

// Delete all sitechat_ options
$options = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'sitechat\_%'"
);
foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete transients
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sitechat_%' OR option_name LIKE '_transient_timeout_sitechat_%'"
);

// Remove DB version option
delete_option( 'sitechat_db_version' );
