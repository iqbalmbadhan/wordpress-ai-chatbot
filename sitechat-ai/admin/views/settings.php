<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="sitechat-tab-content">
<form method="post" action="" id="sitechat-settings-form">
	<?php wp_nonce_field( 'sitechat_settings_nonce' ); ?>
	<input type="hidden" name="sitechat_save_settings" value="1">
	<input type="hidden" name="sitechat_tab" value="settings">

	<div class="sitechat-card">
		<div class="sitechat-card-header"><h2><?php esc_html_e( 'Gemini API Configuration', 'sitechat-ai' ); ?></h2></div>
		<div class="sitechat-card-body">
			<table class="form-table">
				<tr>
					<th><label for="sitechat_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'sitechat-ai' ); ?></label></th>
					<td>
						<div class="sitechat-api-key-row">
							<input type="password" id="sitechat_gemini_api_key" name="sitechat_gemini_api_key"
							       value="<?php echo esc_attr( $settings['sitechat_gemini_api_key'] ); ?>"
							       class="large-text" autocomplete="off">
							<button type="button" id="sitechat-toggle-key" class="button"><?php esc_html_e( 'Show', 'sitechat-ai' ); ?></button>
							<button type="button" id="sitechat-test-api" class="button button-secondary"
							        data-nonce="<?php echo esc_attr( wp_create_nonce( 'sitechat_admin_nonce' ) ); ?>">
								<?php esc_html_e( 'Test Connection', 'sitechat-ai' ); ?>
							</button>
						</div>
						<p class="description">
							<?php
							printf(
								/* translators: %s: Google AI Studio link */
								esc_html__( 'Get a free API key from %s. Both text-embedding-004 and gemini-2.0-flash are on the free tier.', 'sitechat-ai' ),
								'<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>'
							);
							?>
						</p>
						<div id="sitechat-test-result" style="margin-top:8px;"></div>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Enable Chatbot', 'sitechat-ai' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sitechat_enabled" value="1"
							       <?php checked( $settings['sitechat_enabled'], '1' ); ?>>
							<?php esc_html_e( 'Enable the chatbot on the frontend', 'sitechat-ai' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Requires a valid API key and at least one indexed document.', 'sitechat-ai' ); ?></p>
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
						$all_types        = get_post_types( [ 'public' => true ], 'objects' );
						foreach ( $all_types as $pt ) :
						?>
						<label style="display:block;margin-bottom:4px;">
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
						<p class="description"><?php esc_html_e( 'Number of characters per content chunk. Recommended: 1000–2000.', 'sitechat-ai' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="sitechat_chunk_overlap"><?php esc_html_e( 'Chunk Overlap (chars)', 'sitechat-ai' ); ?></label></th>
					<td>
						<input type="number" id="sitechat_chunk_overlap" name="sitechat_chunk_overlap"
						       value="<?php echo esc_attr( $settings['sitechat_chunk_overlap'] ); ?>"
						       min="0" max="500" class="small-text">
						<p class="description"><?php esc_html_e( 'Characters of overlap between adjacent chunks for context continuity.', 'sitechat-ai' ); ?></p>
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
						<p class="description"><?php esc_html_e( 'Instructions given to the AI before each conversation. The retrieved content is automatically appended.', 'sitechat-ai' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<?php submit_button( __( 'Save Settings', 'sitechat-ai' ) ); ?>
</form>
</div>
