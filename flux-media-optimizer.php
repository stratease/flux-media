<?php
/**
 * Plugin Name: Flux Media Optimizer – Image & Video Optimization by Flux Plugins
 * Plugin URI: https://fluxplugins.com/media-optimizer
 * Description: One-click image (AVIF & WebP) and video optimization for WordPress.
 * Version: 4.3.0
 * Author: Flux Plugins
 * Author URI: https://fluxplugins.com
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flux-media-optimizer
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 7.0
 * Requires PHP: 8.1
 *
 * Copyright 2025 Flux Plugins
 *
 * @package FluxMedia
 * @since 1.0.0
 */

use FluxMedia\App\Services\FFmpegAutoloader;
use FluxMedia\FluxPlugins\Common\FluxPlugins;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '4.3.0' );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_FILE', __FILE__ );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_SLUG', 'flux-media-optimizer' );

// Align external service constants before prefixed common library constants load.
// @since 4.3.0
require_once __DIR__ . '/app/Services/ExternalServiceConfig.php';
$flux_media_optimizer_external_config = \FluxMedia\App\Services\ExternalServiceConfig::resolve(
	defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL' ) ? FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL : null,
	defined( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL' ) ? FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL : null,
	defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_TIMEOUT' ) ? (int) FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_TIMEOUT : null,
	defined( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT' ) ? (int) FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT : null
);

if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL', $flux_media_optimizer_external_config['url'] );
}
if ( ! defined( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL' ) ) {
	define( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_URL', $flux_media_optimizer_external_config['url'] );
}
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_TIMEOUT' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_TIMEOUT', $flux_media_optimizer_external_config['timeout'] );
}
if ( ! defined( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT' ) ) {
	define( 'FLUX_PLUGINS_COMMON_EXTERNAL_SERVICE_TIMEOUT', $flux_media_optimizer_external_config['timeout'] );
}

/**
 * API namespace prefix for plugin-specific endpoints.
 *
 * Used to namespace plugin-specific endpoints (e.g., upload operations) for clear separation
 * when multiple plugins integrate with the external service. Shared endpoints (validation,
 * activation, compatibility) do not use this namespace.
 *
 * @since 3.0.0
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_API_NAMESPACE' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_API_NAMESPACE', 'fmo' );
}

/**
 * Default CDN hostnames for webhook URL validation (comma-separated).
 *
 * Override in wp-config.php or extend via FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST.
 *
 * @since 4.1.6
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS', 'cdn.fluxplugins.com' );
}

/**
 * Additional CDN hostnames for webhook validation (comma-separated).
 *
 * @since 4.1.6
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST', '' );
}

/**
 * Maximum webhook requests per account per rate window.
 *
 * @since 4.1.6
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT', 60 );
}

/**
 * Webhook rate limit window in seconds.
 *
 * @since 4.1.6
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_WINDOW' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_WINDOW', 60 );
}

/**
 * Stale external job threshold in seconds (queued/processing older than this are marked failed).
 *
 * @since 4.2.0
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD', 6 * HOUR_IN_SECONDS );
}

/**
 * Maximum automatic conversion retry attempts after the initial failure.
 *
 * @since 4.2.0
 * @since 4.3.0 Applies to unified Action Scheduler retries (local and cloud).
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT', 3 );
}

/**
 * Maximum attachments processed per cleanup batch.
 *
 * @since 4.2.0
 */
if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE', 50 );
}

// Check PHP version compatibility.
// @since 3.0.0 Updated PHP version requirement from 7.4 to 8.0.
// @since 4.1.5 Updated PHP version requirement from 8.0 to 8.1.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action( 'admin_notices', 'flux_media_optimizer_php_version_notice' );
	return;
}

// Check WordPress version compatibility.
// @since 3.0.0 Added WordPress version requirement check.
global $wp_version;
if ( version_compare( $wp_version, '5.8', '<' ) ) {
	add_action( 'admin_notices', 'flux_media_optimizer_wp_version_notice' );
	return;
}

