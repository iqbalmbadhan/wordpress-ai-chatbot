<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Cron {

	private SiteChat_Indexer $indexer;
	private SiteChat_DB      $db;

	public function __construct( SiteChat_Indexer $indexer, SiteChat_DB $db ) {
		$this->indexer = $indexer;
		$this->db      = $db;
	}

	public function init(): void {
		add_action( 'sitechat_auto_reindex', [ $this, 'run_reindex' ] );
	}

	/**
	 * Daily cron handler: re-indexes stale documents and purges old logs.
	 */
	public function run_reindex(): void {
		if ( get_option( 'sitechat_enabled' ) !== '1' ) {
			return;
		}

		$started = microtime( true );

		// Only re-index documents whose content hash has changed, not the full corpus
		$stale = $this->db->get_documents_needing_reindex();

		$indexed = 0;
		$failed  = 0;
		$errors  = [];

		foreach ( $stale as $doc ) {
			$result = $this->indexer->index_post( (int) $doc->post_id );
			if ( ( $result['status'] ?? '' ) === 'indexed' ) {
				$indexed++;
			} else {
				$failed++;
				if ( ! empty( $result['error'] ) ) {
					$errors[ $doc->post_id ] = $result['error'];
				}
			}
		}

		// Clean up logs older than 90 days
		$deleted_logs = $this->db->delete_old_logs( 90 );

		// Refresh global counts
		update_option( 'sitechat_total_indexed', $this->db->get_document_count( 'indexed' ) );
		update_option( 'sitechat_total_chunks',  $this->db->get_total_chunk_count() );

		$elapsed_ms = (int) round( ( microtime( true ) - $started ) * 1000 );

		update_option( 'sitechat_last_cron_result', [
			'ran_at'       => current_time( 'mysql' ),
			'stale_found'  => count( $stale ),
			'indexed'      => $indexed,
			'failed'       => $failed,
			'errors'       => $errors,
			'logs_deleted' => $deleted_logs,
			'elapsed_ms'   => $elapsed_ms,
		] );
	}
}
