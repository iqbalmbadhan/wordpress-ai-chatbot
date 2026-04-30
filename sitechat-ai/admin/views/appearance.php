<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="sitechat-tab-content">
<form method="post" action="" id="sitechat-appearance-form">
	<?php wp_nonce_field( 'sitechat_settings_nonce' ); ?>
	<input type="hidden" name="sitechat_save_settings" value="1">
	<input type="hidden" name="sitechat_tab" value="appearance">

	<div class="sitechat-two-col">

		<!-- Left column: settings -->
		<div class="sitechat-col sitechat-col--settings">

			<div class="sitechat-card">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Display Mode', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<div class="sitechat-mode-grid">
						<?php
						$modes = [
							'bubble'    => [ 'label' => __( 'Floating Bubble', 'sitechat-ai' ), 'icon' => '💬', 'desc' => __( 'Corner chat bubble like Intercom', 'sitechat-ai' ) ],
							'slide_in'  => [ 'label' => __( 'Slide-in Panel', 'sitechat-ai' ),  'icon' => '➡️', 'desc' => __( 'Slides in from the side', 'sitechat-ai' ) ],
							'embedded'  => [ 'label' => __( 'Embedded Block', 'sitechat-ai' ),  'icon' => '📄', 'desc' => __( 'Inline via shortcode or block', 'sitechat-ai' ) ],
							'full_page' => [ 'label' => __( 'Full Page', 'sitechat-ai' ),        'icon' => '🖥️', 'desc' => __( 'Dedicated /chat page', 'sitechat-ai' ) ],
						];
						foreach ( $modes as $value => $mode ) :
							$active = $settings['sitechat_display_mode'] === $value;
						?>
						<label class="sitechat-mode-card <?php echo $active ? 'sitechat-mode-card--active' : ''; ?>">
							<input type="radio" name="sitechat_display_mode" value="<?php echo esc_attr( $value ); ?>" <?php checked( $active ); ?>>
							<span class="sitechat-mode-icon"><?php echo $mode['icon']; ?></span>
							<span class="sitechat-mode-label"><?php echo esc_html( $mode['label'] ); ?></span>
							<span class="sitechat-mode-desc"><?php echo esc_html( $mode['desc'] ); ?></span>
						</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="sitechat-card">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Widget Text', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Widget Title', 'sitechat-ai' ); ?></th>
							<td><input type="text" name="sitechat_widget_title" value="<?php echo esc_attr( $settings['sitechat_widget_title'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Bot Name', 'sitechat-ai' ); ?></th>
							<td><input type="text" name="sitechat_bot_name" value="<?php echo esc_attr( $settings['sitechat_bot_name'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Welcome Message', 'sitechat-ai' ); ?></th>
							<td><textarea name="sitechat_welcome_message" rows="3" class="large-text"><?php echo esc_textarea( $settings['sitechat_welcome_message'] ); ?></textarea></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Input Placeholder', 'sitechat-ai' ); ?></th>
							<td><input type="text" name="sitechat_placeholder" value="<?php echo esc_attr( $settings['sitechat_placeholder'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Bot Avatar URL', 'sitechat-ai' ); ?></th>
							<td><input type="url" name="sitechat_bot_avatar" value="<?php echo esc_url( $settings['sitechat_bot_avatar'] ); ?>" class="large-text" placeholder="https://..."></td>
						</tr>
					</table>
				</div>
			</div>

			<div class="sitechat-card">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Colors & Sizing', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Primary Color', 'sitechat-ai' ); ?></th>
							<td>
								<input type="color" name="sitechat_primary_color" value="<?php echo esc_attr( $settings['sitechat_primary_color'] ); ?>">
								<input type="text" id="sitechat_primary_color_text" value="<?php echo esc_attr( $settings['sitechat_primary_color'] ); ?>" class="small-text sitechat-color-text">
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Secondary Color', 'sitechat-ai' ); ?></th>
							<td>
								<input type="color" name="sitechat_secondary_color" value="<?php echo esc_attr( $settings['sitechat_secondary_color'] ); ?>">
								<input type="text" id="sitechat_secondary_color_text" value="<?php echo esc_attr( $settings['sitechat_secondary_color'] ); ?>" class="small-text sitechat-color-text">
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Bubble Position', 'sitechat-ai' ); ?></th>
							<td>
								<select name="sitechat_bubble_position">
									<option value="bottom-right" <?php selected( $settings['sitechat_bubble_position'], 'bottom-right' ); ?>><?php esc_html_e( 'Bottom Right', 'sitechat-ai' ); ?></option>
									<option value="bottom-left"  <?php selected( $settings['sitechat_bubble_position'], 'bottom-left' ); ?>><?php esc_html_e( 'Bottom Left', 'sitechat-ai' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Bubble Size (px)', 'sitechat-ai' ); ?></th>
							<td><input type="number" name="sitechat_bubble_size" value="<?php echo esc_attr( $settings['sitechat_bubble_size'] ); ?>" min="40" max="120" class="small-text"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Widget Width (px)', 'sitechat-ai' ); ?></th>
							<td><input type="number" name="sitechat_widget_width" value="<?php echo esc_attr( $settings['sitechat_widget_width'] ); ?>" min="280" max="800" class="small-text"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Widget Height (px)', 'sitechat-ai' ); ?></th>
							<td><input type="number" name="sitechat_widget_height" value="<?php echo esc_attr( $settings['sitechat_widget_height'] ); ?>" min="300" max="1200" class="small-text"></td>
						</tr>
					</table>
				</div>
			</div>

			<div class="sitechat-card">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Slide-in Settings', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Slide From', 'sitechat-ai' ); ?></th>
							<td>
								<select name="sitechat_slidein_side">
									<option value="right" <?php selected( $settings['sitechat_slidein_side'], 'right' ); ?>><?php esc_html_e( 'Right', 'sitechat-ai' ); ?></option>
									<option value="left"  <?php selected( $settings['sitechat_slidein_side'], 'left' ); ?>><?php esc_html_e( 'Left', 'sitechat-ai' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Panel Width (px)', 'sitechat-ai' ); ?></th>
							<td><input type="number" name="sitechat_slidein_width" value="<?php echo esc_attr( $settings['sitechat_slidein_width'] ); ?>" min="280" max="600" class="small-text"></td>
						</tr>
					</table>
				</div>
			</div>

			<div class="sitechat-card">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Full Page Settings', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Page Slug', 'sitechat-ai' ); ?></th>
							<td>
								<code><?php echo esc_html( home_url( '/' ) ); ?></code>
								<input type="text" name="sitechat_full_page_slug" value="<?php echo esc_attr( $settings['sitechat_full_page_slug'] ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Page Title', 'sitechat-ai' ); ?></th>
							<td><input type="text" name="sitechat_full_page_title" value="<?php echo esc_attr( $settings['sitechat_full_page_title'] ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Layout', 'sitechat-ai' ); ?></th>
							<td>
								<select name="sitechat_full_page_layout">
									<option value="centered"   <?php selected( $settings['sitechat_full_page_layout'], 'centered' ); ?>><?php esc_html_e( 'Centered', 'sitechat-ai' ); ?></option>
									<option value="full_width" <?php selected( $settings['sitechat_full_page_layout'], 'full_width' ); ?>><?php esc_html_e( 'Full Width', 'sitechat-ai' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="sitechat-card">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Display Rules', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Show Widget', 'sitechat-ai' ); ?></th>
							<td>
								<select name="sitechat_show_on">
									<option value="all"      <?php selected( $settings['sitechat_show_on'], 'all' ); ?>><?php esc_html_e( 'All Pages', 'sitechat-ai' ); ?></option>
									<option value="specific" <?php selected( $settings['sitechat_show_on'], 'specific' ); ?>><?php esc_html_e( 'Specific Pages Only', 'sitechat-ai' ); ?></option>
									<option value="exclude"  <?php selected( $settings['sitechat_show_on'], 'exclude' ); ?>><?php esc_html_e( 'All Except...', 'sitechat-ai' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="sitechat-card">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Misc', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<label class="sitechat-toggle-label">
						<input type="checkbox" name="sitechat_show_sources" value="1" <?php checked( $settings['sitechat_show_sources'], '1' ); ?>>
						<?php esc_html_e( 'Show source links in answers', 'sitechat-ai' ); ?>
					</label>
					<label class="sitechat-toggle-label">
						<input type="checkbox" name="sitechat_show_powered_by" value="1" <?php checked( $settings['sitechat_show_powered_by'], '1' ); ?>>
						<?php esc_html_e( 'Show "Powered by SiteChat AI" footer', 'sitechat-ai' ); ?>
					</label>
					<hr>
					<label><?php esc_html_e( 'Custom CSS', 'sitechat-ai' ); ?></label>
					<textarea name="sitechat_custom_css" rows="6" class="large-text code"><?php echo esc_textarea( $settings['sitechat_custom_css'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Add custom CSS to override widget styles.', 'sitechat-ai' ); ?></p>
				</div>
			</div>

		</div>

		<!-- Right column: preview -->
		<div class="sitechat-col sitechat-col--preview">
			<div class="sitechat-card sitechat-card--sticky">
				<div class="sitechat-card-header"><h2><?php esc_html_e( 'Live Preview', 'sitechat-ai' ); ?></h2></div>
				<div class="sitechat-card-body">
					<div id="sitechat-preview-container">
						<div id="sitechat-preview-widget">
							<div class="sc-preview-header">
								<div class="sc-preview-avatar"></div>
								<span class="sc-preview-title"><?php echo esc_html( $settings['sitechat_widget_title'] ); ?></span>
								<button class="sc-preview-close">✕</button>
							</div>
							<div class="sc-preview-messages">
								<div class="sc-preview-message sc-preview-message--bot">
									<p><?php echo esc_html( $settings['sitechat_welcome_message'] ); ?></p>
								</div>
								<div class="sc-preview-message sc-preview-message--user">
									<p><?php esc_html_e( 'What services do you offer?', 'sitechat-ai' ); ?></p>
								</div>
								<div class="sc-preview-message sc-preview-message--bot">
									<p><?php esc_html_e( 'We offer a wide range of services tailored to your needs…', 'sitechat-ai' ); ?></p>
								</div>
							</div>
							<div class="sc-preview-input">
								<input type="text" placeholder="<?php echo esc_attr( $settings['sitechat_placeholder'] ); ?>" disabled>
								<button>➤</button>
							</div>
						</div>
						<div id="sitechat-preview-bubble">💬</div>
					</div>
				</div>
			</div>
		</div>

	</div>

	<?php submit_button( __( 'Save Appearance Settings', 'sitechat-ai' ) ); ?>
</form>
</div>
