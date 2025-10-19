<?php
/**
 * Uninstall script for Flux Media plugin.
 *
 * This file is executed when the plugin is uninstalled (deleted) from WordPress.
 * It cleans up all plugin data including custom tables, options, and files.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load WordPress functions.
require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * Clean up plugin data on uninstall.
 *
 * @since 0.1.0
 */
function flux_media_uninstall() {
	global $wpdb;

	// Remove custom database tables.
	$tables = [
		$wpdb->prefix . 'flux_media_conversions',
		$wpdb->prefix . 'flux_media_logs',
		$wpdb->prefix . 'flux_media_settings',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// Remove plugin options.
	$options = [
		'flux_media_settings',
		'flux_media_version',
		'flux_media_activation_redirect',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
		delete_site_option( $option );
	}

	// Remove post meta for all attachments.
	$wpdb->query(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_flux_media_%'"
	);

	// Remove converted files from uploads directory.
	$upload_dir = wp_upload_dir();
	$flux_media_dir = $upload_dir['basedir'] . '/flux-media-converted';

	if ( is_dir( $flux_media_dir ) ) {
		// Remove all files in the converted directory.
		$files = glob( $flux_media_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
		// Remove the directory itself.
		rmdir( $flux_media_dir );
	}

	// Clear any scheduled cron jobs.
	wp_clear_scheduled_hook( 'flux_media_cleanup' );
	wp_clear_scheduled_hook( 'flux_media_bulk_convert' );

	// Remove any transients.
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_flux_media_%' OR option_name LIKE '_transient_timeout_flux_media_%'"
	);
}

// Run the uninstall function.
flux_media_uninstall();
