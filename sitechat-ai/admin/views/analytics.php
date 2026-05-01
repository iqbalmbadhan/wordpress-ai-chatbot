<?php if ( ! defined( 'ABSPATH' ) ) exit;
$period = (int) ( $_GET['period'] ?? 30 );
if ( ! in_array( $period, [ 1, 7, 30, 90 ], true ) ) $period = 30;
?>

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

	<div class="sitechat-card">
		<div class="sitechat-card-header sitechat-card-header--flex">
			<h2><?php esc_html_e( 'Query Volume', 'sitechat-ai' ); ?></h2>
			<div class="sitechat-period-btns">
				<?php foreach ( [ 1 => __( 'Today', 'sitechat-ai' ), 7 => '7d', 30 => '30d', 90 => '90d' ] as $days => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'period', $days ) ); ?>"
				   class="button button-small<?php echo $period === $days ? ' button-primary' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="sitechat-card-body">
			<div class="sitechat-chart-container">
				<canvas id="sitechat-queries-chart"></canvas>
			</div>
		</div>
	</div>

	<?php if ( ! empty( $summary['top_queries'] ) ) : ?>
	<div class="sitechat-card">
		<div class="sitechat-card-header"><h2><?php esc_html_e( 'Top Questions', 'sitechat-ai' ); ?></h2></div>
		<div class="sitechat-card-body sitechat-card-body--flush">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Question', 'sitechat-ai' ); ?></th>
						<th style="width:80px;text-align:right;"><?php esc_html_e( 'Count', 'sitechat-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $summary['top_queries'] as $q ) : ?>
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

	<div class="sitechat-card">
		<div class="sitechat-card-header sitechat-card-header--flex">
			<h2><?php esc_html_e( 'Recent Conversations', 'sitechat-ai' ); ?></h2>
			<div class="sitechat-card-actions">
				<button id="sitechat-export-logs" class="button button-secondary">
					⬇ <?php esc_html_e( 'Export CSV', 'sitechat-ai' ); ?>
				</button>
				<div class="sitechat-clear-logs-row">
					<input type="number" id="sitechat-clear-days" value="90" min="1" max="3650" class="small-text" style="width:60px;">
					<label for="sitechat-clear-days"><?php esc_html_e( 'days', 'sitechat-ai' ); ?></label>
					<button id="sitechat-clear-logs" class="button sitechat-btn-danger">
						<?php esc_html_e( 'Clear Old Logs', 'sitechat-ai' ); ?>
					</button>
				</div>
			</div>
		</div>
		<div class="sitechat-card-body sitechat-card-body--flush">
			<?php if ( empty( $logs ) ) : ?>
				<p class="sitechat-empty"><?php esc_html_e( 'No conversations yet.', 'sitechat-ai' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat sitechat-logs-table">
				<thead>
					<tr>
						<th style="width:30px;"></th>
						<th><?php esc_html_e( 'Date', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Question', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Answer Preview', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Similarity', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Time', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Feedback', 'sitechat-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
					<tr class="sitechat-log-row">
						<td>
							<button class="sitechat-expand-btn sitechat-log-expand"
							        data-answer="<?php echo esc_attr( $log->bot_response ); ?>"
							        aria-label="<?php esc_attr_e( 'Expand', 'sitechat-ai' ); ?>">▶</button>
						</td>
						<td style="white-space:nowrap;font-size:12px;"><?php echo esc_html( $log->created_at ); ?></td>
						<td><?php echo esc_html( wp_trim_words( $log->user_message, 12 ) ); ?></td>
						<td class="sitechat-log-preview"><?php echo esc_html( wp_trim_words( $log->bot_response, 15 ) ); ?></td>
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
					<tr class="sitechat-log-detail-row" style="display:none;">
						<td colspan="7" class="sitechat-log-detail"></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>

</div>
