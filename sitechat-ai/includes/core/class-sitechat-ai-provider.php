<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry and dispatcher for all AI chat providers.
 *
 * Embeddings always use Google Gemini (text-embedding-004).
 * This class handles the CHAT / generation side only.
 */
class SiteChat_AI_Provider {

	// ── Registry ──────────────────────────────────────────────────────────────

	public static function providers(): array {
		return [
			'gemini' => [
				'label'      => 'Google Gemini',
				'has_free'   => true,
				'key_url'    => 'https://aistudio.google.com/apikey',
				'key_option' => 'sitechat_gemini_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'gemini-2.0-flash'      => [ 'label' => 'Gemini 2.0 Flash',      'free' => true  ],
					'gemini-2.0-flash-lite' => [ 'label' => 'Gemini 2.0 Flash Lite', 'free' => true  ],
					'gemini-1.5-flash'      => [ 'label' => 'Gemini 1.5 Flash',      'free' => true  ],
					'gemini-1.5-pro'        => [ 'label' => 'Gemini 1.5 Pro',        'free' => false ],
					'gemini-2.5-pro'        => [ 'label' => 'Gemini 2.5 Pro',        'free' => false ],
				],
			],
			'openai' => [
				'label'      => 'OpenAI',
				'has_free'   => false,
				'key_url'    => 'https://platform.openai.com/api-keys',
				'key_option' => 'sitechat_openai_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'gpt-4o-mini'   => [ 'label' => 'GPT-4o Mini (cheapest)', 'free' => false ],
					'gpt-4o'        => [ 'label' => 'GPT-4o',                 'free' => false ],
					'gpt-4.1-nano'  => [ 'label' => 'GPT-4.1 Nano',          'free' => false ],
					'gpt-3.5-turbo' => [ 'label' => 'GPT-3.5 Turbo',         'free' => false ],
				],
			],
			'anthropic' => [
				'label'      => 'Anthropic Claude',
				'has_free'   => false,
				'key_url'    => 'https://console.anthropic.com/',
				'key_option' => 'sitechat_anthropic_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'claude-haiku-4-5-20251001' => [ 'label' => 'Claude Haiku 4.5 (fastest/cheapest)', 'free' => false ],
					'claude-sonnet-4-6'         => [ 'label' => 'Claude Sonnet 4.6',                   'free' => false ],
					'claude-opus-4-7'           => [ 'label' => 'Claude Opus 4.7 (most capable)',       'free' => false ],
				],
			],
			'deepseek' => [
				'label'      => 'DeepSeek',
				'has_free'   => true,
				'key_url'    => 'https://platform.deepseek.com/',
				'key_option' => 'sitechat_deepseek_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'deepseek-chat'     => [ 'label' => 'DeepSeek Chat V3 (very cheap)', 'free' => false ],
					'deepseek-reasoner' => [ 'label' => 'DeepSeek R1 Reasoner',          'free' => false ],
				],
			],
			'groq' => [
				'label'      => 'Groq',
				'has_free'   => true,
				'key_url'    => 'https://console.groq.com/keys',
				'key_option' => 'sitechat_groq_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'llama-3.3-70b-versatile' => [ 'label' => 'Llama 3.3 70B',  'free' => true ],
					'llama-3.1-8b-instant'    => [ 'label' => 'Llama 3.1 8B',   'free' => true ],
					'gemma2-9b-it'            => [ 'label' => 'Gemma2 9B',      'free' => true ],
					'mixtral-8x7b-32768'      => [ 'label' => 'Mixtral 8x7B',   'free' => true ],
				],
			],
			'mistral' => [
				'label'      => 'Mistral AI',
				'has_free'   => false,
				'key_url'    => 'https://console.mistral.ai/api-keys/',
				'key_option' => 'sitechat_mistral_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'mistral-small-latest' => [ 'label' => 'Mistral Small',  'free' => false ],
					'open-mistral-7b'      => [ 'label' => 'Mistral 7B',     'free' => false ],
					'mistral-large-latest' => [ 'label' => 'Mistral Large',  'free' => false ],
				],
			],
			'qwen' => [
				'label'      => 'Qwen (Alibaba)',
				'has_free'   => true,
				'key_url'    => 'https://bailian.console.aliyun.com/',
				'key_option' => 'sitechat_qwen_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'qwen-plus'            => [ 'label' => 'Qwen Plus (free quota)', 'free' => true  ],
					'qwen-turbo'           => [ 'label' => 'Qwen Turbo (cheap)',     'free' => false ],
					'qwen-max'             => [ 'label' => 'Qwen Max',               'free' => false ],
					'qwen2.5-72b-instruct' => [ 'label' => 'Qwen 2.5 72B',          'free' => false ],
				],
			],
			'openrouter' => [
				'label'      => 'OpenRouter (many free models)',
				'has_free'   => true,
				'key_url'    => 'https://openrouter.ai/keys',
				'key_option' => 'sitechat_openrouter_api_key',
				'needs_key'  => true,
				'needs_url'  => false,
				'models'     => [
					'meta-llama/llama-3.3-70b-instruct:free'  => [ 'label' => 'Llama 3.3 70B (Free)',        'free' => true  ],
					'meta-llama/llama-3.1-8b-instruct:free'   => [ 'label' => 'Llama 3.1 8B (Free)',         'free' => true  ],
					'google/gemma-2-9b-it:free'               => [ 'label' => 'Gemma 2 9B (Free)',            'free' => true  ],
					'mistralai/mistral-7b-instruct:free'      => [ 'label' => 'Mistral 7B (Free)',            'free' => true  ],
					'qwen/qwen-2.5-72b-instruct:free'         => [ 'label' => 'Qwen 2.5 72B (Free)',         'free' => true  ],
					'deepseek/deepseek-r1:free'               => [ 'label' => 'DeepSeek R1 (Free)',           'free' => true  ],
					'microsoft/phi-3-mini-128k-instruct:free' => [ 'label' => 'Microsoft Phi-3 Mini (Free)', 'free' => true  ],
					'meta-llama/llama-3.3-70b-instruct'       => [ 'label' => 'Llama 3.3 70B (Paid)',        'free' => false ],
					'anthropic/claude-haiku-4-5'              => [ 'label' => 'Claude Haiku 4.5 (Paid)',      'free' => false ],
					'openai/gpt-4o-mini'                      => [ 'label' => 'GPT-4o Mini (Paid)',           'free' => false ],
				],
			],
			'ollama' => [
				'label'      => 'Ollama (self-hosted, free)',
				'has_free'   => true,
				'key_url'    => 'https://ollama.com/',
				'key_option' => '',
				'needs_key'  => false,
				'needs_url'  => true,
				'models'     => [
					'llama3.2'    => [ 'label' => 'Llama 3.2',   'free' => true ],
					'llama3.1'    => [ 'label' => 'Llama 3.1',   'free' => true ],
					'gemma3'      => [ 'label' => 'Gemma 3',     'free' => true ],
					'mistral'     => [ 'label' => 'Mistral',     'free' => true ],
					'deepseek-r1' => [ 'label' => 'DeepSeek R1', 'free' => true ],
					'qwen2.5'     => [ 'label' => 'Qwen 2.5',    'free' => true ],
					'phi4'        => [ 'label' => 'Phi-4',       'free' => true ],
				],
			],
		];
	}

	/** Subset safe for JS (omits key_option). */
	public static function providers_for_js(): array {
		$out = [];
		foreach ( self::providers() as $id => $cfg ) {
			$out[ $id ] = [
				'label'     => $cfg['label'],
				'has_free'  => $cfg['has_free'],
				'key_url'   => $cfg['key_url'],
				'needs_key' => $cfg['needs_key'],
				'needs_url' => $cfg['needs_url'],
				'models'    => $cfg['models'],
			];
		}
		return $out;
	}

	public static function get_provider( string $id ): ?array {
		return self::providers()[ $id ] ?? null;
	}

	/** Read the stored API key for a provider. */
	public static function get_api_key( string $provider_id ): string {
		$cfg = self::get_provider( $provider_id );
		if ( ! $cfg || ! $cfg['needs_key'] || ! $cfg['key_option'] ) {
			return '';
		}
		return (string) get_option( $cfg['key_option'], '' );
	}

	// ── Chat dispatcher ───────────────────────────────────────────────────────

	/**
	 * Call the chat API for a provider.
	 *
	 * @param  string   $provider_id
	 * @param  string   $model
	 * @param  string   $system_prompt  Full grounded system prompt (includes retrieved context).
	 * @param  array    $messages       [['role'=>'user|assistant','content'=>'…'],…]
	 * @param  string   $api_key
	 * @param  string   $base_url       Ollama / custom base URL.
	 * @return array{text:string}|WP_Error
	 */
	public static function chat(
		string $provider_id,
		string $model,
		string $system_prompt,
		array  $messages,
		string $api_key,
		string $base_url = ''
	): array|WP_Error {
		return match ( $provider_id ) {
			'gemini'    => self::call_gemini( $model, $system_prompt, $messages, $api_key ),
			'anthropic' => self::call_anthropic( $model, $system_prompt, $messages, $api_key ),
			default     => self::call_openai_compat( $provider_id, $model, $system_prompt, $messages, $api_key, $base_url ),
		};
	}

	/**
	 * Stream a chat response via SSE (outputs events, does NOT send the done event).
	 */
	public static function stream_chat(
		string $provider_id,
		string $model,
		string $system_prompt,
		array  $messages,
		string $api_key,
		string $base_url = ''
	): void {
		match ( $provider_id ) {
			'gemini'    => self::stream_gemini( $model, $system_prompt, $messages, $api_key ),
			'anthropic' => self::stream_anthropic( $model, $system_prompt, $messages, $api_key ),
			default     => self::stream_openai_compat( $provider_id, $model, $system_prompt, $messages, $api_key, $base_url ),
		};
	}

	/**
	 * Validate a provider's API key / connection with a minimal test request.
	 *
	 * @return true|WP_Error
	 */
	public static function validate( string $provider_id, string $api_key, string $base_url = '' ): bool|WP_Error {
		$provider = self::get_provider( $provider_id );
		if ( ! $provider ) {
			return new WP_Error( 'unknown_provider', 'Unknown provider: ' . $provider_id );
		}

		if ( $provider_id === 'ollama' ) {
			$url      = trailingslashit( $base_url ?: 'http://localhost:11434' ) . 'api/tags';
			$response = wp_remote_get( $url, [ 'timeout' => 10 ] );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( (int) wp_remote_retrieve_response_code( $response ) === 200 ) {
				return true;
			}
			return new WP_Error( 'ollama_error', 'Could not connect to Ollama at ' . esc_url( $url ) );
		}

		$models = array_keys( $provider['models'] );
		$result = self::chat(
			$provider_id,
			$models[0] ?? '',
			'Reply with exactly: OK',
			[ [ 'role' => 'user', 'content' => 'Say OK' ] ],
			$api_key,
			$base_url
		);

		return is_wp_error( $result ) ? $result : true;
	}

	// ── Gemini ────────────────────────────────────────────────────────────────

	private static function call_gemini( string $model, string $system_prompt, array $messages, string $api_key ): array|WP_Error {
		$contents = self::to_gemini_contents( $system_prompt, $messages );
		$body     = wp_json_encode( [
			'contents'         => $contents,
			'generationConfig' => [ 'temperature' => 0.3, 'maxOutputTokens' => 1024, 'topP' => 0.8 ],
		] );

		$versions = self::gemini_versions();
		$last_err = null;

		foreach ( $versions as $version ) {
			$url      = 'https://generativelanguage.googleapis.com/' . $version . '/models/'
				. rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );
			$response = wp_remote_post( $url, [
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => $body,
				'timeout' => 45,
			] );

			if ( is_wp_error( $response ) ) {
				$last_err = $response;
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code === 200 && ! empty( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
				update_option( 'sitechat_embed_api_version', $version );
				return [ 'text' => $data['candidates'][0]['content']['parts'][0]['text'] ];
			}

			$last_err = new WP_Error(
				'chat_failed',
				$data['error']['message'] ?? 'Gemini API error',
				[ 'status' => $code ]
			);
		}

		return $last_err ?? new WP_Error( 'chat_failed', 'Gemini API request failed.' );
	}

	private static function stream_gemini( string $model, string $system_prompt, array $messages, string $api_key ): void {
		$contents = self::to_gemini_contents( $system_prompt, $messages );
		$body     = wp_json_encode( [
			'contents'         => $contents,
			'generationConfig' => [ 'temperature' => 0.3, 'maxOutputTokens' => 1024, 'topP' => 0.8 ],
		] );

		$version  = self::gemini_versions()[0]; // use cached/preferred version
		$url      = 'https://generativelanguage.googleapis.com/' . $version . '/models/'
			. rawurlencode( $model ) . ':streamGenerateContent?alt=sse&key=' . rawurlencode( $api_key );

		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 60,
			'stream'  => true,
		] );

		if ( is_wp_error( $response ) ) {
			self::sse( [ 'error' => $response->get_error_message() ] );
			return;
		}

		foreach ( explode( "\n", wp_remote_retrieve_body( $response ) ) as $line ) {
			$line = trim( $line );
			if ( ! str_starts_with( $line, 'data: ' ) ) {
				continue;
			}
			$event = json_decode( substr( $line, 6 ), true );
			$token = $event['candidates'][0]['content']['parts'][0]['text'] ?? null;
			if ( $token !== null ) {
				self::sse( [ 'token' => $token ] );
			}
		}
	}

	// ── Anthropic ─────────────────────────────────────────────────────────────

	private static function call_anthropic( string $model, string $system_prompt, array $messages, string $api_key ): array|WP_Error {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => wp_json_encode( [
				'model'      => $model,
				'system'     => $system_prompt,
				'messages'   => $messages,
				'max_tokens' => 1024,
			] ),
			'timeout' => 45,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $data['content'][0]['text'] ) ) {
			return new WP_Error( 'chat_failed', $data['error']['message'] ?? 'Anthropic API error', [ 'status' => $code ] );
		}

		return [ 'text' => $data['content'][0]['text'] ];
	}

	private static function stream_anthropic( string $model, string $system_prompt, array $messages, string $api_key ): void {
		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => wp_json_encode( [
				'model'      => $model,
				'system'     => $system_prompt,
				'messages'   => $messages,
				'max_tokens' => 1024,
				'stream'     => true,
			] ),
			'timeout' => 60,
			'stream'  => true,
		] );

		if ( is_wp_error( $response ) ) {
			self::sse( [ 'error' => $response->get_error_message() ] );
			return;
		}

		foreach ( explode( "\n", wp_remote_retrieve_body( $response ) ) as $line ) {
			$line = trim( $line );
			if ( ! str_starts_with( $line, 'data: ' ) ) {
				continue;
			}
			$event = json_decode( substr( $line, 6 ), true );
			if ( ( $event['type'] ?? '' ) === 'content_block_delta' ) {
				$token = $event['delta']['text'] ?? null;
				if ( $token !== null ) {
					self::sse( [ 'token' => $token ] );
				}
			}
		}
	}

	// ── OpenAI-compatible (OpenAI, DeepSeek, Groq, Mistral, Qwen, OpenRouter, Ollama) ────

	private static function call_openai_compat(
		string $provider_id,
		string $model,
		string $system_prompt,
		array  $messages,
		string $api_key,
		string $base_url
	): array|WP_Error {
		$all_messages = array_merge(
			[ [ 'role' => 'system', 'content' => $system_prompt ] ],
			$messages
		);

		$response = wp_remote_post( self::openai_endpoint( $provider_id, $base_url ), [
			'headers' => self::openai_headers( $provider_id, $api_key ),
			'body'    => wp_json_encode( [
				'model'       => $model,
				'messages'    => $all_messages,
				'temperature' => 0.3,
				'max_tokens'  => 1024,
			] ),
			'timeout' => 45,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $data['choices'][0]['message']['content'] ) ) {
			// Extract the most descriptive error available (OpenRouter adds metadata.raw)
			$error_msg = $data['error']['message'] ?? null;
			$raw       = $data['error']['metadata']['raw'] ?? null;
			if ( $raw && is_string( $raw ) && $raw !== $error_msg ) {
				$error_msg = $error_msg ? $error_msg . ' — ' . $raw : $raw;
			}
			$error_msg = $error_msg ?? 'API error (HTTP ' . $code . ')';
			return new WP_Error( 'chat_failed', $error_msg, [ 'status' => $code ] );
		}

		return [ 'text' => $data['choices'][0]['message']['content'] ];
	}

	private static function stream_openai_compat(
		string $provider_id,
		string $model,
		string $system_prompt,
		array  $messages,
		string $api_key,
		string $base_url
	): void {
		$all_messages = array_merge(
			[ [ 'role' => 'system', 'content' => $system_prompt ] ],
			$messages
		);

		$response = wp_remote_post( self::openai_endpoint( $provider_id, $base_url ), [
			'headers' => self::openai_headers( $provider_id, $api_key ),
			'body'    => wp_json_encode( [
				'model'       => $model,
				'messages'    => $all_messages,
				'temperature' => 0.3,
				'max_tokens'  => 1024,
				'stream'      => true,
			] ),
			'timeout' => 60,
			'stream'  => true,
		] );

		if ( is_wp_error( $response ) ) {
			self::sse( [ 'error' => $response->get_error_message() ] );
			return;
		}

		foreach ( explode( "\n", wp_remote_retrieve_body( $response ) ) as $line ) {
			$line = trim( $line );
			if ( ! str_starts_with( $line, 'data: ' ) ) {
				continue;
			}
			$json = substr( $line, 6 );
			if ( $json === '[DONE]' ) {
				break;
			}
			$event = json_decode( $json, true );
			$token = $event['choices'][0]['delta']['content'] ?? null;
			if ( $token !== null ) {
				self::sse( [ 'token' => $token ] );
			}
		}
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Return Gemini API versions to try, cached winner first.
	 * Shares the same cache key as the embeddings class.
	 *
	 * @return string[]
	 */
	private static function gemini_versions(): array {
		$all    = [ 'v1', 'v1beta' ];
		$cached = (string) get_option( 'sitechat_embed_api_version', '' );
		if ( $cached && in_array( $cached, $all, true ) ) {
			return array_merge( [ $cached ], array_diff( $all, [ $cached ] ) );
		}
		return $all;
	}

	private static function openai_endpoint( string $provider_id, string $base_url ): string {
		return match ( $provider_id ) {
			'openai'     => 'https://api.openai.com/v1/chat/completions',
			'deepseek'   => 'https://api.deepseek.com/v1/chat/completions',
			'groq'       => 'https://api.groq.com/openai/v1/chat/completions',
			'mistral'    => 'https://api.mistral.ai/v1/chat/completions',
			'qwen'       => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions',
			'openrouter' => 'https://openrouter.ai/api/v1/chat/completions',
			'ollama'     => trailingslashit( $base_url ?: 'http://localhost:11434' ) . 'v1/chat/completions',
			default      => $base_url ?: 'https://api.openai.com/v1/chat/completions',
		};
	}

	private static function openai_headers( string $provider_id, string $api_key ): array {
		$headers = [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		];
		if ( $provider_id === 'openrouter' ) {
			$headers['HTTP-Referer'] = home_url();
			$headers['X-Title']      = get_bloginfo( 'name' );
		}
		return $headers;
	}

	/**
	 * Convert generic messages + system prompt to Gemini's contents[] format.
	 * Gemini has no dedicated system role, so the system prompt is prepended
	 * to the first user message.
	 */
	private static function to_gemini_contents( string $system_prompt, array $messages ): array {
		$contents = [];
		$injected = false;

		foreach ( $messages as $msg ) {
			$role = ( $msg['role'] ?? 'user' ) === 'assistant' ? 'model' : 'user';
			$text = (string) ( $msg['content'] ?? '' );

			if ( ! $injected && $role === 'user' ) {
				$text     = $system_prompt . "\n\n" . $text;
				$injected = true;
			}

			$contents[] = [ 'role' => $role, 'parts' => [ [ 'text' => $text ] ] ];
		}

		if ( empty( $contents ) ) {
			$contents[] = [ 'role' => 'user', 'parts' => [ [ 'text' => $system_prompt . "\n\nHello" ] ] ];
		}

		return $contents;
	}

	private static function sse( array $data ): void {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
		if ( ob_get_level() > 0 ) {
			ob_flush();
		}
		flush();
	}
}
