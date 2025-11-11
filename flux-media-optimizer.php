<?php
/**
 * Plugin Name: Flux Media
 * Plugin URI: https://wordpress.org/plugins/flux-media/
 * Description: Compress images to AVIF/WebP for 50-70% faster loads. Boost Core Web Vitals, improve SEO rankings, and enhance user experience with automatic image optimization.
 * Version: 0.1.0
 * Author: Flux Media
 * Author URI: https://fluxplugins.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flux-media
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 *
 * @package FluxMedia
 * @since 0.1.0
 */

use FluxMedia\App\Services\FFmpegAutoloader;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'FLUX_MEDIA_VERSION', '0.1.0' );
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
 * @since 0.1.0
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
if ( file_exists( FLUX_MEDIA_PLUGIN_DIR . 'vendor/autoload.php' )
	&& file_exists( FLUX_MEDIA_PLUGIN_DIR . 'vendor-prefixed/autoload.php' ) ) {
	require_once FLUX_MEDIA_PLUGIN_DIR . 'vendor/autoload.php';
	require_once FLUX_MEDIA_PLUGIN_DIR . 'vendor-prefixed/autoload.php';
} else {
	add_action( 'admin_notices', 'flux_media_composer_notice' );
	return;
}

// Initialize custom FFmpeg autoloader.
FFmpegAutoloader::init();

/**
 * Display Composer dependencies notice.
 *
 * @since 0.1.0
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

// Handle activation redirect.
add_action( 'admin_init', 'flux_media_activation_redirect' );

/**
 * Initialize the Flux Media plugin.
 *
 * @since 0.1.0
 */
function flux_media_init() {
	// Initialize the main plugin class.
	$flux_media = new FluxMedia\App\Plugin();
	$flux_media->init();

	// Register WP-CLI commands if WP-CLI is available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'flux-media', 'FluxMedia\App\Console\Commands\FluxMediaCommand' );
	}
}

/**
 * Handle activation redirect to admin page.
 *
 * @since 0.1.0
 */
function flux_media_activation_redirect() {
	// Only redirect if transient is set and user has proper capabilities
	if ( get_transient( 'flux_media_activation_redirect' ) && current_user_can( 'manage_options' ) ) {
		// Delete the transient
		delete_transient( 'flux_media_activation_redirect' );
		
		// Redirect to admin page
		wp_redirect( admin_url( 'admin.php?page=flux-media' ) );
		exit;
	}
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, 'flux_media_activate' );
register_deactivation_hook( __FILE__, 'flux_media_deactivate' );
register_uninstall_hook( __FILE__, 'flux_media_uninstall' );

/**
 * Plugin activation handler.
 *
 * @since 0.1.0
 */
function flux_media_activate() {
	// Create database tables
	FluxMedia\App\Services\Database::create_tables();
	
	// Initialize settings with defaults
	$settings = new FluxMedia\App\Services\Settings();
	$settings->initialize_defaults();
	
	// Schedule cleanup cron job.
	wp_schedule_event( time(), 'daily', 'flux_media_cleanup' );
	
	// Set transient to redirect to admin page after activation
	set_transient( 'flux_media_activation_redirect', true, 60 );
	
	// TODO: Initialize SaaS API integration with license key validation
	// This will be implemented when the SaaS service is available
}

/**
 * Plugin deactivation handler.
 *
 * @since 0.1.0
 */
function flux_media_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'flux_media_cleanup' );
	wp_clear_scheduled_hook( 'flux_media_bulk_conversion' );

	// Note: We don't drop tables on deactivation to preserve data
	// Tables will only be dropped on uninstall
}

/**
 * Plugin uninstall handler.
 *
 * @since 0.1.0
 */
function flux_media_uninstall() {	
	// Clear scheduled events
	wp_clear_scheduled_hook( 'flux_media_cleanup' );
}
