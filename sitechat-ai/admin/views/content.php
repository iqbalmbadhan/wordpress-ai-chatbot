<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="sitechat-tab-content">

	<div class="sitechat-stats-grid sitechat-stats-grid--4">
		<div class="sitechat-stat-card">
			<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
			<span class="sitechat-stat-label"><?php esc_html_e( 'Total Documents', 'sitechat-ai' ); ?></span>
		</div>
		<div class="sitechat-stat-card sitechat-stat-card--green">
			<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $indexed ) ); ?></span>
			<span class="sitechat-stat-label"><?php esc_html_e( 'Indexed', 'sitechat-ai' ); ?></span>
		</div>
		<div class="sitechat-stat-card sitechat-stat-card--yellow">
			<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $pending ) ); ?></span>
			<span class="sitechat-stat-label"><?php esc_html_e( 'Pending', 'sitechat-ai' ); ?></span>
		</div>
		<div class="sitechat-stat-card sitechat-stat-card--red">
			<span class="sitechat-stat-value"><?php echo esc_html( number_format_i18n( $failed ) ); ?></span>
			<span class="sitechat-stat-label"><?php esc_html_e( 'Failed', 'sitechat-ai' ); ?></span>
		</div>
	</div>

	<div class="sitechat-card">
		<div class="sitechat-card-header sitechat-card-header--flex">
			<h2><?php esc_html_e( 'Index Management', 'sitechat-ai' ); ?></h2>
			<div class="sitechat-card-actions">
				<button id="sitechat-index-all" class="button button-primary">
					<?php esc_html_e( 'Re-index All', 'sitechat-ai' ); ?>
				</button>
				<button id="sitechat-clear-index" class="button button-secondary sitechat-btn-danger">
					<?php esc_html_e( 'Clear Index', 'sitechat-ai' ); ?>
				</button>
			</div>
		</div>
		<div class="sitechat-card-body">

			<div class="sitechat-section-title"><?php esc_html_e( 'Post Types to Index', 'sitechat-ai' ); ?></div>
			<div class="sitechat-checkboxes">
				<?php foreach ( $available_post_types as $pt ) : ?>
				<label class="sitechat-checkbox-label">
					<input type="checkbox"
					       name="sitechat_post_types[]"
					       value="<?php echo esc_attr( $pt->name ); ?>"
					       <?php checked( in_array( $pt->name, $post_types, true ) ); ?>
					       class="sitechat-post-type-toggle">
					<?php echo esc_html( $pt->label ); ?>
					<code class="sitechat-post-type-code"><?php echo esc_html( $pt->name ); ?></code>
				</label>
				<?php endforeach; ?>
			</div>

		</div>
	</div>

	<div class="sitechat-card">
		<div class="sitechat-card-header">
			<h2><?php esc_html_e( 'Indexed Documents', 'sitechat-ai' ); ?></h2>
		</div>
		<div class="sitechat-card-body">
			<?php if ( empty( $documents ) ) : ?>
				<p class="sitechat-empty"><?php esc_html_e( 'No documents indexed yet. Click "Re-index All" to start.', 'sitechat-ai' ); ?></p>
			<?php else : ?>
			<table class="wp-list-table widefat striped sitechat-content-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Type', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Words', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Chunks', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Status', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Indexed At', 'sitechat-ai' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'sitechat-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $documents as $doc ) : ?>
					<tr data-doc-id="<?php echo esc_attr( $doc->id ); ?>">
						<td>
							<a href="<?php echo esc_url( $doc->url ); ?>" target="_blank">
								<?php echo esc_html( $doc->title ); ?>
							</a>
						</td>
						<td><code><?php echo esc_html( $doc->content_type ); ?></code></td>
						<td><?php echo esc_html( number_format_i18n( $doc->word_count ) ); ?></td>
						<td><?php echo esc_html( $doc->chunk_count ); ?></td>
						<td>
							<span class="sitechat-badge sitechat-badge--<?php echo esc_attr( $doc->status === 'indexed' ? 'green' : ( $doc->status === 'failed' ? 'red' : 'yellow' ) ); ?>">
								<?php echo esc_html( ucfirst( $doc->status ) ); ?>
							</span>
							<?php if ( $doc->error_message ) : ?>
								<span class="sitechat-error-hint" title="<?php echo esc_attr( $doc->error_message ); ?>">&#9432;</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $doc->indexed_at ?: '—' ); ?></td>
						<td>
							<button class="button button-small sitechat-reindex-doc"
							        data-post-id="<?php echo esc_attr( $doc->post_id ); ?>">
								<?php esc_html_e( 'Re-index', 'sitechat-ai' ); ?>
							</button>
							<button class="button button-small sitechat-remove-doc"
							        data-doc-id="<?php echo esc_attr( $doc->id ); ?>">
								<?php esc_html_e( 'Remove', 'sitechat-ai' ); ?>
							</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
	</div>

</div>
