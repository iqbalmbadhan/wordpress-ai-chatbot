<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="sitechat-tab-content">

	<?php if ( ! $api_key ) : ?>
	<div class="sitechat-notice sitechat-notice--warning">
		<strong><?php esc_html_e( 'Setup Required:', 'sitechat-ai' ); ?></strong>
		<?php
		printf(
			/* translators: %s: Settings page link */
			esc_html__( 'Please add your Gemini API key in %s to get started.', 'sitechat-ai' ),
			'<a href="' . esc_url( admin_url( 'admin.php?page=sitechat-ai&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'sitechat-ai' ) . '</a>'
		);
		?>
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
			<div class="sitechat-card-header">
				<h2><?php esc_html_e( 'Quick Actions', 'sitechat-ai' ); ?></h2>
			</div>
			<div class="sitechat-card-body">
				<button id="sitechat-index-all" class="button button-primary">
					<?php esc_html_e( 'Index All Content', 'sitechat-ai' ); ?>
				</button>
				<p class="description">
					<?php
					printf(
						/* translators: %s: date/time */
						esc_html__( 'Last full index: %s', 'sitechat-ai' ),
						$last_index ? esc_html( $last_index ) : esc_html__( 'Never', 'sitechat-ai' )
					);
					?>
				</p>
				<hr>
				<div id="sitechat-index-progress" style="display:none;">
					<div class="sitechat-progress-bar"><div class="sitechat-progress-fill"></div></div>
					<p class="sitechat-progress-text"></p>
				</div>
			</div>
		</div>

		<div class="sitechat-card">
			<div class="sitechat-card-header">
				<h2><?php esc_html_e( 'Status', 'sitechat-ai' ); ?></h2>
			</div>
			<div class="sitechat-card-body">
				<table class="sitechat-status-table">
					<tr>
						<td><?php esc_html_e( 'Plugin Status', 'sitechat-ai' ); ?></td>
						<td>
							<?php if ( $enabled ) : ?>
								<span class="sitechat-badge sitechat-badge--green"><?php esc_html_e( 'Active', 'sitechat-ai' ); ?></span>
							<?php else : ?>
								<span class="sitechat-badge sitechat-badge--gray"><?php esc_html_e( 'Disabled', 'sitechat-ai' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'API Key', 'sitechat-ai' ); ?></td>
						<td>
							<?php if ( $api_key ) : ?>
								<span class="sitechat-badge sitechat-badge--green"><?php esc_html_e( 'Configured', 'sitechat-ai' ); ?></span>
							<?php else : ?>
								<span class="sitechat-badge sitechat-badge--red"><?php esc_html_e( 'Not Set', 'sitechat-ai' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Display Mode', 'sitechat-ai' ); ?></td>
						<td><code><?php echo esc_html( get_option( 'sitechat_display_mode', 'bubble' ) ); ?></code></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Helpful Responses', 'sitechat-ai' ); ?></td>
						<td><?php echo esc_html( $analytics['helpful'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Avg. Response Time', 'sitechat-ai' ); ?></td>
						<td><?php echo esc_html( round( $analytics['avg_time'] ) ); ?> ms</td>
					</tr>
				</table>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $analytics['top_queries'] ) ) : ?>
	<div class="sitechat-card">
		<div class="sitechat-card-header">
			<h2><?php esc_html_e( 'Top Questions', 'sitechat-ai' ); ?></h2>
		</div>
		<div class="sitechat-card-body">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Question', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Count', 'sitechat-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $analytics['top_queries'] as $query ) : ?>
					<tr>
						<td><?php echo esc_html( $query['user_message'] ); ?></td>
						<td><?php echo esc_html( $query['count'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

</div>
