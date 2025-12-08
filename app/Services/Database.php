<?php
/**
 * Database setup and management for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

/**
 * Handles database table creation and management for WordPress.
 *
 * @since 0.1.0
 */
class Database {

	/**
	 * Create all Flux Media Optimizer database tables.
	 *
	 * @since 0.1.0
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create conversions table
		$conversions_table = $wpdb->prefix . 'flux_media_optimizer_conversions';
		$conversions_sql = "CREATE TABLE $conversions_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) NOT NULL,
			file_type varchar(10) NOT NULL,
			size_name varchar(50) DEFAULT 'full',
			original_size bigint(20) DEFAULT 0,
			converted_size bigint(20) DEFAULT 0,
			size_savings bigint(20) DEFAULT 0,
			converted_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_conversion (attachment_id, file_type, size_name),
			KEY attachment_id (attachment_id),
			KEY file_type (file_type),
			KEY size_name (size_name),
			KEY converted_at (converted_at)
		) $charset_collate;";

		// Create logs table
		$logs_table = $wpdb->prefix . 'flux_media_optimizer_logs';
		$logs_sql = "CREATE TABLE $logs_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		dbDelta( $conversions_sql );
		dbDelta( $logs_sql );

		// Store database version for future updates
		update_option( 'flux_media_optimizer_db_version', '2.0' );
	}

	/**
	 * Drop all Flux Media Optimizer database tables.
	 *
	 * @since 0.1.0
	 */
	public static function drop_tables() {
		global $wpdb;

		$conversions_table = $wpdb->prefix . 'flux_media_optimizer_conversions';
		$logs_table = $wpdb->prefix . 'flux_media_optimizer_logs';
		$external_jobs_table = $wpdb->prefix . 'flux_media_optimizer_external_jobs';

		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %s", $conversions_table ) );
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %s", $logs_table ) );
		// Drop external_jobs table if it exists (legacy table, no longer used).
		$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS %s", $external_jobs_table ) );

		// Remove database version option
		delete_option( 'flux_media_optimizer_db_version' );
	}

	/**
	 * Check if database tables exist.
	 *
	 * @since 0.1.0
	 * @return bool True if tables exist, false otherwise.
	 */
	public static function tables_exist() {
		global $wpdb;

		$conversions_table = $wpdb->prefix . 'flux_media_optimizer_conversions';
		$logs_table = $wpdb->prefix . 'flux_media_optimizer_logs';

		$conversions_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $conversions_table ) ) === $conversions_table;
		$logs_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $logs_table ) ) === $logs_table;

		return $conversions_exists && $logs_exists;
	}

	/**
	 * Get database version.
	 *
	 * @since 0.1.0
	 * @return string Database version.
	 */
	public static function get_db_version() {
		return get_option( 'flux_media_optimizer_db_version', '0.0' );
	}

	/**
	 * Update database if needed.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_update_database() {
		$current_version = self::get_db_version();
		$target_version = '2.0';

		if ( version_compare( $current_version, $target_version, '<' ) ) {
			self::create_tables();
		}
	}
}
