<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin_Settings {

	public function render(): void {
		$settings = $this->get_settings();
		include SITECHAT_PLUGIN_DIR . 'admin/views/settings.php';
	}

	public function save(): void {
		$fields = [
			// API keys
			'sitechat_gemini_api_key'     => 'sanitize_text_field',
			// General
			'sitechat_system_prompt'      => 'sanitize_textarea_field',
			'sitechat_chunk_size'         => 'absint',
			'sitechat_chunk_overlap'      => 'absint',
			'sitechat_max_chunks_per_doc' => 'absint',
			'sitechat_chat_max_history'   => 'absint',
			'sitechat_rate_limit'         => 'absint',
			// Ollama URL
			'sitechat_ollama_base_url'    => 'sanitize_text_field',
			// Per-provider API keys
			'sitechat_openai_api_key'     => 'sanitize_text_field',
			'sitechat_anthropic_api_key'  => 'sanitize_text_field',
			'sitechat_deepseek_api_key'   => 'sanitize_text_field',
			'sitechat_groq_api_key'       => 'sanitize_text_field',
			'sitechat_mistral_api_key'    => 'sanitize_text_field',
			'sitechat_qwen_api_key'       => 'sanitize_text_field',
			'sitechat_openrouter_api_key' => 'sanitize_text_field',
		];

		foreach ( $fields as $key => $sanitizer ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, $sanitizer( $_POST[ $key ] ) );
			}
		}

		// Indexing provider for non-native-embedding chat providers
		if ( isset( $_POST['sitechat_index_provider'] ) ) {
			update_option( 'sitechat_index_provider', sanitize_key( $_POST['sitechat_index_provider'] ) );
		}

		// Combined "provider::model" select → split into two options
		if ( ! empty( $_POST['sitechat_chat_model_combined'] ) ) {
			$combined = sanitize_text_field( wp_unslash( $_POST['sitechat_chat_model_combined'] ) );
			$parts    = explode( '::', $combined, 2 );
			update_option( 'sitechat_chat_provider', sanitize_key( $parts[0] ?? 'gemini' ) );
			update_option( 'sitechat_chat_model',    sanitize_text_field( $parts[1] ?? 'gemini-2.0-flash' ) );
		}

		// Checkboxes
		update_option( 'sitechat_enabled',    isset( $_POST['sitechat_enabled'] )    ? '1' : '0' );
		update_option( 'sitechat_auto_index', isset( $_POST['sitechat_auto_index'] ) ? '1' : '0' );

		// Index post types (array)
		$post_types = isset( $_POST['sitechat_index_post_types'] )
			? array_map( 'sanitize_key', (array) $_POST['sitechat_index_post_types'] )
			: [];
		update_option( 'sitechat_index_post_types', $post_types );

		// Excluded IDs (array)
		$excluded = isset( $_POST['sitechat_excluded_ids'] )
			? array_map( 'absint', (array) $_POST['sitechat_excluded_ids'] )
			: [];
		update_option( 'sitechat_excluded_ids', $excluded );

		delete_transient( 'sitechat_config_cache' );
	}

	private function get_settings(): array {
		$keys = [
			'sitechat_gemini_api_key', 'sitechat_enabled', 'sitechat_auto_index',
			'sitechat_index_post_types', 'sitechat_excluded_ids', 'sitechat_max_chunks_per_doc',
			'sitechat_chunk_size', 'sitechat_chunk_overlap', 'sitechat_chat_max_history',
			'sitechat_rate_limit', 'sitechat_system_prompt',
			// Indexing + Chat provider
			'sitechat_index_provider',
			'sitechat_chat_provider', 'sitechat_chat_model', 'sitechat_ollama_base_url',
			// Per-provider keys
			'sitechat_openai_api_key', 'sitechat_anthropic_api_key', 'sitechat_deepseek_api_key',
			'sitechat_groq_api_key', 'sitechat_mistral_api_key', 'sitechat_qwen_api_key',
			'sitechat_openrouter_api_key',
		];
		$settings = [];
		foreach ( $keys as $key ) {
			$settings[ $key ] = get_option( $key );
		}
		// Defaults
		if ( ! $settings['sitechat_chat_provider'] ) {
			$settings['sitechat_chat_provider'] = 'gemini';
		}
		if ( ! $settings['sitechat_chat_model'] ) {
			$settings['sitechat_chat_model'] = 'gemini-2.0-flash';
		}
		if ( ! $settings['sitechat_ollama_base_url'] ) {
			$settings['sitechat_ollama_base_url'] = 'http://localhost:11434';
		}
		return $settings;
	}
}
