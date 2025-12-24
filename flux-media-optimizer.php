<?php
/**
 * Plugin Name: Flux Media Optimizer by Flux Plugins
 * Plugin URI: https://fluxplugins.com/media-optimizer
 * Description: One-click image (AVIF & WebP) and video optimization for WordPress.
 * Version: 3.0.0
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
 * Copyright 2025 Flux Plugins
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
define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '3.0.0' );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_FILE', __FILE__ );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_SLUG', 'flux-media-optimizer' );

// Define external service URL constant (can be overridden in wp-config.php).
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL', 'https://api.fluxplugins.com' );
}

// Define external service timeout constant (can be overridden in wp-config.php).
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_TIMEOUT' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_TIMEOUT', 15 );
}

// Check PHP version compatibility.
// @since 3.0.0 Updated PHP version requirement from 7.4 to 8.0.
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
	add_action( 'admin_notices', 'flux_media_optimizer_php_version_notice' );
	return;
}

// Check WordPress version compatibility.
// @since 3.0.0 Added WordPress version requirement check.
global $wp_version;
if ( version_compare( $wp_version, '6.2', '<' ) ) {
	add_action( 'admin_notices', 'flux_media_optimizer_wp_version_notice' );
	return;
}

/**
 * Display PHP version compatibility notice.
 *
 * @since 0.1.0
 * @since 3.0.0 Updated PHP version requirement from 7.4 to 8.0.
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
				'8.0'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Display WordPress version compatibility notice.
 *
 * @since 3.0.0 Added WordPress version requirement check.
 */
