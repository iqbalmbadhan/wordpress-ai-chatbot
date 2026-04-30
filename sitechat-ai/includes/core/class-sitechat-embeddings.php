<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Embeddings {

	private const EMBED_MODEL = 'text-embedding-004';
	private const EMBED_DIM   = 768;
	private const API_BASE    = 'https://generativelanguage.googleapis.com/v1beta/models/';

	private function api_key(): string {
		return (string) get_option( 'sitechat_gemini_api_key', '' );
	}

	/**
	 * Embed a single text string. Returns a float array of length 768, or WP_Error.
	 */
	public function embed( string $text ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$url      = self::API_BASE . self::EMBED_MODEL . ':embedContent?key=' . rawurlencode( $key );
		$body     = wp_json_encode( [
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
			return new WP_Error( 'embed_failed', $msg );
		}

		return array_map( 'floatval', $data['embedding']['values'] );
	}

	/**
	 * Embed multiple texts in a batch request. Returns array of float-arrays, or WP_Error.
	 */
	public function embed_batch( array $texts ): array|WP_Error {
		$key = $this->api_key();
		if ( ! $key ) {
			return new WP_Error( 'no_api_key', __( 'Gemini API key is not configured.', 'sitechat-ai' ) );
		}

		$requests = array_map( fn( $t ) => [
			'model'   => 'models/' . self::EMBED_MODEL,
			'content' => [ 'parts' => [ [ 'text' => $t ] ] ],
		], $texts );

		$url      = self::API_BASE . self::EMBED_MODEL . ':batchEmbedContents?key=' . rawurlencode( $key );
		$body     = wp_json_encode( [ 'requests' => $requests ] );
		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $data['embeddings'] ) ) {
			$msg = $data['error']['message'] ?? 'Unknown Gemini batch embed error';
			return new WP_Error( 'embed_batch_failed', $msg );
		}

		return array_map(
			fn( $e ) => array_map( 'floatval', $e['values'] ),
			$data['embeddings']
		);
	}

	/**
	 * Pack a float array to binary (little-endian 32-bit floats) for storage.
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
}
