<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Embeddings {

	private const EMBED_MODEL  = 'text-embedding-004';
	private const EMBED_DIM    = 768;
	private const API_BASE     = 'https://generativelanguage.googleapis.com/v1beta/models/';
	private const MAX_RETRIES  = 3;

	private function api_key(): string {
		return (string) get_option( 'sitechat_gemini_api_key', '' );
	}

	/**
	 * Embed a single text string. Returns float[768] or WP_Error.
	 */
	public function embed_text( string $text ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$url  = self::API_BASE . self::EMBED_MODEL . ':embedContent?key=' . rawurlencode( $key );
		$body = wp_json_encode( [
			'model'   => 'models/' . self::EMBED_MODEL,
			'content' => [ 'parts' => [ [ 'text' => $text ] ] ],
		] );

		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $data['embedding']['values'] ) ) {
			$msg = $data['error']['message'] ?? 'Unknown Gemini API error';
			return new WP_Error( 'embed_failed', $msg, [ 'status' => $code ] );
		}

		return array_map( 'floatval', $data['embedding']['values'] );
	}

	/** Backward-compat alias for embed_text(). */
	public function embed( string $text ): array|WP_Error {
		return $this->embed_text( $text );
	}

	/**
	 * Embed multiple texts using batchEmbedContents, respecting free-tier rate limits.
	 *
	 * Processes in batches of $batch_size with a 1-second sleep between batches.
	 * Retries failed items up to MAX_RETRIES times with exponential back-off.
	 *
	 * @param  string[] $texts
	 * @param  int      $batch_size  Max requests per API call (5 keeps well under 15 RPM).
	 * @return array[]|WP_Error      Indexed array of float[768], same order as $texts.
	 */
	public function embed_batch( array $texts, int $batch_size = 5 ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$results    = [];
		$batches    = array_chunk( array_values( $texts ), $batch_size, true );
		$batch_url  = self::API_BASE . self::EMBED_MODEL . ':batchEmbedContents?key=' . rawurlencode( $key );
		$first      = true;

		foreach ( $batches as $batch_keys => $batch ) {
			// Throttle between batches so we stay under the free-tier 15 RPM limit
			if ( ! $first ) {
				sleep( 1 );
			}
			$first = false;

			$embeddings = $this->call_batch_api( $batch_url, $batch, $key );

			if ( is_wp_error( $embeddings ) ) {
				// Retry the whole batch up to MAX_RETRIES times
				$last_error = $embeddings;
				for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
					sleep( 2 ** $attempt ); // 2s, 4s, 8s
					$embeddings = $this->call_batch_api( $batch_url, $batch, $key );
					if ( ! is_wp_error( $embeddings ) ) {
						break;
					}
					$last_error = $embeddings;
				}

				if ( is_wp_error( $embeddings ) ) {
					return $last_error;
				}
			}

			foreach ( $embeddings as $i => $floats ) {
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
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return true;
	}

	/**
	 * Pack float array to binary blob (4 bytes per float, machine-native endianness).
	 */
	public static function pack_embedding( array $floats ): string {
		return pack( 'f*', ...$floats );
	}

	/**
	 * Unpack binary blob back to float array.
	 */
	public static function unpack_embedding( string $binary ): array {
		$unpacked = unpack( 'f*', $binary );
		return $unpacked ? array_values( $unpacked ) : [];
	}

	public static function embedding_dim(): int {
		return self::EMBED_DIM;
	}

	// ── Private ───────────────────────────────────────────────────────────────

	/**
	 * Execute one batchEmbedContents call.
	 *
	 * @param  string[] $texts
	 * @return array[]|WP_Error
	 */
	private function call_batch_api( string $url, array $texts, string $key ): array|WP_Error {
		$requests = array_map( fn( $t ) => [
			'model'   => 'models/' . self::EMBED_MODEL,
			'content' => [ 'parts' => [ [ 'text' => $t ] ] ],
		], $texts );

		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => wp_json_encode( [ 'requests' => $requests ] ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 429 ) {
			return new WP_Error( 'rate_limited', 'Gemini API rate limit reached. Will retry.', [ 'status' => 429 ] );
		}

		if ( $code !== 200 || empty( $data['embeddings'] ) ) {
			$msg = $data['error']['message'] ?? 'Unknown Gemini batch embed error';
			return new WP_Error( 'embed_batch_failed', $msg, [ 'status' => $code ] );
		}

		return array_map(
			fn( $e ) => array_map( 'floatval', $e['values'] ),
			$data['embeddings']
		);
	}
}
