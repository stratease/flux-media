<?php
/**
 * Admin controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\App\Services\Settings;
use FluxMedia\FluxPlugins\Common\Services\MenuService;

/**
 * Handles WordPress admin page registration and management.
 *
 * @since 0.1.0
 */
class AdminController {

	/**
	 * Settings instance.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 0.1.0
	 * @since 4.0.0 Register menu during init (before menu.php loads) to ensure page is registered before access check.
	 */
	public function init() {
		// Register menu during init (before menu.php loads) to ensure page is registered before WordPress checks access.
		// menu.php is loaded at line 163 of admin.php, which is BEFORE admin_init fires at line 180.
		// We must register the page before menu.php loads, so we use init hook with is_admin() check.
		// Use priority 1 to ensure Media Optimizer is registered very early.
		if ( is_admin() ) {
			add_action( 'init', [ $this, 'register_menu' ], 1 );
		}
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Register admin menu pages.
	 *
	 * Called during init (before menu.php loads) to ensure page is registered before WordPress checks access.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	public function register_menu() {
		// Register plugin-specific submenu page using MenuService.
		// Placement 1 makes this the primary menu item (first submenu under "Flux Suite").
		$menu_service = MenuService::get_instance();
		$menu_service->register_submenu_page(
			'flux-media-optimizer',
			__( 'Media Optimizer', 'flux-media-optimizer' ),
			[ $this, 'render_main_page' ],
			'manage_options',
			1 // Placement: 1 = first submenu item under "Flux Suite".
		);

		// Note: Plugin registration in Flux Suite overview is now handled centrally
		// in MenuService::init_plugin_registry() for marketing purposes only.
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 0.1.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'flux-media-optimizer' ) === false ) {
			return;
		}

		// Get script URL based on debug mode
		$script_url = $this->get_script_url();

		// Enqueue the main admin script
		wp_enqueue_script(
			'flux-media-optimizer-admin',
			$script_url,
			[ 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-i18n' ],
			FLUX_MEDIA_OPTIMIZER_VERSION,
			true
		);

		// Get current user email
		$current_user = wp_get_current_user();
		$user_email = $current_user->ID ? $current_user->user_email : '';

		// Localize script with WordPress data
		wp_localize_script( 'flux-media-optimizer-admin', 'fluxMediaAdmin', [
			'apiUrl' => rest_url( 'flux-media-optimizer/v1/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => admin_url(),
			'pluginUrl' => FLUX_MEDIA_OPTIMIZER_PLUGIN_URL,
			'userEmail' => $user_email,
		] );

		// Enqueue WordPress admin styles
		wp_enqueue_style( 'wp-components' );
	}


	/**
	 * Get script URL based on debug mode.
	 *
	 * @since 0.1.0
	 * @return string Script URL.
	 */
	private function get_script_url() {
		// Use webpack dev server if debug mode is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return 'http://localhost:3000/admin.bundle.js';
		}

		// Use built asset
		return FLUX_MEDIA_OPTIMIZER_PLUGIN_URL . 'assets/js/dist/admin.bundle.js';
	}

	/**
	 * Render the main admin page.
	 *
	 * @since 0.1.0
	 */
	public function render_main_page() {
		$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
		?>
		<div class="wrap">
			<span class="wp-header-end"></span>
			<div id="flux-media-optimizer-app">
			<?php if ( $is_debug ) : ?>
				<div class="notice notice-warning" style="margin: 20px 0; padding: 15px;">
					<p><strong><?php esc_html_e( 'Development Mode Active', 'flux-media-optimizer' ); ?></strong></p>
					<p><?php esc_html_e( 'Development mode is enabled. The admin interface is attempting to load the React development bundle from:', 'flux-media-optimizer' ); ?></p>
					<p><code><?php echo esc_html( $this->get_script_url() ); ?></code></p>
					<p><?php esc_html_e( 'This assumes you are testing on a localhost WordPress environment with the webpack dev server running on port 3000.', 'flux-media-optimizer' ); ?></p>
					<p><strong><?php esc_html_e( 'To use the development build:', 'flux-media-optimizer' ); ?></strong></p>
					<ol>
						<li><?php esc_html_e( 'Navigate to the plugin directory in your terminal', 'flux-media-optimizer' ); ?></li>
						<li><?php esc_html_e( 'Run "npm run start" to start the webpack dev server', 'flux-media-optimizer' ); ?></li>
						<li><?php esc_html_e( 'Ensure the dev server is running on http://localhost:3000', 'flux-media-optimizer' ); ?></li>
						<li><?php esc_html_e( 'Refresh this page to load the development build', 'flux-media-optimizer' ); ?></li>
					</ol>
				</div>
			<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
