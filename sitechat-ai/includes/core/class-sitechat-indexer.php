<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Indexer {

	private SiteChat_DB         $db;
	private SiteChat_Embeddings $embeddings;

	public function __construct( SiteChat_DB $db, SiteChat_Embeddings $embeddings ) {
		$this->db         = $db;
		$this->embeddings = $embeddings;
	}

	/**
	 * Index all eligible posts across configured post types.
	 *
	 * @return array{indexed:int, failed:int, skipped:int}
	 */
	public function index_all(): array {
		$post_types   = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		$excluded_ids = (array) get_option( 'sitechat_excluded_ids', [] );
		$indexed      = 0;
		$failed       = 0;
		$skipped      = 0;

		$posts = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'exclude'        => $excluded_ids,
		] );

		foreach ( $posts as $post_id ) {
			$result = $this->index_post( (int) $post_id );
			if ( $result === 'indexed' ) {
				$indexed++;
			} elseif ( $result === 'skipped' ) {
				$skipped++;
			} else {
				$failed++;
			}
		}

		update_option( 'sitechat_total_indexed', $this->db->count_documents( 'indexed' ) );
		update_option( 'sitechat_total_chunks', $this->db->count_chunks() );
		update_option( 'sitechat_last_full_index', current_time( 'mysql' ) );

		return compact( 'indexed', 'failed', 'skipped' );
	}

	/**
	 * Index a single post. Returns 'indexed', 'skipped', or 'failed'.
	 */
	public function index_post( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return 'skipped';
		}

		$content = $this->extract_content( $post );
		if ( strlen( $content ) < 50 ) {
			return 'skipped';
		}

		$hash     = md5( $content );
		$existing = $this->db->get_document_by_post_id( $post_id );

		// Skip if content unchanged
		if ( $existing && $existing->content_hash === $hash ) {
			return 'skipped';
		}

		$doc_id = $this->db->upsert_document( [
			'post_id'      => $post_id,
			'url'          => get_permalink( $post_id ),
			'title'        => get_the_title( $post_id ),
			'content_type' => $post->post_type,
			'content_hash' => $hash,
			'word_count'   => str_word_count( $content ),
			'status'       => 'pending',
		] );

		if ( ! $doc_id ) {
			return 'failed';
		}

		$this->db->delete_chunks_for_document( $doc_id );

		$chunks      = $this->chunk_text( $content, $post );
		$max_chunks  = (int) get_option( 'sitechat_max_chunks_per_doc', 20 );
		$chunks      = array_slice( $chunks, 0, $max_chunks );
		$texts       = array_column( $chunks, 'content' );

		$embeddings = $this->embeddings->embed_batch( $texts );
		if ( is_wp_error( $embeddings ) ) {
			$this->db->update_document_status( $doc_id, 'failed', $embeddings->get_error_message() );
			return 'failed';
		}

		foreach ( $chunks as $i => $chunk ) {
			$floats = $embeddings[ $i ] ?? [];
			if ( empty( $floats ) ) {
				continue;
			}
			$binary = SiteChat_Embeddings::pack_embedding( $floats );
			$this->db->insert_chunk(
				$doc_id,
				$i,
				$chunk['content'],
				$chunk['heading'] ?? null,
				str_word_count( $chunk['content'] ),
				$binary
			);
		}

		$this->db->update_document_status( $doc_id, 'indexed' );
		$this->db->wpdb->update( $this->db->documents, [ 'chunk_count' => count( $chunks ) ], [ 'id' => $doc_id ] );

		return 'indexed';
	}

	public function index_post_on_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$excluded_ids = (array) get_option( 'sitechat_excluded_ids', [] );
		if ( in_array( $post_id, $excluded_ids, true ) ) {
			return;
		}
		$post_types = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}
		if ( $post->post_status === 'publish' ) {
			$this->index_post( $post_id );
		}
	}

	public function remove_post_from_index( int $post_id ): void {
		$doc = $this->db->get_document_by_post_id( $post_id );
		if ( $doc ) {
			$this->db->delete_document( (int) $doc->id );
		}
	}

	// ── Content Extraction ───────────────────────────────────────────────────

	private function extract_content( \WP_Post $post ): string {
		$content = $post->post_content;

		// Apply filters so shortcodes/blocks render to text
		$content = apply_filters( 'the_content', $content );
		$content = wp_strip_all_tags( $content, true );
		$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
		$content = preg_replace( '/\s+/', ' ', $content );

		// Prepend title and excerpt for better semantic matching
		$title = get_the_title( $post->ID );
		$excerpt = has_excerpt( $post->ID ) ? get_the_excerpt( $post->ID ) : '';

		$parts = array_filter( [ $title, $excerpt, $content ] );
		return implode( "\n\n", $parts );
	}

	// ── Chunking ─────────────────────────────────────────────────────────────

	private function chunk_text( string $text, \WP_Post $post ): array {
		$chunk_size    = (int) get_option( 'sitechat_chunk_size', 1500 );
		$chunk_overlap = (int) get_option( 'sitechat_chunk_overlap', 200 );
		$chunks        = [];

		// Split on paragraph boundaries first
		$paragraphs = preg_split( '/\n{2,}/', $text );
		$paragraphs = array_filter( array_map( 'trim', $paragraphs ) );

		$current       = '';
		$current_start = 0;

		foreach ( $paragraphs as $para ) {
			if ( strlen( $current ) + strlen( $para ) + 2 <= $chunk_size ) {
				$current .= ( $current ? "\n\n" : '' ) . $para;
			} else {
				if ( $current ) {
					$chunks[] = [ 'content' => $current, 'heading' => get_the_title( $post->ID ) ];
				}
				// If paragraph itself is bigger than chunk_size, split by sentences
				if ( strlen( $para ) > $chunk_size ) {
					$sub = $this->split_long_text( $para, $chunk_size, $chunk_overlap );
					foreach ( $sub as $s ) {
						$chunks[] = [ 'content' => $s, 'heading' => null ];
					}
					$current = '';
				} else {
					// Start new chunk with overlap from previous
					$overlap_text = $current ? $this->tail_of( $current, $chunk_overlap ) : '';
					$current      = trim( $overlap_text . "\n\n" . $para );
				}
			}
		}

		if ( trim( $current ) ) {
			$chunks[] = [ 'content' => trim( $current ), 'heading' => get_the_title( $post->ID ) ];
		}

		return $chunks;
	}

	private function split_long_text( string $text, int $size, int $overlap ): array {
		$chunks = [];
		$start  = 0;
		$len    = strlen( $text );

		while ( $start < $len ) {
			$end   = min( $start + $size, $len );
			$chunk = substr( $text, $start, $end - $start );
			$chunks[] = $chunk;
			$start  = $end - $overlap;
			if ( $start >= $len ) {
				break;
			}
		}
		return $chunks;
	}

	private function tail_of( string $text, int $max_chars ): string {
		if ( strlen( $text ) <= $max_chars ) {
			return $text;
		}
		return substr( $text, -$max_chars );
	}
}
