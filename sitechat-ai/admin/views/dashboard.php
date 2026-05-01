<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="sitechat-tab-content">

	<?php if ( ! $api_key ) : ?>
	<div class="sitechat-notice sitechat-notice--warning">
		<strong><?php esc_html_e( 'Setup Required:', 'sitechat-ai' ); ?></strong>
		<?php printf(
			esc_html__( 'Please add your Gemini API key in %s to get started.', 'sitechat-ai' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=sitechat-ai&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'sitechat-ai' ) . '</a>'
		); ?>
	</div>
	<?php endif; ?>

	<div class="sitechat-stats-grid">
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-admin-page"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $total_indexed ) ); ?></span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Pages Indexed', 'sitechat-ai' ); ?></span>
			</div>
		</div>
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-editor-ul"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $total_chunks ) ); ?></span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Content Chunks', 'sitechat-ai' ); ?></span>
			</div>
		</div>
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-format-chat"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $analytics['total'] ) ); ?></span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Total Queries', 'sitechat-ai' ); ?></span>
			</div>
		</div>
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-calendar-alt"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $analytics['today'] ) ); ?></span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Queries Today', 'sitechat-ai' ); ?></span>
			</div>
		</div>
	</div>

	<div class="sitechat-dashboard-grid">

		<div class="sitechat-card">
			<div class="sitechat-card-header"><h2><?php esc_html_e( 'Quick Actions', 'sitechat-ai' ); ?></h2></div>
			<div class="sitechat-card-body">
				<div class="sitechat-quick-actions">
					<button id="sitechat-index-all" class="sitechat-action-btn sitechat-action-btn--primary">
						<span class="dashicons dashicons-update"></span>
						<span class="sitechat-action-btn-label"><?php esc_html_e( 'Index All Content', 'sitechat-ai' ); ?></span>
						<span class="sitechat-action-btn-desc"><?php
						printf(
							esc_html__( 'Last: %s', 'sitechat-ai' ),
							$last_index ? esc_html( human_time_diff( strtotime( $last_index ), time() ) . ' ' . __( 'ago', 'sitechat-ai' ) ) : esc_html__( 'Never', 'sitechat-ai' )
						);
						?></span>
					</button>
					<button id="sitechat-open-testchat" class="sitechat-action-btn">
						<span class="dashicons dashicons-format-chat"></span>
						<span class="sitechat-action-btn-label"><?php esc_html_e( 'Test Chatbot', 'sitechat-ai' ); ?></span>
						<span class="sitechat-action-btn-desc"><?php esc_html_e( 'Send a test message', 'sitechat-ai' ); ?></span>
					</button>
					<button id="sitechat-open-embed" class="sitechat-action-btn">
						<span class="dashicons dashicons-editor-code"></span>
						<span class="sitechat-action-btn-label"><?php esc_html_e( 'Embed Code', 'sitechat-ai' ); ?></span>
						<span class="sitechat-action-btn-desc"><?php esc_html_e( 'Shortcode & REST API', 'sitechat-ai' ); ?></span>
					</button>
				</div>
			</div>
		</div>

		<div class="sitechat-card">
			<div class="sitechat-card-header"><h2><?php esc_html_e( 'System Status', 'sitechat-ai' ); ?></h2></div>
			<div class="sitechat-card-body sitechat-card-body--flush">
				<?php
				$next_cron    = wp_next_scheduled( 'sitechat_auto_reindex' );
				$display_mode = get_option( 'sitechat_display_mode', 'bubble' );
				$last_cron    = get_option( 'sitechat_last_cron_result', null );
				$mem_limit    = ini_get( 'memory_limit' );
				$rows = [
					[
						'label' => __( 'Plugin Status', 'sitechat-ai' ),
						'value' => $enabled
							? '<span class="sitechat-badge sitechat-badge--green">' . esc_html__( 'Active', 'sitechat-ai' ) . '</span>'
							: '<span class="sitechat-badge sitechat-badge--gray">' . esc_html__( 'Disabled', 'sitechat-ai' ) . '</span>',
					],
					[
						'label' => __( 'API Key', 'sitechat-ai' ),
						'value' => $api_key
							? '<span class="sitechat-badge sitechat-badge--green">' . esc_html__( 'Configured', 'sitechat-ai' ) . '</span>'
							: '<span class="sitechat-badge sitechat-badge--red">' . esc_html__( 'Not Set', 'sitechat-ai' ) . '</span>',
					],
					[
						'label' => __( 'Display Mode', 'sitechat-ai' ),
						'value' => '<code>' . esc_html( $display_mode ) . '</code>',
					],
					[
						'label' => __( 'Auto Reindex (Cron)', 'sitechat-ai' ),
						'value' => $next_cron
							? esc_html( __( 'Next: ', 'sitechat-ai' ) . human_time_diff( time(), $next_cron ) )
							: '<span class="sitechat-badge sitechat-badge--yellow">' . esc_html__( 'Not Scheduled', 'sitechat-ai' ) . '</span>',
					],
					[
						'label' => __( 'Avg. Response Time', 'sitechat-ai' ),
						'value' => esc_html( round( $analytics['avg_time'] ) . ' ms' ),
					],
					[
						'label' => __( 'PHP Version', 'sitechat-ai' ),
						'value' => esc_html( PHP_VERSION ),
					],
					[
						'label' => __( 'Memory Limit', 'sitechat-ai' ),
						'value' => esc_html( $mem_limit ),
					],
				];
				?>
				<table class="sitechat-status-table">
					<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td><?php echo wp_kses( $row['value'], [ 'span' => [ 'class' => [] ], 'code' => [] ] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>

	</div>

	<?php if ( ! empty( $analytics['top_queries'] ) ) : ?>
	<div class="sitechat-card">
		<div class="sitechat-card-header sitechat-card-header--flex">
			<h2><?php esc_html_e( 'Top Questions (Last 30 Days)', 'sitechat-ai' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sitechat-ai&tab=analytics' ) ); ?>" class="sitechat-header-link">
				<?php esc_html_e( 'View All →', 'sitechat-ai' ); ?>
			</a>
		</div>
		<div class="sitechat-card-body sitechat-card-body--flush">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Question', 'sitechat-ai' ); ?></th>
						<th style="width:80px;text-align:right;"><?php esc_html_e( 'Count', 'sitechat-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $analytics['top_queries'], 0, 8 ) as $q ) : ?>
					<tr>
						<td><?php echo esc_html( $q['user_message'] ); ?></td>
						<td style="text-align:right;">
							<span class="sitechat-badge sitechat-badge--gray"><?php echo esc_html( $q['count'] ); ?></span>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

</div>
