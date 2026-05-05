<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="sitechat-tab-content">
<form method="post" action="" id="sitechat-settings-form">
	<?php wp_nonce_field( 'sitechat_settings_nonce' ); ?>
	<input type="hidden" name="sitechat_save_settings" value="1">
	<input type="hidden" name="sitechat_tab" value="settings">

	<?php
	$providers        = SiteChat_AI_Provider::providers();
	$cur_provider     = $settings['sitechat_chat_provider'] ?: 'gemini';
	$cur_model        = $settings['sitechat_chat_model']    ?: 'gemini-2.0-flash';
	$cur_combined     = $cur_provider . '::' . $cur_model;
	$cur_embed        = $settings['sitechat_embed_provider'] ?: 'gemini';
	?>

	<div class="sitechat-card">
		<div class="sitechat-card-header"><h2><?php esc_html_e( 'AI Configuration', 'sitechat-ai' ); ?></h2></div>
		<div class="sitechat-card-body">
			<table class="form-table">

				<!-- ── Indexing (embedding) provider ── -->
				<tr>
					<th><label for="sitechat_embed_provider"><?php esc_html_e( 'Indexing Provider', 'sitechat-ai' ); ?></label></th>
					<td>
						<select name="sitechat_embed_provider" id="sitechat_embed_provider">
							<option value="gemini" <?php selected( $cur_embed, 'gemini' ); ?>>
								<?php esc_html_e( 'Gemini (Free) — text-embedding-004', 'sitechat-ai' ); ?>
							</option>
							<option value="openai" <?php selected( $cur_embed, 'openai' ); ?>>
								<?php esc_html_e( 'OpenAI — text-embedding-3-small', 'sitechat-ai' ); ?>
							</option>
						</select>
						<p class="description" id="sitechat-embed-provider-hint" style="margin:6px 0 0;"></p>
						<p class="description" style="margin:4px 0 0;color:#b45309;" id="sitechat-embed-reindex-warn" style="display:none;">
							<?php esc_html_e( '⚠ Changing the indexing provider clears your existing index. You must re-index all content after saving.', 'sitechat-ai' ); ?>
						</p>
					</td>
				</tr>

				<!-- ── Gemini API key ── -->
				<tr id="sitechat-gemini-key-row" <?php if ( $cur_embed !== 'gemini' && $cur_provider !== 'gemini' ) : ?>style="display:none"<?php endif; ?>>
					<th><label for="sitechat_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'sitechat-ai' ); ?></label></th>
					<td>
						<div class="sitechat-api-key-row">
							<input type="password" id="sitechat_gemini_api_key" name="sitechat_gemini_api_key"
							       value="<?php echo esc_attr( $settings['sitechat_gemini_api_key'] ); ?>"
							       class="large-text" autocomplete="off">
							<button type="button" id="sitechat-toggle-key" class="button"><?php esc_html_e( 'Show', 'sitechat-ai' ); ?></button>
							<button type="button" id="sitechat-validate-key" class="button button-secondary">
								<?php esc_html_e( 'Validate Key', 'sitechat-ai' ); ?>
							</button>
						</div>
						<p class="description" id="sitechat-gemini-key-desc">
							<?php printf(
								esc_html__( 'Free key at %s (no credit card needed).', 'sitechat-ai' ),
								'<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>'
							); ?>
						</p>
						<details class="sitechat-api-guide" style="margin-top:8px;">
							<summary style="cursor:pointer;font-weight:600;color:#2563eb;"><?php esc_html_e( 'How to get a free Gemini API key — step by step', 'sitechat-ai' ); ?></summary>
							<ol style="margin:10px 0 0 18px;line-height:1.8;">
								<li><?php printf( esc_html__( 'Go to %s (no credit card required).', 'sitechat-ai' ), '<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a>' ); ?></li>
								<li><?php esc_html_e( 'Sign in with your Google account.', 'sitechat-ai' ); ?></li>
								<li><?php esc_html_e( 'Click the blue "Create API key" button.', 'sitechat-ai' ); ?></li>
								<li><?php esc_html_e( 'Choose "Create API key in new project".', 'sitechat-ai' ); ?></li>
								<li><?php esc_html_e( 'Copy the generated key (starts with "AIza…").', 'sitechat-ai' ); ?></li>
								<li><?php esc_html_e( 'Paste it above and click "Validate Key".', 'sitechat-ai' ); ?></li>
							</ol>
						</details>
						<div id="sitechat-test-result" style="margin-top:8px;"></div>
					</td>
				</tr>

				<!-- ── Enable chatbot ── -->
				<tr>
					<th><?php esc_html_e( 'Enable Chatbot', 'sitechat-ai' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sitechat_enabled" value="1"
							       <?php checked( $settings['sitechat_enabled'], '1' ); ?>>
							<?php esc_html_e( 'Enable the chatbot on the frontend', 'sitechat-ai' ); ?>
						</label>
					</td>
				</tr>

				<!-- ── Combined provider + model select ── -->
				<tr>
					<th><label for="sitechat_chat_model_combined"><?php esc_html_e( 'Chat Answer Model', 'sitechat-ai' ); ?></label></th>
					<td>
						<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
							<select name="sitechat_chat_model_combined" id="sitechat_chat_model_combined" style="min-width:320px;">
								<?php foreach ( $providers as $pid => $pcfg ) : ?>
								<optgroup label="<?php echo esc_attr( $pcfg['label'] . ( $pcfg['has_free'] ? ' ✦ Free' : '' ) ); ?>">
									<?php foreach ( $pcfg['models'] as $mid => $mcfg ) : ?>
									<option value="<?php echo esc_attr( $pid . '::' . $mid ); ?>"
									        data-provider="<?php echo esc_attr( $pid ); ?>"
									        <?php selected( $cur_combined, $pid . '::' . $mid ); ?>>
										<?php echo esc_html( $mcfg['label'] . ( $mcfg['free'] ? ' ⚡' : '' ) ); ?>
									</option>
									<?php endforeach; ?>
								</optgroup>
								<?php endforeach; ?>
							</select>
							<button type="button" id="sitechat-test-chat-provider" class="button button-secondary">
								<?php esc_html_e( 'Test Connection', 'sitechat-ai' ); ?>
							</button>
						</div>
						<div id="sitechat-chat-provider-result" style="font-size:13px;margin-bottom:4px;min-height:20px;"></div>
						<p class="description" style="margin:0;">
							<?php esc_html_e( 'All providers in one list. Gemini always handles content indexing — this setting only affects chat answers.', 'sitechat-ai' ); ?>
						</p>
						<p class="description" id="sitechat-provider-key-hint" style="margin:4px 0 0;"></p>
					</td>
				</tr>

				<!-- ── Per-provider API key rows (shown/hidden by JS) ── -->
				<?php foreach ( $providers as $pid => $pcfg ) : ?>
				<?php if ( $pid === 'gemini' || ! $pcfg['needs_key'] || ! $pcfg['key_option'] ) : continue; endif; ?>
				<tr class="sitechat-provider-key-row"
				    data-provider="<?php echo esc_attr( $pid ); ?>"
				    <?php if ( $cur_provider !== $pid ) : ?>style="display:none"<?php endif; ?>>
					<th>
						<label for="sitechat_key_<?php echo esc_attr( $pid ); ?>">
							<?php printf( esc_html__( '%s API Key', 'sitechat-ai' ), esc_html( $pcfg['label'] ) ); ?>
						</label>
					</th>
					<td>
						<input type="password"
						       id="sitechat_key_<?php echo esc_attr( $pid ); ?>"
						       name="<?php echo esc_attr( $pcfg['key_option'] ); ?>"
						       value="<?php echo esc_attr( (string) get_option( $pcfg['key_option'], '' ) ); ?>"
						       class="large-text" autocomplete="off">
						<p class="description">
							<?php printf(
								esc_html__( 'Get your key at %s', 'sitechat-ai' ),
								'<a href="' . esc_url( $pcfg['key_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $pcfg['key_url'] ) . '</a>'
							); ?>
						</p>
					</td>
				</tr>
				<?php endforeach; ?>

				<!-- ── Ollama URL (shown only for Ollama) ── -->
				<tr id="sitechat-ollama-url-row" <?php if ( $cur_provider !== 'ollama' ) : ?>style="display:none"<?php endif; ?>>
					<th><label for="sitechat_ollama_base_url"><?php esc_html_e( 'Ollama Server URL', 'sitechat-ai' ); ?></label></th>
					<td>
						<input type="text" id="sitechat_ollama_base_url" name="sitechat_ollama_base_url"
						       value="<?php echo esc_attr( $settings['sitechat_ollama_base_url'] ); ?>"
						       class="regular-text" placeholder="http://localhost:11434">
						<p class="description">
							<?php esc_html_e( 'Base URL of your Ollama instance. No API key needed.', 'sitechat-ai' ); ?>
							<a href="https://ollama.com/" target="_blank" rel="noopener">ollama.com</a>
						</p>
					</td>
				</tr>

			</table>
		</div>
	</div>

	<div class="sitechat-card">
		<div class="sitechat-card-header"><h2><?php esc_html_e( 'Indexing Settings', 'sitechat-ai' ); ?></h2></div>
		<div class="sitechat-card-body">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Auto-Index on Save', 'sitechat-ai' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sitechat_auto_index" value="1"
							       <?php checked( $settings['sitechat_auto_index'], '1' ); ?>>
							<?php esc_html_e( 'Automatically re-index posts when they are saved/updated', 'sitechat-ai' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Post Types to Index', 'sitechat-ai' ); ?></th>
					<td>
						<?php
						$index_post_types = (array) $settings['sitechat_index_post_types'];
						foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $pt ) :
						?>
						<label style="display:block;margin-bottom:6px;">
							<input type="checkbox" name="sitechat_index_post_types[]"
							       value="<?php echo esc_attr( $pt->name ); ?>"
							       <?php checked( in_array( $pt->name, $index_post_types, true ) ); ?>>
							<?php echo esc_html( $pt->label ); ?>
							<code><?php echo esc_html( $pt->name ); ?></code>
						</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th><label for="sitechat_chunk_size"><?php esc_html_e( 'Chunk Size (chars)', 'sitechat-ai' ); ?></label></th>
					<td>
						<input type="number" id="sitechat_chunk_size" name="sitechat_chunk_size"
						       value="<?php echo esc_attr( $settings['sitechat_chunk_size'] ); ?>"
						       min="500" max="5000" class="small-text">
						<p class="description"><?php esc_html_e( 'Characters per content chunk. Recommended: 1000–2000.', 'sitechat-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="sitechat_chunk_overlap"><?php esc_html_e( 'Chunk Overlap (chars)', 'sitechat-ai' ); ?></label></th>
					<td>
						<input type="number" id="sitechat_chunk_overlap" name="sitechat_chunk_overlap"
						       value="<?php echo esc_attr( $settings['sitechat_chunk_overlap'] ); ?>"
						       min="0" max="500" class="small-text">
						<p class="description"><?php esc_html_e( 'Overlap between adjacent chunks for context continuity.', 'sitechat-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="sitechat_max_chunks_per_doc"><?php esc_html_e( 'Max Chunks per Document', 'sitechat-ai' ); ?></label></th>
					<td>
						<input type="number" id="sitechat_max_chunks_per_doc" name="sitechat_max_chunks_per_doc"
						       value="<?php echo esc_attr( $settings['sitechat_max_chunks_per_doc'] ); ?>"
						       min="1" max="100" class="small-text">
					</td>
				</tr>
			</table>
		</div>
	</div>

	<div class="sitechat-card">
		<div class="sitechat-card-header"><h2><?php esc_html_e( 'Chat Settings', 'sitechat-ai' ); ?></h2></div>
		<div class="sitechat-card-body">
			<table class="form-table">
				<tr>
					<th><label for="sitechat_chat_max_history"><?php esc_html_e( 'Chat History Turns', 'sitechat-ai' ); ?></label></th>
					<td>
						<input type="number" id="sitechat_chat_max_history" name="sitechat_chat_max_history"
						       value="<?php echo esc_attr( $settings['sitechat_chat_max_history'] ); ?>"
						       min="1" max="20" class="small-text">
						<p class="description"><?php esc_html_e( 'Number of previous message pairs to include as context.', 'sitechat-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="sitechat_rate_limit"><?php esc_html_e( 'Rate Limit (per minute)', 'sitechat-ai' ); ?></label></th>
					<td>
						<input type="number" id="sitechat_rate_limit" name="sitechat_rate_limit"
						       value="<?php echo esc_attr( $settings['sitechat_rate_limit'] ); ?>"
						       min="1" max="100" class="small-text">
						<p class="description"><?php esc_html_e( 'Max queries per IP per minute to prevent abuse.', 'sitechat-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="sitechat_system_prompt"><?php esc_html_e( 'System Prompt', 'sitechat-ai' ); ?></label></th>
					<td>
						<textarea id="sitechat_system_prompt" name="sitechat_system_prompt"
						          rows="6" class="large-text"><?php echo esc_textarea( $settings['sitechat_system_prompt'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Instructions given to the AI before each conversation. Use {site_name} and {site_url} as placeholders.', 'sitechat-ai' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<?php submit_button( __( 'Save Settings', 'sitechat-ai' ) ); ?>
</form>

<div class="sitechat-card sitechat-card--danger">
	<div class="sitechat-card-header">
		<h2><?php esc_html_e( 'Danger Zone', 'sitechat-ai' ); ?></h2>
	</div>
	<div class="sitechat-card-body">
		<div class="sitechat-danger-row">
			<div class="sitechat-danger-info">
				<strong><?php esc_html_e( 'Reset Settings', 'sitechat-ai' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Reset all plugin settings to their default values. Your indexed content is preserved.', 'sitechat-ai' ); ?></p>
			</div>
			<button id="sitechat-reset-settings" class="button sitechat-btn-danger">
				<?php esc_html_e( 'Reset to Defaults', 'sitechat-ai' ); ?>
			</button>
		</div>
		<div class="sitechat-danger-row">
			<div class="sitechat-danger-info">
				<strong><?php esc_html_e( 'Clear Index', 'sitechat-ai' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Delete all indexed documents and chunks. Settings are preserved. You can re-index at any time.', 'sitechat-ai' ); ?></p>
			</div>
			<button id="sitechat-clear-index" class="button sitechat-btn-danger">
				<?php esc_html_e( 'Clear All Index Data', 'sitechat-ai' ); ?>
			</button>
		</div>
		<div class="sitechat-danger-row sitechat-danger-row--severe">
			<div class="sitechat-danger-info">
				<strong><?php esc_html_e( 'Delete Everything', 'sitechat-ai' ); ?></strong>
				<p class="description"><?php esc_html_e( 'Permanently delete all indexed content, chat logs, and plugin settings. This cannot be undone.', 'sitechat-ai' ); ?></p>
			</div>
			<button id="sitechat-delete-all" class="button sitechat-btn-danger-severe">
				<?php esc_html_e( 'Delete All Data', 'sitechat-ai' ); ?>
			</button>
		</div>
	</div>
</div>
</div>
