<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_DB {

	public wpdb $wpdb;
	public string $documents;
	public string $chunks;
	public string $logs;

	public function __construct() {
		global $wpdb;
		$this->wpdb      = $wpdb;
		$this->documents = $wpdb->prefix . 'sitechat_documents';
		$this->chunks    = $wpdb->prefix . 'sitechat_chunks';
		$this->logs      = $wpdb->prefix . 'sitechat_chat_logs';
	}

	// ── Documents ─────────────────────────────────────────────────────────────

	public function insert_document( array $data ): int|false {
		$result = $this->wpdb->insert( $this->documents, $data );
		return $result !== false ? $this->wpdb->insert_id : false;
	}

	public function update_document( int $id, array $data ): bool {
		return $this->wpdb->update( $this->documents, $data, [ 'id' => $id ] ) !== false;
	}

	/** Insert or update by post_id; returns the document ID. */
	public function upsert_document( array $data ): int|false {
		$existing = isset( $data['post_id'] ) ? $this->get_document_by_post_id( (int) $data['post_id'] ) : null;

		if ( $existing ) {
			$this->update_document( (int) $existing->id, $data );
			return (int) $existing->id;
		}

		return $this->insert_document( $data );
	}

	public function get_document( int $id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->documents} WHERE id = %d", $id )
		) ?: null;
	}

	public function get_document_by_post_id( int $post_id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->documents} WHERE post_id = %d", $post_id )
		) ?: null;
	}

	/**
	 * @param array{status?:string, content_type?:string, limit?:int, offset?:int, orderby?:string, order?:string} $args
	 */
	public function get_all_documents( array $args = [] ): array {
		$allowed_orderby = [ 'title', 'indexed_at', 'word_count', 'chunk_count', 'status', 'created_at' ];
		$orderby = in_array( $args['orderby'] ?? '', $allowed_orderby, true ) ? $args['orderby'] : 'indexed_at';
		$order   = ( strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ) ? 'ASC' : 'DESC';
		$limit   = max( 1, (int) ( $args['limit'] ?? 50 ) );
		$offset  = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$wheres = [];
		$values = [];

		if ( ! empty( $args['status'] ) ) {
			$wheres[] = 'status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['content_type'] ) ) {
			$wheres[] = 'content_type = %s';
			$values[] = $args['content_type'];
		}

		$where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';
		$sql       = "SELECT * FROM {$this->documents} {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[]  = $limit;
		$values[]  = $offset;

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$values )
		) ?: [];
	}

	public function delete_document( int $id ): bool {
		$this->wpdb->delete( $this->chunks, [ 'document_id' => $id ] );
		return $this->wpdb->delete( $this->documents, [ 'id' => $id ] ) !== false;
	}

	public function get_document_count( ?string $status = null ): int {
		if ( $status !== null ) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->documents} WHERE status = %s", $status )
			);
		}
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->documents}" );
	}

	/** Backward-compat alias. */
	public function count_documents( string $status = '' ): int {
		return $this->get_document_count( $status !== '' ? $status : null );
	}

	/** Returns documents whose stored content_hash differs from the current post content. */
	public function get_documents_needing_reindex(): array {
		$docs = $this->get_all_documents( [ 'status' => 'indexed', 'limit' => 500 ] );
		$stale = [];
		foreach ( $docs as $doc ) {
			if ( ! $doc->post_id ) {
				continue;
			}
			$post = get_post( (int) $doc->post_id );
			if ( ! $post || $post->post_status !== 'publish' ) {
				continue;
			}
			$current_hash = md5( $post->post_content );
			if ( $current_hash !== $doc->content_hash ) {
				$stale[] = $doc;
			}
		}
		return $stale;
	}

	public function update_document_status( int $id, string $status, ?string $error = null ): void {
		$data = [ 'status' => $status ];
		if ( $status === 'indexed' ) {
			$data['indexed_at'] = current_time( 'mysql' );
		}
		if ( $error !== null ) {
			$data['error_message'] = $error;
		}
		$this->update_document( $id, $data );
	}

	// ── Chunks ────────────────────────────────────────────────────────────────

	public function insert_chunk( int $document_id, int $chunk_index, string $content, ?string $heading, int $token_count, string $embedding_binary ): int|false {
		$result = $this->wpdb->insert( $this->chunks, [
			'document_id' => $document_id,
			'chunk_index' => $chunk_index,
			'content'     => $content,
			'heading'     => $heading,
			'token_count' => $token_count,
			'embedding'   => $embedding_binary,
		] );
		return $result !== false ? $this->wpdb->insert_id : false;
	}

	/**
	 * Bulk insert chunks using a single multi-row INSERT for performance.
	 * Returns number of rows inserted.
	 */
	public function insert_chunks_batch( array $chunks ): int {
		if ( empty( $chunks ) ) {
			return 0;
		}

		$placeholders = [];
		$values       = [];

		foreach ( $chunks as $chunk ) {
			$placeholders[] = '(%d, %d, %s, %s, %d, %s)';
			$values[]       = (int) $chunk['document_id'];
			$values[]       = (int) $chunk['chunk_index'];
			$values[]       = $chunk['content'];
			$values[]       = $chunk['heading'] ?? null;
			$values[]       = (int) ( $chunk['token_count'] ?? 0 );
			$values[]       = $chunk['embedding'];
		}

		$table = $this->chunks;
		$sql   = "INSERT INTO {$table} (document_id, chunk_index, content, heading, token_count, embedding) VALUES "
			. implode( ', ', $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$result = $this->wpdb->query( $this->wpdb->prepare( $sql, ...$values ) );

		// Invalidate embedding cache after new chunks are inserted
		delete_transient( 'sitechat_embeddings_cache' );

		return (int) $result;
	}

	public function get_chunks_by_document( int $document_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->chunks} WHERE document_id = %d ORDER BY chunk_index ASC",
				$document_id
			)
		) ?: [];
	}

	public function delete_chunks_by_document( int $document_id ): bool {
		$result = $this->wpdb->delete( $this->chunks, [ 'document_id' => $document_id ] );
		delete_transient( 'sitechat_embeddings_cache' );
		return $result !== false;
	}

	/** Backward-compat alias. */
	public function delete_chunks_for_document( int $document_id ): void {
		$this->delete_chunks_by_document( $document_id );
	}

	/**
	 * Load all chunk embeddings + joined document metadata.
	 * Results are transient-cached for 1 hour to avoid per-request DB reads.
	 */
	public function get_all_chunks_with_embeddings(): array {
		$cached = get_transient( 'sitechat_embeddings_cache' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows = $this->wpdb->get_results(
			"SELECT c.id, c.document_id, c.chunk_index, c.content, c.heading, c.embedding,
			        d.url, d.title, d.content_type
			 FROM {$this->chunks} c
			 INNER JOIN {$this->documents} d ON c.document_id = d.id
			 WHERE d.status = 'indexed'",
			ARRAY_A
		) ?: [];

		set_transient( 'sitechat_embeddings_cache', $rows, HOUR_IN_SECONDS );
		return $rows;
	}

	public function get_total_chunk_count(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->chunks}" );
	}

	/** Backward-compat alias. */
	public function count_chunks(): int {
		return $this->get_total_chunk_count();
	}

	// ── Chat Logs ─────────────────────────────────────────────────────────────

	public function insert_chat_log( array $data ): int|false {
		$result = $this->wpdb->insert( $this->logs, $data );
		return $result !== false ? $this->wpdb->insert_id : false;
	}

	/** Backward-compat alias. */
	public function insert_log( array $data ): int|false {
		return $this->insert_chat_log( $data );
	}

	/**
	 * @param array{limit?:int, offset?:int, date_from?:string, date_to?:string, session_id?:string} $args
	 */
	public function get_chat_logs( array $args = [] ): array {
		$limit  = max( 1, (int) ( $args['limit'] ?? 50 ) );
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );

		$wheres = [];
		$values = [];

		if ( ! empty( $args['date_from'] ) ) {
			$wheres[] = 'created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$wheres[] = 'created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}
		if ( ! empty( $args['session_id'] ) ) {
			$wheres[] = 'session_id = %s';
			$values[] = $args['session_id'];
		}

		$where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';
		$sql       = "SELECT * FROM {$this->logs} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$values[]  = $limit;
		$values[]  = $offset;

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$values )
		) ?: [];
	}

	/** Backward-compat alias. */
	public function get_logs( array $args = [] ): array {
		return $this->get_chat_logs( $args );
	}

	public function count_logs(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs}" );
	}

	/**
	 * Returns aggregate stats for a rolling period.
	 *
	 * @param string $period  '7days' | '30days' | 'all'
	 */
	public function get_chat_stats( string $period = '7days' ): object {
		$since = $this->period_to_datetime( $period );
		$where = $since ? $this->wpdb->prepare( 'WHERE created_at >= %s', $since ) : '';

		$total        = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs} {$where}" );
		$helpful      = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs} {$where} " . ( $since ? 'AND' : 'WHERE' ) . " feedback = 'helpful'" );
		$not_helpful  = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs} {$where} " . ( $since ? 'AND' : 'WHERE' ) . " feedback = 'not_helpful'" );
		$avg_sim      = (float) $this->wpdb->get_var( "SELECT AVG(similarity_avg) FROM {$this->logs} {$where}" );
		$avg_time     = (float) $this->wpdb->get_var( "SELECT AVG(response_time_ms) FROM {$this->logs} {$where}" );

		return (object) [
			'total'       => $total,
			'helpful'     => $helpful,
			'not_helpful' => $not_helpful,
			'avg_sim'     => round( $avg_sim, 3 ),
			'avg_time_ms' => round( $avg_time ),
		];
	}

	public function get_popular_questions( int $limit = 10, string $period = '30days' ): array {
		$since     = $this->period_to_datetime( $period );
		$where     = $since ? $this->wpdb->prepare( 'WHERE created_at >= %s', $since ) : '';
		$limit_sql = max( 1, $limit );

		return $this->wpdb->get_results(
			"SELECT user_message, COUNT(*) as count
			 FROM {$this->logs} {$where}
			 GROUP BY user_message
			 ORDER BY count DESC
			 LIMIT {$limit_sql}",
			ARRAY_A
		) ?: [];
	}

	/** Returns daily query counts for the last N days as [{date, count}, ...]. */
	public function get_daily_query_counts( int $days = 30 ): array {
		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT DATE(created_at) as date, COUNT(*) as count
				 FROM {$this->logs}
				 WHERE created_at >= %s
				 GROUP BY DATE(created_at)
				 ORDER BY date ASC",
				$since
			),
			ARRAY_A
		) ?: [];
	}

	/** Deletes logs older than N days and returns the number deleted. */
	public function delete_old_logs( int $days = 90 ): int {
		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->logs} WHERE created_at < %s",
				$cutoff
			)
		);
		return (int) $this->wpdb->rows_affected;
	}

	public function update_feedback( int $log_id, string $feedback ): bool {
		return $this->wpdb->update( $this->logs, [ 'feedback' => $feedback ], [ 'id' => $log_id ] ) !== false;
	}

	/** Backward-compat alias. */
	public function update_log_feedback( int $id, string $feedback ): void {
		$this->update_feedback( $id, $feedback );
	}

	/** Aggregated summary used by the admin dashboard widget. */
	public function get_analytics_summary(): array {
		$stats       = $this->get_chat_stats( 'all' );
		$today       = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->logs} WHERE DATE(created_at) = %s",
				current_time( 'Y-m-d' )
			)
		);
		$top_queries = $this->get_popular_questions( 10, '30days' );

		return [
			'total'       => $stats->total,
			'today'       => $today,
			'helpful'     => $stats->helpful,
			'avg_time'    => $stats->avg_time_ms,
			'top_queries' => $top_queries,
		];
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function period_to_datetime( string $period ): ?string {
		return match ( $period ) {
			'7days'  => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
			'30days' => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
			'all'    => null,
			default  => gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) ),
		};
	}
}
