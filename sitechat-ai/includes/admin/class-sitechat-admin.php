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
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

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

		add_submenu_page( 'sitechat-ai', __( 'Dashboard', 'sitechat-ai' ), __( 'Dashboard', 'sitechat-ai' ), 'manage_options', 'sitechat-ai', [ $this, 'render_page' ] );
		add_submenu_page( 'sitechat-ai', __( 'Content', 'sitechat-ai' ), __( 'Content', 'sitechat-ai' ), 'manage_options', 'sitechat-ai&tab=content', [ $this, 'render_page' ] );
		add_submenu_page( 'sitechat-ai', __( 'Appearance', 'sitechat-ai' ), __( 'Appearance', 'sitechat-ai' ), 'manage_options', 'sitechat-ai&tab=appearance', [ $this, 'render_page' ] );
		add_submenu_page( 'sitechat-ai', __( 'Analytics', 'sitechat-ai' ), __( 'Analytics', 'sitechat-ai' ), 'manage_options', 'sitechat-ai&tab=analytics', [ $this, 'render_page' ] );
		add_submenu_page( 'sitechat-ai', __( 'Settings', 'sitechat-ai' ), __( 'Settings', 'sitechat-ai' ), 'manage_options', 'sitechat-ai&tab=settings', [ $this, 'render_page' ] );
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'sitechat-ai' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'sitechat-admin',
			SITECHAT_PLUGIN_URL . 'admin/css/sitechat-admin.css',
			[],
			SITECHAT_VERSION
		);
		wp_enqueue_script(
			'sitechat-admin',
			SITECHAT_PLUGIN_URL . 'admin/js/sitechat-admin.js',
			[],
			SITECHAT_VERSION,
			true
		);
		wp_localize_script( 'sitechat-admin', 'sitechatAdmin', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sitechat_admin_nonce' ),
			'strings' => [
				'indexing'      => __( 'Indexing content…', 'sitechat-ai' ),
				'indexComplete' => __( 'Indexing complete!', 'sitechat-ai' ),
				'indexFailed'   => __( 'Indexing failed. Please check your API key.', 'sitechat-ai' ),
				'confirm_clear' => __( 'Are you sure you want to clear all indexed data? This cannot be undone.', 'sitechat-ai' ),
				'saving'        => __( 'Saving…', 'sitechat-ai' ),
				'saved'         => __( 'Saved!', 'sitechat-ai' ),
			],
		] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'sitechat-ai' ) );
		}

		$tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );

		// Handle form submissions
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
	}

	private function render_header(): void {
		?>
		<div class="sitechat-header">
			<div class="sitechat-header-logo">
				<svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
					<rect width="32" height="32" rx="8" fill="#2563eb"/>
					<path d="M8 10C8 8.9 8.9 8 10 8H22C23.1 8 24 8.9 24 10V18C24 19.1 23.1 20 22 20H18L14 24V20H10C8.9 20 8 19.1 8 18V10Z" fill="white"/>
					<circle cx="12" cy="14" r="1.5" fill="#2563eb"/>
					<circle cx="16" cy="14" r="1.5" fill="#2563eb"/>
					<circle cx="20" cy="14" r="1.5" fill="#2563eb"/>
				</svg>
				<h1><?php esc_html_e( 'SiteChat AI', 'sitechat-ai' ); ?></h1>
			</div>
			<div class="sitechat-header-status">
				<?php if ( get_option( 'sitechat_enabled' ) === '1' ) : ?>
					<span class="sitechat-status sitechat-status--active"><?php esc_html_e( 'Active', 'sitechat-ai' ); ?></span>
				<?php else : ?>
					<span class="sitechat-status sitechat-status--inactive"><?php esc_html_e( 'Inactive', 'sitechat-ai' ); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_tabs( string $current ): void {
		$tabs = [
			'dashboard'  => __( 'Dashboard', 'sitechat-ai' ),
			'content'    => __( 'Content', 'sitechat-ai' ),
			'appearance' => __( 'Appearance', 'sitechat-ai' ),
			'analytics'  => __( 'Analytics', 'sitechat-ai' ),
			'settings'   => __( 'Settings', 'sitechat-ai' ),
		];
		echo '<nav class="sitechat-tabs nav-tab-wrapper">';
		foreach ( $tabs as $slug => $label ) {
			$url   = admin_url( 'admin.php?page=sitechat-ai&tab=' . $slug );
			$class = $current === $slug ? 'nav-tab nav-tab-active' : 'nav-tab';
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
		}
		echo '</nav>';
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

	public function admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'sitechat' ) === false ) {
			return;
		}
		$api_key = get_option( 'sitechat_gemini_api_key', '' );
		if ( ! $api_key ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: link to settings */
				esc_html__( 'SiteChat AI requires a Gemini API key. %s to configure it.', 'sitechat-ai' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=sitechat-ai&tab=settings' ) ) . '">' . esc_html__( 'Click here', 'sitechat-ai' ) . '</a>'
			);
			echo '</p></div>';
		}
	}
}
