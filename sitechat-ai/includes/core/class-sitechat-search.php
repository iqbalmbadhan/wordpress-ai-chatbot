<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Search {

	private SiteChat_DB         $db;
	private SiteChat_Embeddings $embeddings;

	/** Minimum cosine similarity to include a result. */
	private const MIN_SCORE = 0.4;

	public function __construct( SiteChat_DB $db, SiteChat_Embeddings $embeddings ) {
		$this->db         = $db;
		$this->embeddings = $embeddings;
	}

	/**
	 * Embed $query, load all chunk embeddings (from 1-hour transient cache),
	 * compute cosine similarity in PHP, and return the top-k results.
	 *
	 * @return array{chunk_id:int, document_id:int, content:string, heading:string|null,
	 *               url:string, title:string, similarity:float}[]
	 */
	public function search( string $query, int $top_k = 5 ): array {
		$query_embedding = $this->embeddings->embed_text( $query );
		if ( is_wp_error( $query_embedding ) ) {
			return [];
		}

		return $this->search_by_embedding( $query_embedding, $top_k );
	}

	/**
	 * Search using a pre-computed embedding vector (used by SiteChat_Chat to avoid
	 * re-embedding when the caller already has the vector).
	 *
	 * @param  float[] $query_embedding
	 */
	public function search_by_embedding( array $query_embedding, int $top_k = 5 ): array {
		$chunks  = $this->db->get_all_chunks_with_embeddings();
		$results = [];

		foreach ( $chunks as $chunk ) {
			$stored = SiteChat_Embeddings::unpack_embedding( $chunk['embedding'] );
			if ( count( $stored ) < 2 ) {
				continue;
			}

			$sim = $this->cosine_similarity( $query_embedding, $stored );
			if ( $sim >= self::MIN_SCORE ) {
				$results[] = [
					'chunk_id'    => (int) $chunk['id'],
					'document_id' => (int) $chunk['document_id'],
					'content'     => $chunk['content'],
					'heading'     => $chunk['heading'] ?: null,
					'url'         => $chunk['url'],
					'title'       => $chunk['title'],
					'similarity'  => $sim,
				];
			}
		}

		usort( $results, fn( $a, $b ) => $b['similarity'] <=> $a['similarity'] );
		return array_slice( $results, 0, $top_k );
	}

	/** Invalidate the embeddings transient cache (call after index changes). */
	public function invalidate_cache(): void {
		delete_transient( 'sitechat_embeddings_cache' );
	}

	// ── Maths ─────────────────────────────────────────────────────────────────

	private function cosine_similarity( array $a, array $b ): float {
		$len  = min( count( $a ), count( $b ) );
		$dot  = 0.0;
		$normA = 0.0;
		$normB = 0.0;

		for ( $i = 0; $i < $len; $i++ ) {
			$dot   += $a[ $i ] * $b[ $i ];
			$normA += $a[ $i ] * $a[ $i ];
			$normB += $b[ $i ] * $b[ $i ];
		}

		$denom = sqrt( $normA ) * sqrt( $normB );
		return $denom > 0.0 ? $dot / $denom : 0.0;
	}
}