/**
 * Display PHP version compatibility notice.
 *
 * @since 0.1.0
 * @since 3.0.0 Updated PHP version requirement from 7.4 to 8.0.
 * @since 4.1.5 Updated PHP version requirement from 8.0 to 8.1.
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
				'8.1'
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
				'5.8'
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

// Load Action Scheduler.
// Action Scheduler is excluded from Strauss namespacing because it's a WordPress plugin/library
// that uses global functions and should not be prefixed.
// According to Action Scheduler docs, we only need to include the file - it handles its own initialization.
// It registers on 'plugins_loaded' priority 0 and initializes on 'init' priority 1.
// Action Scheduler APIs should not be used until after 'init' priority 1 or the 'action_scheduler_init' hook.
// @since 3.0.3
if ( ! function_exists( 'as_schedule_single_action' ) ) {
	$action_scheduler_file = FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	if ( file_exists( $action_scheduler_file ) ) {
		require_once $action_scheduler_file;
	}
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
 * @since 4.0.0 Initialize Flux Plugins common library.
 */
function flux_media_optimizer_init() {
	// Initialize Flux Plugins common library.
	// This handles account ID, menu setup, and required pages etc.
	FluxPlugins::init( FLUX_MEDIA_OPTIMIZER_PLUGIN_SLUG, FLUX_MEDIA_OPTIMIZER_VERSION, 'flux-media-optimizer', FLUX_MEDIA_OPTIMIZER_PLUGIN_URL . 'src/assets/common/' );
	
	// Initialize the main plugin class.
	$flux_media_optimizer = new FluxMedia\App\Plugin();
	$flux_media_optimizer->init();

	// Register WP-CLI commands if WP-CLI is available.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::add_command( 'flux-media-optimizer', 'FluxMedia\App\Console\Commands\FluxMediaCommand' );
	}
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
 * @since 4.1.5 Updated PHP version requirement check from 8.0 to 8.1.
 */
function flux_media_optimizer_activate() {
	global $wp_version;

	if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( $wp_version, '5.8', '<' ) ) {
		flux_media_optimizer_abort_activation(
			esc_html__( 'Flux Media Optimizer requires PHP 8.1 or higher and WordPress 5.8 or higher.', 'flux-media-optimizer' )
		);
	}

	if ( ! file_exists( FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php' )
		|| ! file_exists( FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor-prefixed/autoload.php' ) ) {
		flux_media_optimizer_abort_activation(
			esc_html__( 'Flux Media Optimizer requires Composer dependencies. Please run "composer install" in the plugin directory.', 'flux-media-optimizer' )
		);
	}

	require_once FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php';
	require_once FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor-prefixed/autoload.php';

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
 * Deactivate plugin during activation when requirements are not met.
 *
 * @since 4.2.1
 * @param string $message User-facing error message.
 * @return void
 */
function flux_media_optimizer_abort_activation( $message ) {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	deactivate_plugins( plugin_basename( FLUX_MEDIA_OPTIMIZER_PLUGIN_FILE ) );

	wp_die(
		$message,
		esc_html__( 'Plugin Activation Error', 'flux-media-optimizer' ),
		[ 'back_link' => true ]
	);
}

/**
 * Plugin uninstall handler.
 *
 * @since 2.0.1
 * @since 3.0.0 Added WP_UNINSTALL_PLUGIN security check.
 * @since 4.2.1 Removes correct option keys; preserves shared flux-plugins_account_id for other suite plugins.
 */
function flux_media_optimizer_uninstall() {
	defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

	global $wpdb;

	// Initialize WordPress filesystem.
	WP_Filesystem();

	if ( file_exists( FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
		require_once FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/autoload.php';
	}

	if ( class_exists( 'FluxMedia\App\Services\Database' ) ) {
		FluxMedia\App\Services\Database::drop_tables();
	}

	$options = class_exists( 'FluxMedia\App\Services\Settings' )
		? FluxMedia\App\Services\Settings::get_uninstall_option_names()
		: [ 'flux_media_optimizer_options' ];

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
