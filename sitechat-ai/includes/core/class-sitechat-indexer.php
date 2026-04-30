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

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Index all eligible published posts.
	 *
	 * @param  callable|null $callback  fn(int $current, int $total, string $title)
	 * @return array{total:int, indexed:int, skipped:int, failed:int, errors:array}
	 */
	public function index_all( ?callable $callback = null ): array {
		$post_types   = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		$excluded_ids = (array) get_option( 'sitechat_excluded_ids', [] );

		$post_ids = get_posts( [
			'post_type'      => $post_types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'exclude'        => $excluded_ids,
		] );

		$total   = count( $post_ids );
		$indexed = 0;
		$skipped = 0;
		$failed  = 0;
		$errors  = [];

		foreach ( $post_ids as $i => $post_id ) {
			if ( $callback ) {
				$callback( $i + 1, $total, (string) get_the_title( $post_id ) );
			}

			$result = $this->index_post( (int) $post_id );

			if ( isset( $result['status'] ) ) {
				match ( $result['status'] ) {
					'indexed' => $indexed++,
					'skipped' => $skipped++,
					default   => ( $failed++ ) && ( $errors[ $post_id ] = $result['error'] ?? 'unknown' ),
				};
			}
		}

		update_option( 'sitechat_total_indexed', $this->db->get_document_count( 'indexed' ) );
		update_option( 'sitechat_total_chunks',  $this->db->get_total_chunk_count() );
		update_option( 'sitechat_last_full_index', current_time( 'mysql' ) );

		return compact( 'total', 'indexed', 'skipped', 'failed', 'errors' );
	}

	/**
	 * Index a single post by ID.
	 *
	 * @return array{status:string, chunks?:int, words?:int, error?:string}
	 */
	public function index_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return [ 'status' => 'skipped' ];
		}

		$post_types = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return [ 'status' => 'skipped' ];
		}

		$full_text = $this->extract_content( $post );
		if ( strlen( $full_text ) < 50 ) {
			return [ 'status' => 'skipped' ];
		}

		$hash     = md5( $full_text );
		$existing = $this->db->get_document_by_post_id( $post_id );

		// Skip if content unchanged
		if ( $existing && $existing->content_hash === $hash && $existing->status === 'indexed' ) {
			return [ 'status' => 'skipped' ];
		}

		$word_count = str_word_count( $full_text );

		$doc_id = $this->db->upsert_document( [
			'post_id'      => $post_id,
			'url'          => get_permalink( $post_id ),
			'title'        => get_the_title( $post_id ),
			'content_type' => $post->post_type,
			'content_hash' => $hash,
			'word_count'   => $word_count,
			'chunk_count'  => 0,
			'status'       => 'pending',
			'error_message'=> null,
		] );

		if ( ! $doc_id ) {
			return [ 'status' => 'failed', 'error' => 'DB insert failed' ];
		}

		// Chunk
		$max_chunks = (int) get_option( 'sitechat_max_chunks_per_doc', 20 );
		$raw_chunks = $this->chunk_text( $full_text );
		$raw_chunks = array_slice( $raw_chunks, 0, $max_chunks );

		if ( empty( $raw_chunks ) ) {
			$this->db->update_document_status( $doc_id, 'failed', 'No chunks produced' );
			return [ 'status' => 'failed', 'error' => 'No chunks produced' ];
		}

		// Embed
		$texts      = array_column( $raw_chunks, 'content' );
		$embeddings = $this->embeddings->embed_batch( $texts );
		if ( is_wp_error( $embeddings ) ) {
			$msg = $embeddings->get_error_message();
			$this->db->update_document_status( $doc_id, 'failed', $msg );
			return [ 'status' => 'failed', 'error' => $msg ];
		}

		// Store chunks
		$this->db->delete_chunks_by_document( $doc_id );

		$batch = [];
		foreach ( $raw_chunks as $i => $chunk ) {
			$floats = $embeddings[ $i ] ?? [];
			if ( empty( $floats ) ) {
				continue;
			}
			$batch[] = [
				'document_id' => $doc_id,
				'chunk_index' => $chunk['chunk_index'],
				'content'     => $chunk['content'],
				'heading'     => $chunk['heading'],
				'token_count' => (int) ( strlen( $chunk['content'] ) / 4 ),
				'embedding'   => SiteChat_Embeddings::pack_embedding( $floats ),
			];
		}

		$inserted = $this->db->insert_chunks_batch( $batch );

		$this->db->update_document( $doc_id, [
			'status'      => 'indexed',
			'chunk_count' => $inserted,
			'indexed_at'  => current_time( 'mysql' ),
		] );

		return [ 'status' => 'indexed', 'chunks' => $inserted, 'words' => $word_count ];
	}

	/** Called on save_post to re-index a single post if its type/status qualifies. */
	public function index_post_on_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$excluded = (array) get_option( 'sitechat_excluded_ids', [] );
		if ( in_array( $post_id, $excluded, true ) ) {
			return;
		}
		$post_types = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}
		if ( $post->post_status === 'publish' ) {
			$this->index_post( $post_id );
		} elseif ( in_array( $post->post_status, [ 'trash', 'draft', 'private' ], true ) ) {
			$this->delete_post_index( $post_id );
		}
	}

	/** Remove a post's document and all its chunks from the index. */
	public function delete_post_index( int $post_id ): bool {
		$doc = $this->db->get_document_by_post_id( $post_id );
		if ( ! $doc ) {
			return false;
		}
		return $this->db->delete_document( (int) $doc->id );
	}

	/** Backward-compat alias. */
	public function remove_post_from_index( int $post_id ): void {
		$this->delete_post_index( $post_id );
	}

	// ── Content Extraction ────────────────────────────────────────────────────

	/**
	 * Extract clean, structured text from a post for embedding.
	 * Headings are preserved as Markdown (## / ###) to aid chunking context.
	 */
	private function extract_content( \WP_Post $post ): string {
		$parts = [];

		// Title + URL header
		$parts[] = 'Title: ' . get_the_title( $post->ID );
		$parts[] = 'URL: ' . get_permalink( $post->ID );

		// Excerpt / meta description
		$excerpt = $post->post_excerpt ?: '';
		if ( ! $excerpt && class_exists( 'WPSEO_Meta' ) ) {
			$excerpt = (string) WPSEO_Meta::get_value( 'metadesc', $post->ID );
		}
		if ( $excerpt ) {
			$parts[] = wp_strip_all_tags( $excerpt );
		}

		// WooCommerce product extras
		if ( $post->post_type === 'product' && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$parts[] = 'Price: ' . $product->get_price_html();
				if ( $product->get_sku() ) {
					$parts[] = 'SKU: ' . $product->get_sku();
				}
				$short_desc = $product->get_short_description();
				if ( $short_desc ) {
					$parts[] = wp_strip_all_tags( $short_desc );
				}
				// Attributes
				foreach ( $product->get_attributes() as $attr ) {
					if ( is_object( $attr ) ) {
						$name   = wc_attribute_label( $attr->get_name() );
						$values = implode( ', ', $attr->get_slugs() );
						$parts[] = "{$name}: {$values}";
					}
				}
			}
		}

		// Main content: apply_filters renders blocks/shortcodes, then convert to markdown-ish text
		$html = apply_filters( 'the_content', $post->post_content );
		$parts[] = $this->html_to_structured_text( $html );

		// Featured image alt text
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$alt = get_post_meta( $thumb_id, '_wp_attachment_image_alt', true );
			if ( $alt ) {
				$parts[] = 'Featured image: ' . $alt;
			}
		}

		$full_text = implode( "\n\n", array_filter( array_map( 'trim', $parts ) ) );

		// Normalise excessive whitespace while preserving paragraph structure
		$full_text = preg_replace( '/[ \t]+/', ' ', $full_text );
		$full_text = preg_replace( '/\n{3,}/', "\n\n", $full_text );

		return trim( $full_text );
	}

	/**
	 * Convert HTML to structured plain text, preserving heading hierarchy as
	 * Markdown-style prefixes so the chunker can track context.
	 */
	private function html_to_structured_text( string $html ): string {
		// Strip scripts and styles
		$html = preg_replace( '/<(script|style)[^>]*>.*?<\/(script|style)>/si', '', $html );

		// Convert block-level headings to Markdown
		$html = preg_replace_callback(
			'/<h([1-6])[^>]*>(.*?)<\/h[1-6]>/si',
			function ( $m ) {
				$level  = (int) $m[1];
				$text   = wp_strip_all_tags( $m[2] );
				$prefix = str_repeat( '#', min( $level, 3 ) ) . ' ';
				return "\n\n{$prefix}{$text}\n\n";
			},
			$html
		);

		// Paragraphs and breaks become newlines
		$html = preg_replace( '/<\/?(p|br|div|li|tr)[^>]*>/i', "\n", $html );

		// List items get a dash prefix
		$html = preg_replace( '/<li[^>]*>/i', "\n- ", $html );

		// Strip remaining tags and decode entities
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Strip any leftover shortcode tags that didn't render
		$text = preg_replace( '/\[[^\]]*\]/', '', $text );

		return trim( $text );
	}

	// ── Chunking ──────────────────────────────────────────────────────────────

	/**
	 * Split text into overlapping chunks, preserving heading context.
	 *
	 * @return array{content:string, heading:string|null, chunk_index:int}[]
	 */
	private function chunk_text( string $text ): array {
		$max_chars    = (int) get_option( 'sitechat_chunk_size', 1500 );
		$overlap      = (int) get_option( 'sitechat_chunk_overlap', 200 );

		$paragraphs = preg_split( '/\n{2,}/', $text );
		$paragraphs = array_values( array_filter( array_map( 'trim', $paragraphs ) ) );

		$chunks          = [];
		$current         = '';
		$current_heading = null;
		$chunk_index     = 0;

		foreach ( $paragraphs as $para ) {
			// Track heading context
			if ( preg_match( '/^#{1,3}\s+(.+)$/', $para, $m ) ) {
				$current_heading = trim( $m[1] );
			}

			$fits = strlen( $current ) + ( $current ? 2 : 0 ) + strlen( $para ) <= $max_chars;

			if ( $fits ) {
				$current .= ( $current ? "\n\n" : '' ) . $para;
			} else {
				// Flush current chunk
				if ( $current ) {
					$chunks[] = [
						'content'     => $current,
						'heading'     => $current_heading,
						'chunk_index' => $chunk_index++,
					];
				}

				// Paragraph itself is too long — split by sentence
				if ( strlen( $para ) > $max_chars ) {
					$sub_chunks = $this->split_by_sentences( $para, $max_chars, $overlap );
					foreach ( $sub_chunks as $sub ) {
						$chunks[] = [
							'content'     => $sub,
							'heading'     => $current_heading,
							'chunk_index' => $chunk_index++,
						];
					}
					$current = '';
				} else {
					// Start new chunk with overlap tail from previous
					$overlap_text = $current ? $this->tail_of( $current, $overlap ) : '';
					$current = trim( ( $overlap_text ? $overlap_text . "\n\n" : '' ) . $para );
				}
			}
		}

		if ( trim( $current ) ) {
			$chunks[] = [
				'content'     => trim( $current ),
				'heading'     => $current_heading,
				'chunk_index' => $chunk_index,
			];
		}

		return $chunks;
	}

	/**
	 * Split a long paragraph by sentence boundaries (. ? !), never mid-sentence.
	 *
	 * @return string[]
	 */
	private function split_by_sentences( string $text, int $max_chars, int $overlap ): array {
		// Split on sentence-ending punctuation followed by a space or end of string
		$sentences = preg_split( '/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		$chunks  = [];
		$current = '';

		foreach ( $sentences as $sentence ) {
			if ( strlen( $current ) + strlen( $sentence ) + 1 <= $max_chars ) {
				$current .= ( $current ? ' ' : '' ) . $sentence;
			} else {
				if ( $current ) {
					$chunks[] = $current;
				}
				// If a single sentence exceeds max_chars, hard-split it
				if ( strlen( $sentence ) > $max_chars ) {
					$start = 0;
					$len   = strlen( $sentence );
					while ( $start < $len ) {
						$end = min( $start + $max_chars, $len );
						$chunks[] = substr( $sentence, $start, $end - $start );
						$start    = $end - $overlap;
						if ( $start < 0 || $start >= $len ) break;
					}
					$current = '';
				} else {
					$overlap_text = $current ? $this->tail_of( $current, $overlap ) : '';
					$current = ( $overlap_text ? $overlap_text . ' ' : '' ) . $sentence;
				}
			}
		}

		if ( trim( $current ) ) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	private function tail_of( string $text, int $max_chars ): string {
		if ( strlen( $text ) <= $max_chars ) {
			return $text;
		}
		// Try to break at a word boundary
		$tail = substr( $text, -$max_chars );
		$pos  = strpos( $tail, ' ' );
		return $pos !== false ? substr( $tail, $pos + 1 ) : $tail;
	}
}
