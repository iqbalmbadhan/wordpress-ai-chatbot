<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Admin {

	private SiteChat_DB      $db;
	private SiteChat_Indexer $indexer;

	public function __construct( SiteChat_DB $db, SiteChat_Indexer $indexer ) {
		$this->db      = $db;
		$this->indexer = $indexer;
	}

	public function init(): void {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices',         [ $this, 'admin_notices' ] );
		add_action( 'admin_footer',          [ $this, 'render_modals' ] );
	}

	// ── Menu ──────────────────────────────────────────────────────────────────

	public function register_menu(): void {
		add_menu_page(
			__( 'SiteChat AI', 'sitechat-ai' ),
			__( 'SiteChat AI', 'sitechat-ai' ),
			'manage_options',
			'sitechat-ai',
			[ $this, 'render_page' ],
			'dashicons-format-chat',
			80
		);

		$tabs = [
			'dashboard'  => __( 'Dashboard', 'sitechat-ai' ),
			'content'    => __( 'Content', 'sitechat-ai' ),
			'appearance' => __( 'Appearance', 'sitechat-ai' ),
			'analytics'  => __( 'Analytics', 'sitechat-ai' ),
			'settings'   => __( 'Settings', 'sitechat-ai' ),
		];
		foreach ( $tabs as $slug => $label ) {
			$menu_slug = $slug === 'dashboard' ? 'sitechat-ai' : 'sitechat-ai&tab=' . $slug;
			add_submenu_page( 'sitechat-ai', $label, $label, 'manage_options', $menu_slug, [ $this, 'render_page' ] );
		}
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'sitechat-ai' ) === false ) {
			return;
		}

		$tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );

		// WP built-ins
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		// Chart.js — used on analytics tab
		wp_register_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
			[],
			'4.4.3',
			true
		);

		wp_enqueue_style(
			'sitechat-admin',
			SITECHAT_PLUGIN_URL . 'admin/css/sitechat-admin.css',
			[ 'wp-color-picker' ],
			SITECHAT_VERSION
		);

		wp_enqueue_script(
			'sitechat-admin',
			SITECHAT_PLUGIN_URL . 'admin/js/sitechat-admin.js',
			[ 'jquery', 'wp-color-picker', 'chartjs' ],
			SITECHAT_VERSION,
			true
		);

		// Gather analytics chart data once on the analytics tab
		$chart_data = [];
		if ( $tab === 'analytics' ) {
			$daily = $this->db->get_daily_query_counts( 30 );
			foreach ( $daily as $row ) {
				$chart_data[ $row['date'] ] = (int) $row['count'];
			}
		}

		// Cron status
		$next_cron = wp_next_scheduled( 'sitechat_auto_reindex' );

		wp_localize_script( 'sitechat-admin', 'sitechatAdmin', [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'sitechat_admin_nonce' ),
			'tab'       => $tab,
			'chartData' => $chart_data,
			'nextCron'  => $next_cron ? gmdate( 'Y-m-d H:i:s', $next_cron ) : '',
			'pluginUrl' => SITECHAT_PLUGIN_URL,
			'strings'   => [
				'indexing'         => __( 'Indexing…', 'sitechat-ai' ),
				'indexComplete'    => __( 'Indexing complete!', 'sitechat-ai' ),
				'indexFailed'      => __( 'Indexing failed.', 'sitechat-ai' ),
				'indexCancelled'   => __( 'Indexing cancelled.', 'sitechat-ai' ),
				'saving'           => __( 'Saving…', 'sitechat-ai' ),
				'saved'            => __( 'Saved!', 'sitechat-ai' ),
				'saveFailed'       => __( 'Save failed.', 'sitechat-ai' ),
				'testing'          => __( 'Testing…', 'sitechat-ai' ),
				'validating'       => __( 'Validating…', 'sitechat-ai' ),
				'valid'            => __( '✓ API key is valid!', 'sitechat-ai' ),
				'invalid'          => __( '✗ Invalid API key.', 'sitechat-ai' ),
				'confirmClear'     => __( 'This will permanently delete all indexed data. This cannot be undone.', 'sitechat-ai' ),
				'confirmReset'     => __( 'This will reset all settings to their defaults.', 'sitechat-ai' ),
				'confirmDelete'    => __( 'This will delete ALL data and settings. Type DELETE to confirm.', 'sitechat-ai' ),
				'typeDelete'       => __( 'Type DELETE to confirm:', 'sitechat-ai' ),
				'deleteWord'       => __( 'DELETE', 'sitechat-ai' ),
				'cancelled'        => __( 'Cancelled.', 'sitechat-ai' ),
				'exportReady'      => __( 'CSV export ready.', 'sitechat-ai' ),
				'logsCleared'      => __( 'Old logs deleted.', 'sitechat-ai' ),
				'reindexing'       => __( 'Re-indexing…', 'sitechat-ai' ),
				'excluding'        => __( 'Excluding…', 'sitechat-ai' ),
				'removing'         => __( 'Removing…', 'sitechat-ai' ),
				'done'             => __( 'Done', 'sitechat-ai' ),
				'of'               => __( 'of', 'sitechat-ai' ),
				'sending'          => __( 'Sending…', 'sitechat-ai' ),
				'chatError'        => __( 'Chat error. Check your API key.', 'sitechat-ai' ),
				'selectAvatar'     => __( 'Select Avatar', 'sitechat-ai' ),
				'useAvatar'        => __( 'Use as Avatar', 'sitechat-ai' ),
			],
		] );
	}

	// ── Page renderer ─────────────────────────────────────────────────────────

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sitechat-ai' ) );
		}

		$tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );

		if ( isset( $_POST['sitechat_save_settings'] ) && check_admin_referer( 'sitechat_settings_nonce' ) ) {
			$this->save_settings( $tab );
		}

		echo '<div class="wrap sitechat-admin-wrap">';
		$this->render_header();
		$this->render_tabs( $tab );

		switch ( $tab ) {
			case 'content':
				( new SiteChat_Admin_Content( $this->db ) )->render();
				break;
			case 'appearance':
				( new SiteChat_Admin_Appearance() )->render();
				break;
			case 'analytics':
				( new SiteChat_Admin_Analytics( $this->db ) )->render();
				break;
			case 'settings':
				( new SiteChat_Admin_Settings() )->render();
				break;
			default:
				( new SiteChat_Admin_Dashboard( $this->db ) )->render();
		}

		echo '</div>';
		echo '<div id="sitechat-toast-container"></div>';
	}

	private function render_header(): void {
		$enabled = get_option( 'sitechat_enabled' ) === '1';
		?>
		<div class="sitechat-header">
			<div class="sitechat-header-logo">
				<svg width="34" height="34" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect width="256" height="256" rx="48" fill="#2563eb"/>
					<path d="M56 76C56 63.85 65.85 54 78 54H178C190.15 54 200 63.85 200 76V148C200 160.15 190.15 170 178 170H144L112 202V170H78C65.85 170 56 160.15 56 148V76Z" fill="white"/>
					<circle cx="96" cy="112" r="10" fill="#2563eb"/>
					<circle cx="128" cy="112" r="10" fill="#2563eb"/>
					<circle cx="160" cy="112" r="10" fill="#2563eb"/>
				</svg>
				<div>
					<h1><?php esc_html_e( 'SiteChat AI', 'sitechat-ai' ); ?></h1>
					<span class="sitechat-version">v<?php echo esc_html( SITECHAT_VERSION ); ?></span>
				</div>
			</div>
			<div class="sitechat-header-right">
				<span class="sitechat-status sitechat-status--<?php echo $enabled ? 'active' : 'inactive'; ?>">
					<?php echo $enabled ? esc_html__( 'Active', 'sitechat-ai' ) : esc_html__( 'Inactive', 'sitechat-ai' ); ?>
				</span>
				<a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="sitechat-header-link">
					<?php esc_html_e( 'Get API Key', 'sitechat-ai' ); ?> ↗
				</a>
			</div>
		</div>
		<?php
	}

	private function render_tabs( string $current ): void {
		$tabs = [
			'dashboard'  => [ 'label' => __( 'Dashboard', 'sitechat-ai' ),  'icon' => '⊞' ],
			'content'    => [ 'label' => __( 'Content', 'sitechat-ai' ),    'icon' => '≡' ],
			'appearance' => [ 'label' => __( 'Appearance', 'sitechat-ai' ), 'icon' => '◑' ],
			'analytics'  => [ 'label' => __( 'Analytics', 'sitechat-ai' ),  'icon' => '↗' ],
			'settings'   => [ 'label' => __( 'Settings', 'sitechat-ai' ),   'icon' => '⚙' ],
		];

		echo '<nav class="sitechat-tabs">';
		foreach ( $tabs as $slug => $info ) {
			$url    = admin_url( 'admin.php?page=sitechat-ai&tab=' . $slug );
			$active = $current === $slug ? ' sitechat-tab--active' : '';
			printf(
				'<a href="%s" class="sitechat-tab%s"><span class="sitechat-tab-icon">%s</span>%s</a>',
				esc_url( $url ),
				esc_attr( $active ),
				$info['icon'],
				esc_html( $info['label'] )
			);
		}
		echo '</nav>';
	}

	// ── Shared modals (rendered once in footer) ───────────────────────────────

	public function render_modals(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'sitechat' ) === false ) {
			return;
		}
		?>
		<!-- Confirmation modal -->
		<div id="sitechat-modal-overlay" class="sitechat-modal-overlay" hidden>
			<div class="sitechat-modal" role="dialog" aria-modal="true">
				<div class="sitechat-modal-header">
					<h3 id="sitechat-modal-title"><?php esc_html_e( 'Confirm Action', 'sitechat-ai' ); ?></h3>
					<button class="sitechat-modal-close" aria-label="<?php esc_attr_e( 'Close', 'sitechat-ai' ); ?>">✕</button>
				</div>
				<div class="sitechat-modal-body">
					<p id="sitechat-modal-message"></p>
					<div id="sitechat-modal-input-wrap" hidden>
						<label id="sitechat-modal-input-label" for="sitechat-modal-input"></label>
						<input type="text" id="sitechat-modal-input" class="regular-text" autocomplete="off">
					</div>
				</div>
				<div class="sitechat-modal-footer">
					<button id="sitechat-modal-cancel" class="button button-secondary"><?php esc_html_e( 'Cancel', 'sitechat-ai' ); ?></button>
					<button id="sitechat-modal-confirm" class="button button-primary sitechat-btn-danger"><?php esc_html_e( 'Confirm', 'sitechat-ai' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Indexing progress modal -->
		<div id="sitechat-progress-overlay" class="sitechat-modal-overlay" hidden>
			<div class="sitechat-modal sitechat-modal--progress" role="dialog" aria-modal="true">
				<div class="sitechat-modal-header">
					<h3><?php esc_html_e( 'Indexing Content', 'sitechat-ai' ); ?></h3>
				</div>
				<div class="sitechat-modal-body">
					<div class="sitechat-progress-bar-wrap">
						<div class="sitechat-progress-bar"><div id="sitechat-progress-fill" class="sitechat-progress-fill" style="width:0%"></div></div>
						<span id="sitechat-progress-pct">0%</span>
					</div>
					<p id="sitechat-progress-text" class="sitechat-progress-text"><?php esc_html_e( 'Starting…', 'sitechat-ai' ); ?></p>
					<p id="sitechat-progress-count" class="sitechat-progress-count"></p>
				</div>
				<div class="sitechat-modal-footer">
					<button id="sitechat-cancel-index" class="button button-secondary"><?php esc_html_e( 'Cancel', 'sitechat-ai' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Chunk preview modal -->
		<div id="sitechat-chunks-overlay" class="sitechat-modal-overlay" hidden>
			<div class="sitechat-modal sitechat-modal--wide" role="dialog" aria-modal="true">
				<div class="sitechat-modal-header">
					<h3 id="sitechat-chunks-title"><?php esc_html_e( 'Content Chunks', 'sitechat-ai' ); ?></h3>
					<button class="sitechat-modal-close">✕</button>
				</div>
				<div class="sitechat-modal-body" id="sitechat-chunks-body"></div>
			</div>
		</div>

		<!-- Test chat panel -->
		<div id="sitechat-testchat-overlay" class="sitechat-modal-overlay" hidden>
			<div class="sitechat-modal sitechat-modal--chat" role="dialog" aria-modal="true">
				<div class="sitechat-modal-header">
					<h3><?php esc_html_e( 'Test Chatbot', 'sitechat-ai' ); ?></h3>
					<button class="sitechat-modal-close">✕</button>
				</div>
				<div class="sitechat-modal-body sitechat-testchat-messages" id="sitechat-testchat-messages"></div>
				<div class="sitechat-modal-footer sitechat-testchat-input-row">
					<input type="text" id="sitechat-testchat-input" class="regular-text" placeholder="<?php esc_attr_e( 'Type a test message…', 'sitechat-ai' ); ?>">
					<button id="sitechat-testchat-send" class="button button-primary"><?php esc_html_e( 'Send', 'sitechat-ai' ); ?></button>
				</div>
			</div>
		</div>

		<!-- Embed code modal -->
		<div id="sitechat-embed-overlay" class="sitechat-modal-overlay" hidden>
			<div class="sitechat-modal" role="dialog" aria-modal="true">
				<div class="sitechat-modal-header">
					<h3><?php esc_html_e( 'Embed Codes', 'sitechat-ai' ); ?></h3>
					<button class="sitechat-modal-close">✕</button>
				</div>
				<div class="sitechat-modal-body">
					<p><strong><?php esc_html_e( 'Shortcode (paste into any page/post):', 'sitechat-ai' ); ?></strong></p>
					<div class="sitechat-code-block">
						<code>[sitechat]</code>
						<button class="sitechat-copy-btn" data-copy="[sitechat]"><?php esc_html_e( 'Copy', 'sitechat-ai' ); ?></button>
					</div>
					<p><strong><?php esc_html_e( 'Shortcode with custom height:', 'sitechat-ai' ); ?></strong></p>
					<div class="sitechat-code-block">
						<code>[sitechat height="600" title="Ask us anything"]</code>
						<button class="sitechat-copy-btn" data-copy='[sitechat height="600" title="Ask us anything"]'><?php esc_html_e( 'Copy', 'sitechat-ai' ); ?></button>
					</div>
					<p><strong><?php esc_html_e( 'Gutenberg Block:', 'sitechat-ai' ); ?></strong></p>
					<p class="description"><?php esc_html_e( 'In the block editor, search for "SiteChat AI Chatbot" in the block inserter.', 'sitechat-ai' ); ?></p>
					<p><strong><?php esc_html_e( 'REST API endpoint:', 'sitechat-ai' ); ?></strong></p>
					<div class="sitechat-code-block">
						<code><?php echo esc_html( rest_url( 'sitechat/v1/chat' ) ); ?></code>
						<button class="sitechat-copy-btn" data-copy="<?php echo esc_attr( rest_url( 'sitechat/v1/chat' ) ); ?>"><?php esc_html_e( 'Copy', 'sitechat-ai' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	// ── Notices ───────────────────────────────────────────────────────────────

	public function admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'sitechat' ) === false ) {
			return;
		}
		$api_key = get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $api_key ) {
			echo '<div class="notice notice-warning is-dismissible"><p>';
			printf(
				esc_html__( 'SiteChat AI requires a Gemini API key. %s to configure it.', 'sitechat-ai' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=sitechat-ai&tab=settings' ) ) . '">' . esc_html__( 'Go to Settings', 'sitechat-ai' ) . '</a>'
			);
			echo '</p></div>';
		}
	}

	private function save_settings( string $tab ): void {
		$handler = match ( $tab ) {
			'appearance' => new SiteChat_Admin_Appearance(),
			'settings'   => new SiteChat_Admin_Settings(),
			default      => null,
		};
		if ( $handler && method_exists( $handler, 'save' ) ) {
			$handler->save();
		}
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'sitechat-ai' ) . '</p></div>';
		} );
	}
}
