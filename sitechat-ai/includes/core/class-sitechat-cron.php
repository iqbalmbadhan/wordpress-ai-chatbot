<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Cron {

	private SiteChat_Indexer $indexer;

	public function __construct( SiteChat_Indexer $indexer ) {
		$this->indexer = $indexer;
	}

	public function init(): void {
		add_action( 'sitechat_auto_reindex', [ $this, 'run_reindex' ] );
	}

	public function run_reindex(): void {
		if ( get_option( 'sitechat_enabled' ) !== '1' ) {
			return;
		}
		$this->indexer->index_all();
	}
}
