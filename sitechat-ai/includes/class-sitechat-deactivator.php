<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Deactivator {

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'sitechat_auto_reindex' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'sitechat_auto_reindex' );
		}
		flush_rewrite_rules();
	}
}