function flux_media_optimizer_wp_version_notice() {
	global $wp_version;
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Current WordPress version, 2: Required WordPress version */
				esc_html__( 'Flux Media Optimizer requires WordPress %2$s or higher. You are running WordPress %1$s.', 'flux-media-optimizer' ),
				esc_html( $wp_version ),
				'6.2'
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
 * Load plugin translations.
 *
 * @since 3.0.0
 */
function flux_media_optimizer_load_translations() {
	load_plugin_textdomain(
		'flux-media-optimizer',
		false,
		dirname( plugin_basename( FLUX_MEDIA_OPTIMIZER_PLUGIN_FILE ) ) . '/languages/'
	);
}
add_action( 'init', 'flux_media_optimizer_load_translations' );

/**
 * Initialize the Flux Media Optimizer plugin.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_init() {
	// Generate and store UUID (account_id) if it doesn't exist.
	// This is done on plugin initialization, not on license key activation.
	flux_media_optimizer_ensure_account_id();
	
	// Initialize the main plugin class.
	$flux_media_optimizer = new FluxMedia\App\Plugin();
	$flux_media_optimizer->init();

	// Register WP-CLI commands if WP-CLI is available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'flux-media-optimizer', 'FluxMedia\App\Console\Commands\FluxMediaCommand' );
	}
}

/**
 * Ensure account ID (UUID) exists for this site.
 *
 * Generates and stores UUID on first plugin load if it doesn't exist.
 * This UUID is persistent and never changes, even if license keys change.
 *
 * Privacy & Usage:
 * - UUID is generated locally and stored only in WordPress site options
 * - UUID is used for service identification (matching webhooks, license validation)
 * - UUID is NOT used for user tracking or analytics
 * - UUID is only transmitted to external service when user explicitly enables external service AND provides a license key
 * - UUID is automatically removed on plugin uninstall for privacy compliance
 * - See readme.txt Privacy Policy section for full details
 *
 * @since 3.0.0
 * @return void
 */
function flux_media_optimizer_ensure_account_id() {
	$account_id = get_site_option( 'flux-plugins_account_id', '' );
	
	if ( empty( $account_id ) ) {
		// Generate UUID v4.
		$uuid = flux_media_optimizer_generate_uuid();
		update_site_option( 'flux-plugins_account_id', $uuid );
	}
}

/**
 * Generate a UUID v4.
 *
 * @since 3.0.0
 * @return string UUID v4 string.
 */
function flux_media_optimizer_generate_uuid() {
	// Use WordPress's wp_generate_uuid4() if available (WP 6.1+), otherwise generate manually.
	if ( function_exists( 'wp_generate_uuid4' ) ) {
		return wp_generate_uuid4();
	}
	
	// Fallback UUID v4 generation.
	$data = random_bytes( 16 );
	$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // Set version to 0100.
	$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // Set bits 6-7 to 10.
	return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
}

/**
 * Check if Flux Media Optimizer is activated on the network.
 *
 * @since 3.0.0
 *
 * @return bool True if Flux Media Optimizer is activated on the network.
 */
function flux_media_optimizer_is_active_for_network() {
	static $is;

	if ( isset( $is ) ) {
		return $is;
	}

	if ( ! is_multisite() ) {
		$is = false;
		return $is;
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$is = is_plugin_active_for_network( plugin_basename( FLUX_MEDIA_OPTIMIZER_PLUGIN_FILE ) );

	return $is;
}

/**
 * Handle activation redirect to admin page.
 *
 * This is a one-time redirect that occurs only immediately after plugin activation.
 * The redirect helps users discover the plugin settings page after installation.
 *
 * Safety measures to prevent dashboard hijacking:
 * - Only redirects if transient is set (created only on activation)
 * - Transient expires after 60 seconds (failsafe)
 * - Transient is immediately deleted on redirect (ensures one-time only)
 * - Only redirects users with 'manage_options' capability (admins only)
 * - Redirects to plugin's own settings page (not external site)
 * - Only runs on admin_init hook (not on frontend)
 *
 * @since 0.1.0
 * @since 3.0.0 Added multisite support for activation redirect transients.
 * @return void
 */
function flux_media_optimizer_activation_redirect() {
	// Only redirect in admin area and if transient is set.
	if ( ! is_admin() ) {
		return;
	}

	// Only redirect if transient is set and user has proper capabilities.
	$redirect_transient = flux_media_optimizer_is_active_for_network()
		? get_site_transient( 'flux_media_optimizer_activation_redirect' )
		: get_transient( 'flux_media_optimizer_activation_redirect' );

	if ( $redirect_transient && current_user_can( 'manage_options' ) ) {
		// Delete the transient immediately to ensure this only happens once.
		if ( flux_media_optimizer_is_active_for_network() ) {
			delete_site_transient( 'flux_media_optimizer_activation_redirect' );
		} else {
			delete_transient( 'flux_media_optimizer_activation_redirect' );
		}
		
		// Redirect to plugin's own admin settings page (not external site).
		wp_safe_redirect( admin_url( 'admin.php?page=flux-media-optimizer' ) );
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
 * @since 3.0.0 Added requirements check before activation and multisite support for activation redirect.
 */
function flux_media_optimizer_activate() {
	// Check requirements before activation.
	global $wp_version;
	if ( version_compare( PHP_VERSION, '8.0', '<' ) || version_compare( $wp_version, '6.2', '<' ) ) {
		return;
	}

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
	if ( flux_media_optimizer_is_active_for_network() ) {
		set_site_transient( 'flux_media_optimizer_activation_redirect', true, 60 );
	} else {
	set_transient( 'flux_media_optimizer_activation_redirect', true, 60 );
	}
}

/**
 * Plugin deactivation handler.
 *
 * @since 0.1.0
 */
function flux_media_optimizer_deactivate() {
	// Clear scheduled WP Cron events.
	wp_clear_scheduled_hook( 'flux_media_optimizer_cleanup' );
	// Note: Bulk conversion now uses Action Scheduler, which handles its own cleanup

	// Note: We don't drop tables on deactivation to preserve data
	// Tables will only be dropped on uninstall
}

/**
 * Plugin uninstall handler.
 *
 * @since 2.0.1
 * @since 3.0.0 Added WP_UNINSTALL_PLUGIN security check and account ID cleanup for privacy compliance.
 */
function flux_media_optimizer_uninstall() {
	defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

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
		// Use %i placeholder for identifiers (table names) - available in WordPress 6.2+
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %i", $table ) );
	}

	// Remove plugin options.
	$options = [
		'flux_media_optimizer_settings',
		'flux_media_optimizer_version',
		'flux_media_optimizer_activation_redirect',
		'flux-plugins_account_id',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
		delete_site_option( $option );
	}

	// Remove post meta for all attachments.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_flux_media_optimizer_' ) . '%'
		)
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

	// Clear any scheduled WP Cron jobs.
	wp_clear_scheduled_hook( 'flux_media_optimizer_cleanup' );
	// Note: Action Scheduler actions are automatically cleaned up by Action Scheduler

	// Remove any transients.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_flux_media_optimizer_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_flux_media_optimizer_' ) . '%'
		)
	);
}
