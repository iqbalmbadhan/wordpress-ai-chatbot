<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteChat_Loader {

	private SiteChat_DB         $db;
	private SiteChat_Indexer    $indexer;
	private SiteChat_Embeddings $embeddings;
	private SiteChat_Search     $search;
	private SiteChat_Chat       $chat;

	public function run(): void {
		// Instantiate core services once; share by reference through closures
		$this->db         = new SiteChat_DB();
		$this->embeddings = new SiteChat_Embeddings();
		$this->indexer    = new SiteChat_Indexer( $this->db, $this->embeddings );
		$this->search     = new SiteChat_Search( $this->db, $this->embeddings );
		$this->chat       = new SiteChat_Chat( $this->search, $this->embeddings );
		$cron             = new SiteChat_Cron( $this->indexer, $this->db );

		// ── Admin ──────────────────────────────────────────────────────────────
		if ( is_admin() ) {
			$admin      = new SiteChat_Admin( $this->db, $this->indexer );
			$admin_ajax = new SiteChat_Admin_Ajax( $this->db, $this->indexer, $this->chat );
			$admin->init();
			$admin_ajax->init();
		}

		// ── Frontend ───────────────────────────────────────────────────────────
		( new SiteChat_REST_API( $this->chat, $this->db ) )->init();
		( new SiteChat_Frontend() )->init();
		( new SiteChat_Shortcode() )->init();
		( new SiteChat_Block() )->init();

		// ── Cron ───────────────────────────────────────────────────────────────
		$cron->init();

		// ── Content sync hooks ─────────────────────────────────────────────────
		$this->register_content_sync_hooks();

		// ── Admin UX hooks ─────────────────────────────────────────────────────
		$this->register_admin_ui_hooks();

		// ── Rewrite / template hooks ───────────────────────────────────────────
		add_action( 'init',              [ $this, 'add_rewrite_rules' ] );
		add_filter( 'query_vars',        [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_full_page_template' ] );
	}

	// ── Phase 3.1: Content sync hooks ─────────────────────────────────────────

	private function register_content_sync_hooks(): void {
		// Deferred single-event cron (10s delay batches rapid saves)
		add_action( 'sitechat_deferred_index', [ $this, 'run_deferred_index' ] );

		// Publish / status-change handling
		add_action( 'transition_post_status', [ $this, 'on_transition_post_status' ], 20, 3 );

		// Hard-delete: remove from index before the post row is gone
		add_action( 'before_delete_post', [ $this, 'on_before_delete_post' ] );

		// Trash: treat as unpublish
		add_action( 'wp_trash_post', [ $this, 'on_trash_post' ] );

		// Taxonomy: re-index posts that have the changed term (if auto-index is on)
		if ( get_option( 'sitechat_auto_index' ) === '1' ) {
			add_action( 'edited_term',  [ $this, 'on_term_change' ], 10, 3 );
			add_action( 'created_term', [ $this, 'on_term_change' ], 10, 3 );
			add_action( 'delete_term',  [ $this, 'on_term_change' ], 10, 3 );
		}
	}

	/**
	 * Handle publish ↔ other-status transitions.
	 *
	 * - publish → *          : remove from index
	 * - * → publish          : queue a deferred index (10s delay to batch rapid saves)
	 * - * → draft/trash/etc  : remove from index (belt-and-suspenders with trash/delete hooks)
	 */
	public function on_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( ! $this->is_indexable_type( $post->post_type ) ) {
			return;
		}

		if ( $this->is_excluded( $post->ID ) ) {
			return;
		}

		if ( $new_status === 'publish' ) {
			if ( get_option( 'sitechat_auto_index' ) === '1' ) {
				$this->schedule_deferred_index( $post->ID );
			}
			return;
		}

		// Moved away from publish → remove from index
		if ( $old_status === 'publish' ) {
			$this->indexer->delete_post_index( $post->ID );
		}
	}

	/**
	 * Schedule a single deferred index event 10 seconds from now.
	 * Cancels any previously-scheduled event for the same post to prevent stacking.
	 */
	private function schedule_deferred_index( int $post_id ): void {
		// Unschedule any existing deferred index for this post
		$timestamp = wp_next_scheduled( 'sitechat_deferred_index', [ $post_id ] );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'sitechat_deferred_index', [ $post_id ] );
		}

		wp_schedule_single_event( time() + 10, 'sitechat_deferred_index', [ $post_id ] );
	}

	/** Cron callback for deferred indexing. */
	public function run_deferred_index( int $post_id ): void {
		if ( get_option( 'sitechat_enabled' ) !== '1' && get_option( 'sitechat_auto_index' ) !== '1' ) {
			return;
		}
		$this->indexer->index_post( $post_id );
	}

	public function on_before_delete_post( int $post_id ): void {
		if ( $this->is_indexable_type( get_post_type( $post_id ) ) ) {
			$this->indexer->delete_post_index( $post_id );
		}
	}

	public function on_trash_post( int $post_id ): void {
		if ( $this->is_indexable_type( get_post_type( $post_id ) ) ) {
			$this->indexer->delete_post_index( $post_id );
		}
	}

	/**
	 * When a term is created, edited, or deleted, queue a deferred re-index
	 * for all published posts in that taxonomy.
	 */
	public function on_term_change( int $term_id, int $tt_id, string $taxonomy ): void {
		$post_types = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );

		// Find post types that use this taxonomy
		$affected_types = array_filter(
			$post_types,
			fn( $pt ) => in_array( $taxonomy, get_object_taxonomies( $pt ), true )
		);

		if ( empty( $affected_types ) ) {
			return;
		}

		$post_ids = get_posts( [
			'post_type'      => array_values( $affected_types ),
			'post_status'    => 'publish',
			'posts_per_page' => 200, // cap to avoid overwhelming the scheduler
			'fields'         => 'ids',
			'tax_query'      => [ [ // phpcs:ignore WordPress.DB.SlowDBQuery
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_id,
			] ],
		] );

		foreach ( $post_ids as $pid ) {
			$this->schedule_deferred_index( (int) $pid );
		}
	}

	// ── Phase 3.2: Admin UI hooks ──────────────────────────────────────────────

	private function register_admin_ui_hooks(): void {
		// "Settings" link on the Plugins list page
		$plugin_basename = plugin_basename( SITECHAT_PLUGIN_FILE );
		add_filter( "plugin_action_links_{$plugin_basename}", [ $this, 'add_plugin_action_links' ] );

		// Admin bar quick-access node
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_node' ], 90 );
	}

	/** Prepend a "Settings" link in the plugin row on /wp-admin/plugins.php. */
	public function add_plugin_action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=sitechat-ai&tab=settings' ) ),
			esc_html__( 'Settings', 'sitechat-ai' )
		);
		$dashboard = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=sitechat-ai' ) ),
			esc_html__( 'Dashboard', 'sitechat-ai' )
		);
		array_unshift( $links, $settings, $dashboard );
		return $links;
	}

	/** Add SiteChat AI node to the WP admin bar. */
	public function add_admin_bar_node( \WP_Admin_Bar $bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$enabled = get_option( 'sitechat_enabled' ) === '1';

		$bar->add_node( [
			'id'    => 'sitechat-ai',
			'title' => '<span class="ab-icon dashicons dashicons-format-chat" style="margin-top:3px;"></span>'
				. esc_html__( 'SiteChat AI', 'sitechat-ai' )
				. ' <span style="background:' . ( $enabled ? '#22c55e' : '#9ca3af' ) . ';border-radius:99px;padding:1px 7px;font-size:10px;font-weight:700;color:#fff;margin-left:4px;">'
				. ( $enabled ? esc_html__( 'ON', 'sitechat-ai' ) : esc_html__( 'OFF', 'sitechat-ai' ) )
				. '</span>',
			'href'  => admin_url( 'admin.php?page=sitechat-ai' ),
		] );

		$bar->add_node( [
			'parent' => 'sitechat-ai',
			'id'     => 'sitechat-ai-dashboard',
			'title'  => esc_html__( 'Dashboard', 'sitechat-ai' ),
			'href'   => admin_url( 'admin.php?page=sitechat-ai' ),
		] );

		$bar->add_node( [
			'parent' => 'sitechat-ai',
			'id'     => 'sitechat-ai-index',
			'title'  => esc_html__( 'Index Now', 'sitechat-ai' ),
			'href'   => admin_url( 'admin.php?page=sitechat-ai&tab=content' ),
		] );

		$bar->add_node( [
			'parent' => 'sitechat-ai',
			'id'     => 'sitechat-ai-settings',
			'title'  => esc_html__( 'Settings', 'sitechat-ai' ),
			'href'   => admin_url( 'admin.php?page=sitechat-ai&tab=settings' ),
		] );

		$bar->add_node( [
			'parent' => 'sitechat-ai',
			'id'     => 'sitechat-ai-analytics',
			'title'  => esc_html__( 'Analytics', 'sitechat-ai' ),
			'href'   => admin_url( 'admin.php?page=sitechat-ai&tab=analytics' ),
		] );
	}

	// ── Phase 3.3: Frontend / rewrite hooks ───────────────────────────────────

	public function add_rewrite_rules(): void {
		$slug = sanitize_title( get_option( 'sitechat_full_page_slug', 'chat' ) );
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?sitechat_page=1', 'top' );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'sitechat_page';
		return $vars;
	}

	public function handle_full_page_template(): void {
		if ( get_query_var( 'sitechat_page' ) && get_option( 'sitechat_display_mode' ) === 'full_page' ) {
			$template = SITECHAT_PLUGIN_DIR . 'public/templates/full-page-chat.php';
			if ( file_exists( $template ) ) {
				include $template;
				exit;
			}
		}
	}

	// ── Helpers ────────────────────────────────────────────────────────────────

	private function is_indexable_type( string|false $post_type ): bool {
		if ( ! $post_type ) {
			return false;
		}
		$post_types = (array) get_option( 'sitechat_index_post_types', [ 'post', 'page' ] );
		return in_array( $post_type, $post_types, true );
	}

	private function is_excluded( int $post_id ): bool {
		$excluded = (array) get_option( 'sitechat_excluded_ids', [] );
		return in_array( $post_id, $excluded, true );
	}
}
