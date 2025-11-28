<?php
/**
 * Plugin Name: Flux Media Optimizer by Flux Plugins
 * Plugin URI: https://fluxplugins.com/media-optimizer
 * Description: One-click image (AVIF & WebP) and video optimization for WordPress.
 * Version: 2.0.1
 * Author: Flux Plugins
 * Author URI: https://fluxplugins.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flux-media-optimizer
 * Domain Path: /languages
 * Requires at least: 6.2
 * Tested up to: 6.8
 * Requires PHP: 8.0
 *
 * @package FluxMedia
 * @since 1.0.0
 */

use FluxMedia\App\Services\FFmpegAutoloader;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '2.0.1' );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_FILE', __FILE__ );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check PHP version compatibility.
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', 'flux_media_optimizer_php_version_notice' );
	return;
}

/**
 * Display PHP version compatibility notice.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_php_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Current PHP version, 2: Required PHP version */
				esc_html__( 'Flux Media Optimizer requires PHP %2$s or higher. You are running PHP %1$s.', 'flux-media-optimizer' ),
				PHP_VERSION,
				'7.4'
			);
			?>
		</p>
	</div>
	<?php
}

// Load Composer autoloader.
if ( file_exists( FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php' )
	&& file_exists( FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor-prefixed/autoload.php' ) ) {
	require_once FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php';
	require_once FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor-prefixed/autoload.php';
} else {
	add_action( 'admin_notices', 'flux_media_optimizer_composer_notice' );
	return;
}

// Initialize custom FFmpeg autoloader.
FFmpegAutoloader::init();

/**
 * Display Composer dependencies notice.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_composer_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'Flux Media Optimizer requires Composer dependencies. Please run "composer install" in the plugin directory.', 'flux-media-optimizer' ); ?>
		</p>
	</div>
	<?php
}

// Initialize the plugin.
add_action( 'plugins_loaded', 'flux_media_optimizer_init' );

// Handle activation redirect.
add_action( 'admin_init', 'flux_media_optimizer_activation_redirect' );

/**
 * Initialize the Flux Media Optimizer plugin.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_init() {
	// Initialize the main plugin class.
	$flux_media_optimizer = new FluxMedia\App\Plugin();
	$flux_media_optimizer->init();

	// Register WP-CLI commands if WP-CLI is available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'flux-media-optimizer', 'FluxMedia\App\Console\Commands\FluxMediaCommand' );
	}
}

/**
 * Handle activation redirect to admin page.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_activation_redirect() {
	// Only redirect if transient is set and user has proper capabilities
	if ( get_transient( 'flux_media_optimizer_activation_redirect' ) && current_user_can( 'manage_options' ) ) {
		// Delete the transient
		delete_transient( 'flux_media_optimizer_activation_redirect' );
		
		// Redirect to admin page
		wp_redirect( admin_url( 'admin.php?page=flux-media-optimizer' ) );
		exit;
	}
}

// Activation and deactivation hooks.
register_activation_hook( __FILE__, 'flux_media_optimizer_activate' );
register_deactivation_hook( __FILE__, 'flux_media_optimizer_deactivate' );
register_uninstall_hook( __FILE__, 'flux_media_optimizer_uninstall' );

/**
 * Plugin activation handler.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_activate() {
	// Create database tables
	FluxMedia\App\Services\Database::create_tables();
	
	// Initialize settings with defaults
	$settings = new FluxMedia\App\Services\Settings();
	$settings->initialize_defaults();
	
	// Schedule cleanup cron job.
	if ( ! wp_next_scheduled( 'flux_media_optimizer_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'flux_media_optimizer_cleanup' );
	}
	
	// Set transient to redirect to admin page after activation
	set_transient( 'flux_media_optimizer_activation_redirect', true, 60 );
	
	// TODO: Initialize SaaS API integration with license key validation
	// This will be implemented when the SaaS service is available
}

/**
 * Plugin deactivation handler.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'flux_media_optimizer_cleanup' );
	wp_clear_scheduled_hook( 'flux_media_optimizer_bulk_conversion' );

	// Note: We don't drop tables on deactivation to preserve data
	// Tables will only be dropped on uninstall
}

/**
 * Plugin uninstall handler.
 *
 * @since 2.0.1
 */
function flux_media_optimizer_uninstall() {
	global $wpdb;

	// Initialize WordPress filesystem.
	WP_Filesystem();

	// Remove custom database tables.
	$tables = [
		$wpdb->prefix . 'flux_media_optimizer_conversions',
		$wpdb->prefix . 'flux_media_optimizer_logs',
		$wpdb->prefix . 'flux_media_optimizer_settings',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %s", $table ) );
	}

	// Remove plugin options.
	$options = [
		'flux_media_optimizer_settings',
		'flux_media_optimizer_version',
		'flux_media_optimizer_activation_redirect',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
		delete_site_option( $option );
	}

	// Remove post meta for all attachments.
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_flux_media_optimizer_%'"
	);

	// Remove converted files from uploads directory using WordPress filesystem.
	$upload_dir = wp_upload_dir();
	$flux_media_optimizer_dir = $upload_dir['basedir'] . '/flux-media-optimizer-converted';

	if ( is_dir( $flux_media_optimizer_dir ) ) {
		// Use WordPress filesystem to remove directory and all contents.
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->is_dir( $flux_media_optimizer_dir ) ) {
			$wp_filesystem->rmdir( $flux_media_optimizer_dir, true );
		} else {
			// Fallback: Remove files individually using wp_delete_file().
			$files = glob( $flux_media_optimizer_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
		}
	}

	// Clear any scheduled cron jobs.
	wp_clear_scheduled_hook( 'flux_media_optimizer_cleanup' );
	wp_clear_scheduled_hook( 'flux_media_optimizer_bulk_conversion' );

	// Remove any transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_flux_media_optimizer_%' OR option_name LIKE '_transient_timeout_flux_media_optimizer_%'"
	);
}
