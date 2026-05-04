<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Embeddings {

	private const EMBED_MODEL = 'text-embedding-004';
	private const EMBED_DIM   = 768;
	private const MAX_RETRIES = 3;

	// Tried in order; first successful response wins and is cached.
	private const API_VERSIONS = [ 'v1', 'v1beta' ];

	private function api_key(): string {
		return (string) get_option( 'sitechat_gemini_api_key', '' );
	}

	/**
	 * Return the base URL for the Gemini embedding API, auto-detecting which
	 * version (v1 vs v1beta) works for this API key and caching the result.
	 */
	private function api_base(): string {
		$cached = get_option( 'sitechat_embed_api_version', '' );
		if ( $cached ) {
			return 'https://generativelanguage.googleapis.com/' . $cached . '/models/';
		}
		return 'https://generativelanguage.googleapis.com/v1/models/';
	}

	/**
	 * Embed a single text string. Returns float[768] or WP_Error.
	 *
	 * Tries API v1 then v1beta automatically; caches whichever succeeds
	 * in sitechat_embed_api_version so future calls skip the retry.
	 */
	public function embed_text( string $text ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$body = wp_json_encode( [
			'model'   => 'models/' . self::EMBED_MODEL,
			'content' => [ 'parts' => [ [ 'text' => $text ] ] ],
		] );

		// Build the version list: try cached version first, then the rest.
		$versions = $this->ordered_versions();
		$last_err = null;

		foreach ( $versions as $version ) {
			$url      = 'https://generativelanguage.googleapis.com/' . $version . '/models/'
				. self::EMBED_MODEL . ':embedContent?key=' . rawurlencode( $key );
			$response = wp_remote_post( $url, [
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => $body,
				'timeout' => 30,
			] );

			if ( is_wp_error( $response ) ) {
				$last_err = $response;
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code === 200 && ! empty( $data['embedding']['values'] ) ) {
				update_option( 'sitechat_embed_api_version', $version ); // cache winner
				return array_map( 'floatval', $data['embedding']['values'] );
			}

			$last_err = new WP_Error(
				'embed_failed',
				$data['error']['message'] ?? 'Unknown Gemini API error',
				[ 'status' => $code ]
			);
		}

		return $last_err ?? new WP_Error( 'embed_failed', 'Embedding request failed.' );
	}

	/** Backward-compat alias for embed_text(). */
	public function embed( string $text ): array|WP_Error {
		return $this->embed_text( $text );
	}

	/**
	 * Embed multiple texts using batchEmbedContents, respecting free-tier rate limits.
	 *
	 * @param  string[] $texts
	 * @param  int      $batch_size  Max texts per API call (5 keeps under 15 RPM free tier).
	 * @return array[]|WP_Error  Indexed array of float[768], same order as $texts.
	 */
	public function embed_batch( array $texts, int $batch_size = 5 ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$results  = [];
		$batches  = array_chunk( array_values( $texts ), $batch_size, true );
		$versions = $this->ordered_versions();
		$first    = true;

		foreach ( $batches as $batch ) {
			if ( ! $first ) {
				sleep( 1 ); // stay under free-tier 15 RPM
			}
			$first = false;

			$embeddings = $this->call_batch_api( $batch, $key, $versions );

			if ( is_wp_error( $embeddings ) ) {
				$last_error = $embeddings;
				for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
					sleep( 2 ** $attempt );
					$embeddings = $this->call_batch_api( $batch, $key, $versions );
					if ( ! is_wp_error( $embeddings ) ) {
						break;
					}
					$last_error = $embeddings;
				}
				if ( is_wp_error( $embeddings ) ) {
					return $last_error;
				}
			}

			foreach ( $embeddings as $floats ) {
				$results[] = $floats;
			}
		}

		return $results;
	}

	/**
	 * Test whether the current API key is functional.
	 *
	 * @return true|WP_Error
	 */
	public function validate_api_key(): bool|WP_Error {
		$result = $this->embed_text( 'test' );
		return is_wp_error( $result ) ? $result : true;
	}

	/** Pack float array to binary blob (4 bytes per float, machine-native endianness). */
	public static function pack_embedding( array $floats ): string {
		return pack( 'f*', ...$floats );
	}

	/** Unpack binary blob back to float array. */
	public static function unpack_embedding( string $binary ): array {
		$unpacked = unpack( 'f*', $binary );
		return $unpacked ? array_values( $unpacked ) : [];
	}

	public static function embedding_dim(): int {
		return self::EMBED_DIM;
	}

	// ── Private ───────────────────────────────────────────────────────────────

	/**
	 * Return API versions to try, with the cached winner first.
	 *
	 * @return string[]
	 */
	private function ordered_versions(): array {
		$cached   = (string) get_option( 'sitechat_embed_api_version', '' );
		$versions = self::API_VERSIONS;
		if ( $cached && in_array( $cached, $versions, true ) ) {
			// Move cached version to front
			$versions = array_merge( [ $cached ], array_diff( $versions, [ $cached ] ) );
		}
		return $versions;
	}

	/**
	 * Execute one batchEmbedContents call, trying each version in order.
	 *
	 * @param  string[] $texts
	 * @param  string[] $versions
	 * @return array[]|WP_Error
	 */
	private function call_batch_api( array $texts, string $key, array $versions ): array|WP_Error {
		$requests = array_map( fn( $t ) => [
			'model'   => 'models/' . self::EMBED_MODEL,
			'content' => [ 'parts' => [ [ 'text' => $t ] ] ],
		], $texts );

		$last_err = null;

		foreach ( $versions as $version ) {
			$url      = 'https://generativelanguage.googleapis.com/' . $version . '/models/'
				. self::EMBED_MODEL . ':batchEmbedContents?key=' . rawurlencode( $key );
			$response = wp_remote_post( $url, [
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'requests' => $requests ] ),
				'timeout' => 60,
			] );

			if ( is_wp_error( $response ) ) {
				$last_err = $response;
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code === 429 ) {
				return new WP_Error( 'rate_limited', 'Gemini API rate limit reached. Will retry.', [ 'status' => 429 ] );
			}

			if ( $code === 200 && ! empty( $data['embeddings'] ) ) {
				update_option( 'sitechat_embed_api_version', $version ); // cache winner
				return array_map(
					fn( $e ) => array_map( 'floatval', $e['values'] ),
					$data['embeddings']
				);
			}

			$last_err = new WP_Error(
				'embed_batch_failed',
				$data['error']['message'] ?? 'Unknown Gemini batch embed error',
				[ 'status' => $code ]
			);
		}

		return $last_err ?? new WP_Error( 'embed_batch_failed', 'Batch embedding request failed.' );
	}
}
