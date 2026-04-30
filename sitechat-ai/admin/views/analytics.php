<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="sitechat-tab-content">

	<div class="sitechat-stats-grid">
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-format-chat"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $summary['total'] ) ); ?></span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Total Queries', 'sitechat-ai' ); ?></span>
			</div>
		</div>
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-calendar-alt"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $summary['today'] ) ); ?></span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Today', 'sitechat-ai' ); ?></span>
			</div>
		</div>
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-yes-alt"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $summary['helpful'] ) ); ?></span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Marked Helpful', 'sitechat-ai' ); ?></span>
			</div>
		</div>
		<div class="sitechat-stat-card">
			<div class="sitechat-stat-icon dashicons dashicons-clock"></div>
			<div class="sitechat-stat-body">
				<span class="sitechat-stat-value"><?php echo esc_html( round( $summary['avg_time'] ) ); ?> ms</span>
				<span class="sitechat-stat-label"><?php esc_html_e( 'Avg Response Time', 'sitechat-ai' ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $summary['top_queries'] ) ) : ?>
	<div class="sitechat-card">
		<div class="sitechat-card-header">
			<h2><?php esc_html_e( 'Top Questions', 'sitechat-ai' ); ?></h2>
		</div>
		<div class="sitechat-card-body">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Question', 'sitechat-ai' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Count', 'sitechat-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $summary['top_queries'] as $q ) : ?>
					<tr>
						<td><?php echo esc_html( $q['user_message'] ); ?></td>
						<td><?php echo esc_html( $q['count'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<div class="sitechat-card">
		<div class="sitechat-card-header sitechat-card-header--flex">
			<h2><?php esc_html_e( 'Recent Conversations', 'sitechat-ai' ); ?></h2>
		</div>
		<div class="sitechat-card-body">
			<?php if ( empty( $logs ) ) : ?>
				<p class="sitechat-empty"><?php esc_html_e( 'No conversations yet.', 'sitechat-ai' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat striped sitechat-logs-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Question', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Answer (excerpt)', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Similarity', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Time', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Feedback', 'sitechat-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td style="white-space:nowrap;"><?php echo esc_html( $log->created_at ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $log->user_message, 12 ) ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $log->bot_response, 15 ) ); ?></td>
						<td><?php echo $log->similarity_avg !== null ? esc_html( round( (float) $log->similarity_avg, 2 ) ) : '—'; ?></td>
						<td><?php echo $log->response_time_ms ? esc_html( $log->response_time_ms . ' ms' ) : '—'; ?></td>
						<td>
							<?php if ( $log->feedback === 'helpful' ) : ?>
								<span class="sitechat-badge sitechat-badge--green">👍</span>
							<?php elseif ( $log->feedback === 'not_helpful' ) : ?>
								<span class="sitechat-badge sitechat-badge--red">👎</span>
							<?php else : ?>
								<span class="sitechat-badge sitechat-badge--gray">—</span>
							<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>

</div>
