<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_DB {

	private wpdb $wpdb;
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

	// ── Documents ────────────────────────────────────────────────────────────

	public function upsert_document( array $data ): int|false {
		$existing = $this->get_document_by_post_id( $data['post_id'] ?? null );

		if ( $existing ) {
			$this->wpdb->update( $this->documents, $data, [ 'id' => $existing->id ] );
			return $existing->id;
		}

		$this->wpdb->insert( $this->documents, $data );
		return $this->wpdb->insert_id ?: false;
	}

	public function get_document_by_post_id( ?int $post_id ): ?object {
		if ( ! $post_id ) {
			return null;
		}
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->documents} WHERE post_id = %d", $post_id )
		) ?: null;
	}

	public function get_document( int $id ): ?object {
		return $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->documents} WHERE id = %d", $id )
		) ?: null;
	}

	public function get_all_documents( array $args = [] ): array {
		$status  = $args['status'] ?? '';
		$limit   = (int) ( $args['limit'] ?? 50 );
		$offset  = (int) ( $args['offset'] ?? 0 );
		$orderby = in_array( $args['orderby'] ?? '', [ 'title', 'indexed_at', 'word_count', 'status' ], true )
			? $args['orderby'] : 'indexed_at';
		$order   = ( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$where = '';
		if ( $status ) {
			$where = $this->wpdb->prepare( 'WHERE status = %s', $status );
		}

		return $this->wpdb->get_results(
			"SELECT * FROM {$this->documents} {$where} ORDER BY {$orderby} {$order} LIMIT {$limit} OFFSET {$offset}"
		) ?: [];
	}

	public function count_documents( string $status = '' ): int {
		if ( $status ) {
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare( "SELECT COUNT(*) FROM {$this->documents} WHERE status = %s", $status )
			);
		}
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->documents}" );
	}

	public function delete_document( int $id ): void {
		// Chunks cascade via ON DELETE CASCADE if FK is supported; also delete manually for safety.
		$this->wpdb->delete( $this->chunks, [ 'document_id' => $id ] );
		$this->wpdb->delete( $this->documents, [ 'id' => $id ] );
	}

	public function update_document_status( int $id, string $status, ?string $error = null ): void {
		$data = [ 'status' => $status ];
		if ( $status === 'indexed' ) {
			$data['indexed_at'] = current_time( 'mysql' );
		}
		if ( $error !== null ) {
			$data['error_message'] = $error;
		}
		$this->wpdb->update( $this->documents, $data, [ 'id' => $id ] );
	}

	// ── Chunks ───────────────────────────────────────────────────────────────

	public function insert_chunk( int $document_id, int $chunk_index, string $content, ?string $heading, int $token_count, string $embedding_binary ): int|false {
		$this->wpdb->insert( $this->chunks, [
			'document_id' => $document_id,
			'chunk_index' => $chunk_index,
			'content'     => $content,
			'heading'     => $heading,
			'token_count' => $token_count,
			'embedding'   => $embedding_binary,
		] );
		return $this->wpdb->insert_id ?: false;
	}

	public function delete_chunks_for_document( int $document_id ): void {
		$this->wpdb->delete( $this->chunks, [ 'document_id' => $document_id ] );
	}

	public function get_all_chunks_with_embeddings(): array {
		return $this->wpdb->get_results(
			"SELECT c.id, c.document_id, c.content, c.heading, c.embedding,
			        d.url, d.title, d.content_type
			 FROM {$this->chunks} c
			 INNER JOIN {$this->documents} d ON c.document_id = d.id
			 WHERE d.status = 'indexed'",
			ARRAY_A
		) ?: [];
	}

	public function count_chunks(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->chunks}" );
	}

	// ── Chat Logs ─────────────────────────────────────────────────────────────

	public function insert_log( array $data ): int|false {
		$this->wpdb->insert( $this->logs, $data );
		return $this->wpdb->insert_id ?: false;
	}

	public function get_logs( array $args = [] ): array {
		$limit  = (int) ( $args['limit'] ?? 50 );
		$offset = (int) ( $args['offset'] ?? 0 );
		return $this->wpdb->get_results(
			"SELECT * FROM {$this->logs} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}"
		) ?: [];
	}

	public function count_logs(): int {
		return (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs}" );
	}

	public function get_analytics_summary(): array {
		$total    = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs}" );
		$today    = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->logs} WHERE DATE(created_at) = %s",
				current_time( 'Y-m-d' )
			)
		);
		$helpful  = (int) $this->wpdb->get_var( "SELECT COUNT(*) FROM {$this->logs} WHERE feedback = 'helpful'" );
		$avg_time = (float) $this->wpdb->get_var( "SELECT AVG(response_time_ms) FROM {$this->logs}" );
		$top_queries = $this->wpdb->get_results(
			"SELECT user_message, COUNT(*) as count FROM {$this->logs} GROUP BY user_message ORDER BY count DESC LIMIT 10",
			ARRAY_A
		) ?: [];

		return compact( 'total', 'today', 'helpful', 'avg_time', 'top_queries' );
	}

	public function update_log_feedback( int $id, string $feedback ): void {
		$this->wpdb->update( $this->logs, [ 'feedback' => $feedback ], [ 'id' => $id ] );
	}
}
