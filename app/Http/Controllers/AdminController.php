<?php
/**
 * Admin controller for Flux Media plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\App\Services\Settings;

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
	 */
	public function init() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Add admin menu pages.
	 *
	 * @since 0.1.0
	 */
	public function add_admin_menu() {
		// Main menu page
		add_menu_page(
			__( 'Flux Media', 'flux-media' ),
			__( 'Flux Media', 'flux-media' ),
			'manage_options',
			'flux-media',
			[ $this, 'render_main_page' ],
			'dashicons-images-alt2',
			30
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 0.1.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'flux-media' ) === false ) {
			return;
		}

		// Get script URL based on debug mode
		$script_url = $this->get_script_url();

		// Enqueue the main admin script
		wp_enqueue_script(
			'flux-media-admin',
			$script_url,
			[ 'wp-api-fetch', 'wp-element', 'wp-components', 'wp-i18n' ],
			FLUX_MEDIA_VERSION,
			true
		);

		// Get current user email
		$current_user = wp_get_current_user();
		$user_email = $current_user->ID ? $current_user->user_email : '';

		// Localize script with WordPress data
		wp_localize_script( 'flux-media-admin', 'fluxMediaAdmin', [
			'apiUrl' => rest_url( 'flux-media/v1/' ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => admin_url(),
			'pluginUrl' => FLUX_MEDIA_PLUGIN_URL,
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
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG || defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return 'http://localhost:3000/admin.bundle.js';
		}

		// Use built asset
		return FLUX_MEDIA_PLUGIN_URL . 'assets/js/dist/admin.bundle.js';
	}

	/**
	 * Render the main admin page.
	 *
	 * @since 0.1.0
	 */
	public function render_main_page() {
		?>
		<div class="wrap">
			<div id="flux-media-app">
			</div>
		</div>
		<?php
	}
}
