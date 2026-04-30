<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Search {

	private SiteChat_DB $db;

	public function __construct( SiteChat_DB $db ) {
		$this->db = $db;
	}

	/**
	 * Find the top-k chunks most similar to the query embedding.
	 *
	 * @param float[] $query_embedding
	 * @param int     $top_k
	 * @param float   $min_score Minimum cosine similarity threshold (0–1)
	 * @return array  Array of result arrays with keys: content, heading, url, title, similarity
	 */
	public function search( array $query_embedding, int $top_k = 5, float $min_score = 0.3 ): array {
		$chunks  = $this->db->get_all_chunks_with_embeddings();
		$results = [];

		foreach ( $chunks as $chunk ) {
			$stored = SiteChat_Embeddings::unpack_embedding( $chunk['embedding'] );
			if ( count( $stored ) < 1 ) {
				continue;
			}
			$sim = $this->cosine_similarity( $query_embedding, $stored );
			if ( $sim >= $min_score ) {
				$results[] = [
					'content'    => $chunk['content'],
					'heading'    => $chunk['heading'],
					'url'        => $chunk['url'],
					'title'      => $chunk['title'],
					'similarity' => $sim,
				];
			}
		}

		usort( $results, fn( $a, $b ) => $b['similarity'] <=> $a['similarity'] );
		return array_slice( $results, 0, $top_k );
	}

	private function cosine_similarity( array $a, array $b ): float {
		$len = min( count( $a ), count( $b ) );
		if ( $len === 0 ) {
			return 0.0;
		}

		$dot = 0.0;
		$ma  = 0.0;
		$mb  = 0.0;

		for ( $i = 0; $i < $len; $i++ ) {
			$dot += $a[ $i ] * $b[ $i ];
			$ma  += $a[ $i ] ** 2;
			$mb  += $b[ $i ] ** 2;
		}

		$denom = sqrt( $ma ) * sqrt( $mb );
		return $denom > 0.0 ? $dot / $denom : 0.0;
	}
}
