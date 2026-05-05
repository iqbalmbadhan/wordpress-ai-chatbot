<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Embeddings {

	private const EMBED_DIM = 768;
	private const MAX_RETRIES = 3;

	// Gemini
	private const GEMINI_MODEL    = 'text-embedding-004';
	private const GEMINI_VERSIONS = [ 'v1', 'v1beta' ];

	// OpenAI — dimensions=768 keeps blob size identical to Gemini
	private const OPENAI_MODEL    = 'text-embedding-3-small';
	private const OPENAI_ENDPOINT = 'https://api.openai.com/v1/embeddings';

	// ── Provider selection ────────────────────────────────────────────────────

	/**
	 * Derive the embedding provider from the selected chat provider.
	 * - gemini → gemini embeddings  (one key for everything)
	 * - openai → openai embeddings  (one key for everything)
	 * - all others → gemini (free fallback for indexing)
	 */
	private function provider(): string {
		$chat = (string) get_option( 'sitechat_chat_provider', 'gemini' );
		return in_array( $chat, [ 'gemini', 'openai' ], true ) ? $chat : 'gemini';
	}

	private function api_key(): string {
		if ( $this->provider() === 'openai' ) {
			return (string) get_option( 'sitechat_openai_api_key', '' );
		}
		return (string) get_option( 'sitechat_gemini_api_key', '' );
	}

	// ── Public API ────────────────────────────────────────────────────────────

	/**
	 * Embed a single text string. Returns float[768] or WP_Error.
	 */
	public function embed_text( string $text ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', $this->no_key_message() );
		}
		return $this->provider() === 'openai'
			? $this->openai_embed_single( $text, $key )
			: $this->gemini_embed_single( $text, $key );
	}

	/** Backward-compat alias. */
	public function embed( string $text ): array|WP_Error {
		return $this->embed_text( $text );
	}

	/**
	 * Embed multiple texts in batches. Returns float[][768] or WP_Error.
	 *
	 * @param  string[] $texts
	 * @param  int      $batch_size
	 */
	public function embed_batch( array $texts, int $batch_size = 5 ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', $this->no_key_message() );
		}
		return $this->provider() === 'openai'
			? $this->openai_embed_batch( array_values( $texts ), $key, $batch_size )
			: $this->gemini_embed_batch( array_values( $texts ), $key, $batch_size );
	}

	/**
	 * Test whether the current embedding provider + key is functional.
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

	// ── OpenAI ────────────────────────────────────────────────────────────────

	private function openai_embed_single( string $text, string $key ): array|WP_Error {
		$result = $this->openai_api_call( [ $text ], $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result[0] ?? new WP_Error( 'embed_failed', 'Empty OpenAI embedding response.' );
	}

	private function openai_embed_batch( array $texts, string $key, int $batch_size ): array|WP_Error {
		$results = [];
		$batches = array_chunk( $texts, $batch_size );
		$first   = true;

		foreach ( $batches as $batch ) {
			if ( ! $first ) {
				sleep( 1 );
			}
			$first = false;

			$embeddings = $this->openai_api_call( $batch, $key );

			if ( is_wp_error( $embeddings ) ) {
				$last = $embeddings;
				for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
					sleep( 2 ** $attempt );
					$embeddings = $this->openai_api_call( $batch, $key );
					if ( ! is_wp_error( $embeddings ) ) {
						break;
					}
					$last = $embeddings;
				}
				if ( is_wp_error( $embeddings ) ) {
					return $last;
				}
			}

			foreach ( $embeddings as $floats ) {
				$results[] = $floats;
			}
		}

		return $results;
	}

	private function openai_api_call( array $texts, string $key ): array|WP_Error {
		$response = wp_remote_post( self::OPENAI_ENDPOINT, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $key,
			],
			'body'    => wp_json_encode( [
				'model'      => self::OPENAI_MODEL,
				'input'      => count( $texts ) === 1 ? $texts[0] : $texts,
				'dimensions' => self::EMBED_DIM,
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 429 ) {
			return new WP_Error( 'rate_limited', 'OpenAI embedding rate limit reached. Will retry.', [ 'status' => 429 ] );
		}

		if ( $code === 200 && ! empty( $data['data'] ) ) {
			// Sort by index to maintain input order
			usort( $data['data'], fn( $a, $b ) => $a['index'] <=> $b['index'] );
			return array_map(
				fn( $item ) => array_map( 'floatval', $item['embedding'] ),
				$data['data']
			);
		}

		return new WP_Error(
			'embed_failed',
			$data['error']['message'] ?? 'OpenAI embedding error (HTTP ' . $code . ')',
			[ 'status' => $code ]
		);
	}

	// ── Gemini ────────────────────────────────────────────────────────────────

	private function gemini_embed_single( string $text, string $key ): array|WP_Error {
		$body     = wp_json_encode( [
			'model'   => 'models/' . self::GEMINI_MODEL,
			'content' => [ 'parts' => [ [ 'text' => $text ] ] ],
		] );
		$versions = $this->ordered_versions();
		$last_err = null;

		foreach ( $versions as $version ) {
			$url      = 'https://generativelanguage.googleapis.com/' . $version . '/models/'
				. self::GEMINI_MODEL . ':embedContent?key=' . rawurlencode( $key );
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
				update_option( 'sitechat_embed_api_version', $version );
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

	private function gemini_embed_batch( array $texts, string $key, int $batch_size ): array|WP_Error {
		$results  = [];
		$batches  = array_chunk( $texts, $batch_size );
		$versions = $this->ordered_versions();
		$first    = true;

		foreach ( $batches as $batch ) {
			if ( ! $first ) {
				sleep( 1 );
			}
			$first = false;

			$embeddings = $this->gemini_batch_api_call( $batch, $key, $versions );

			if ( is_wp_error( $embeddings ) ) {
				$last_error = $embeddings;
				for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
					sleep( 2 ** $attempt );
					$embeddings = $this->gemini_batch_api_call( $batch, $key, $versions );
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

	private function gemini_batch_api_call( array $texts, string $key, array $versions ): array|WP_Error {
		$requests = array_map( fn( $t ) => [
			'model'   => 'models/' . self::GEMINI_MODEL,
			'content' => [ 'parts' => [ [ 'text' => $t ] ] ],
		], $texts );

		$last_err = null;

		foreach ( $versions as $version ) {
			$url      = 'https://generativelanguage.googleapis.com/' . $version . '/models/'
				. self::GEMINI_MODEL . ':batchEmbedContents?key=' . rawurlencode( $key );
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
				update_option( 'sitechat_embed_api_version', $version );
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

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function ordered_versions(): array {
		$cached   = (string) get_option( 'sitechat_embed_api_version', '' );
		$versions = self::GEMINI_VERSIONS;
		if ( $cached && in_array( $cached, $versions, true ) ) {
			$versions = array_merge( [ $cached ], array_diff( $versions, [ $cached ] ) );
		}
		return $versions;
	}

	private function no_key_message(): string {
		return $this->provider() === 'openai'
			? __( 'OpenAI API key is not configured. Enter it in Settings under the Chat Answer Model section.', 'sitechat-ai' )
			: __( 'Gemini API key is not configured.', 'sitechat-ai' );
	}
}
