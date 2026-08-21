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
	 * @since 4.1.6 Stopped creating legacy `flux_media_optimizer_logs` (suite uses `flux_plugins_logs`).
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

		// Logging uses flux-plugins-common table `{prefix}flux_plugins_logs` (see Logger\DatabaseHandler).

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		// Check if tables exist - if not, create them directly
		// dbDelta tries to DESCRIBE tables first, which fails if they don't exist
		$tables_exist = self::tables_exist();
		
		if ( ! $tables_exist ) {
			// Tables don't exist, create them directly
			$wpdb->query( $conversions_sql );
		} else {
			// Tables exist, use dbDelta to update them if needed
			dbDelta( $conversions_sql );
		}

		// Store database version for future updates
		update_option( 'flux_media_optimizer_db_version', '2.0' );
	}

	/**
	 * Table names managed by this plugin (current and legacy).
	 *
	 * @since 4.2.1
	 * @return string[] Fully qualified table names.
	 */
	public static function get_table_names() {
		global $wpdb;

		return [
			$wpdb->prefix . 'flux_media_optimizer_conversions',
			$wpdb->prefix . 'flux_media_optimizer_logs',
			$wpdb->prefix . 'flux_media_optimizer_external_jobs',
			$wpdb->prefix . 'flux_media_optimizer_settings',
		];
	}

	/**
	 * Drop all Flux Media Optimizer database tables.
	 *
	 * @since 0.1.0
	 * @since 4.2.1 Use validated identifier SQL instead of prepared %s placeholders.
	 */
	public static function drop_tables() {
		foreach ( self::get_table_names() as $table ) {
			self::drop_table_if_exists( $table );
		}

		delete_option( 'flux_media_optimizer_db_version' );
	}

	/**
	 * Drop a database table when the name is a safe SQL identifier.
	 *
	 * @since 4.2.1
	 * @param string $table Fully qualified table name.
	 * @return void
	 */
	public static function drop_table_if_exists( $table ) {
		global $wpdb;

		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table ) ) {
			return;
		}

		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

		return $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $conversions_table ) ) === $conversions_table;
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
		// Check if tables exist first - if not, create them
		if ( ! self::tables_exist() ) {
			self::create_tables();
			return;
		}

		// If tables exist, check version and update if needed
		$current_version = self::get_db_version();
		$target_version = '2.0';

		if ( version_compare( $current_version, $target_version, '<' ) ) {
			self::create_tables();
		}
	}
}
