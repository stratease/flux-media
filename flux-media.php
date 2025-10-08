<?php
/**
 * Plugin Name: Flux Media
 * Plugin URI: https://github.com/your-org/flux-media
 * Description: Advanced image and video optimization plugin for WordPress. Converts images to WebP/AVIF and videos to AV1/WebM with high-quality settings.
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flux-media
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 *
 * @package FluxMedia
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'FLUX_MEDIA_VERSION', '1.0.0' );
define( 'FLUX_MEDIA_PLUGIN_FILE', __FILE__ );
define( 'FLUX_MEDIA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLUX_MEDIA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLUX_MEDIA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check PHP version compatibility.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', 'flux_media_php_version_notice' );
	return;
}

/**
 * Display PHP version compatibility notice.
 *
 * @since 1.0.0
 */
function flux_media_php_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Current PHP version, 2: Required PHP version */
				esc_html__( 'Flux Media requires PHP %2$s or higher. You are running PHP %1$s.', 'flux-media' ),
				PHP_VERSION,
				'7.4'
			);
			?>
		</p>
	</div>
	<?php
}

// Load Composer autoloader.
if ( file_exists( FLUX_MEDIA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once FLUX_MEDIA_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action( 'admin_notices', 'flux_media_composer_notice' );
	return;
}

/**
 * Display Composer dependencies notice.
 *
 * @since 1.0.0
 */
function flux_media_composer_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'Flux Media requires Composer dependencies. Please run "composer install" in the plugin directory.', 'flux-media' ); ?>
		</p>
	</div>
	<?php
}

// Initialize the plugin.
add_action( 'plugins_loaded', 'flux_media_init' );

/**
 * Initialize the Flux Media plugin.
 *
 * @since 1.0.0
 */
function flux_media_init() {
	// Load text domain for internationalization.
	load_plugin_textdomain( 'flux-media', false, dirname( FLUX_MEDIA_PLUGIN_BASENAME ) . '/languages' );

	// Initialize the main plugin class.
	$flux_media = new FluxMedia\Core\Plugin();
	$flux_media->init();
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, 'flux_media_activate' );
register_deactivation_hook( __FILE__, 'flux_media_deactivate' );

/**
 * Plugin activation handler.
 *
 * @since 1.0.0
 */
function flux_media_activate() {
	// Create necessary database tables.
	FluxMedia\Core\Database::create_tables();
	
	// Set default options.
	FluxMedia\Core\Options::set_defaults();
	
	// Schedule cleanup cron job.
	wp_schedule_event( time(), 'daily', 'flux_media_cleanup' );
}

/**
 * Plugin deactivation handler.
 *
 * @since 1.0.0
 */
function flux_media_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'flux_media_cleanup' );
}
