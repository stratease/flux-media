<?php
/**
 * Conversion tracking service.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Services;

use FluxMedia\Utils\Logger;

/**
 * Handles tracking of converted files in the database.
 *
 * @since 1.0.0
 */
class ConversionTracker {

	/**
	 * Database table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger
	 */
	private $logger;


	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'flux_media_conversions';
	}

	/**
	 * Record a conversion for an attachment.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $file_type File type (webp, avif, av1, webm).
	 * @return bool True on success, false on failure.
	 */
	public function record_conversion( $attachment_id, $file_type ) {
		global $wpdb;

		// Validate inputs
		if ( ! $attachment_id || ! $file_type ) {
			return false;
		}

		// Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$this->table_name} (attachment_id, file_type, converted_at) 
			 VALUES (%d, %s, %s) 
			 ON DUPLICATE KEY UPDATE converted_at = VALUES(converted_at)",
			$attachment_id,
			$file_type,
			current_time( 'mysql' )
		) );

		return $result !== false;
	}

	/**
	 * Get all conversions for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array Array of conversion records.
	 */
	public function get_attachment_conversions( $attachment_id ) {
		global $wpdb;

		if ( ! $attachment_id ) {
			return [];
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT file_type, converted_at FROM {$this->table_name} WHERE attachment_id = %d ORDER BY converted_at DESC",
			$attachment_id
		), ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Check if an attachment has been converted to a specific file type.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $file_type File type to check.
	 * @return bool True if converted, false otherwise.
	 */
	public function has_conversion( $attachment_id, $file_type ) {
		global $wpdb;

		if ( ! $attachment_id || ! $file_type ) {
			return false;
		}

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE attachment_id = %d AND file_type = %s",
			$attachment_id,
			$file_type
		) );

		return (int) $count > 0;
	}

	/**
	 * Get all file types that an attachment has been converted to.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array Array of file types.
	 */
	public function get_converted_types( $attachment_id ) {
		global $wpdb;

		if ( ! $attachment_id ) {
			return [];
		}

		$results = $wpdb->get_col( $wpdb->prepare(
			"SELECT file_type FROM {$this->table_name} WHERE attachment_id = %d",
			$attachment_id
		) );

		return $results ?: [];
	}

	/**
	 * Delete all conversion records for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_attachment_conversions( $attachment_id ) {
		global $wpdb;

		if ( ! $attachment_id ) {
			return false;
		}

		$result = $wpdb->delete(
			$this->table_name,
			[ 'attachment_id' => $attachment_id ],
			[ '%d' ]
		);

		return $result !== false;
	}

	/**
	 * Get conversion statistics.
	 *
	 * @since 1.0.0
	 * @return array Statistics array.
	 */
	public function get_conversion_stats() {
		global $wpdb;

		$stats = [];

		// Total conversions
		$stats['total_conversions'] = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

		// Conversions by file type
		$type_stats = $wpdb->get_results(
			"SELECT file_type, COUNT(*) as count FROM {$this->table_name} GROUP BY file_type",
			ARRAY_A
		);

		$stats['by_type'] = [];
		foreach ( $type_stats as $stat ) {
			$stats['by_type'][ $stat['file_type'] ] = (int) $stat['count'];
		}

		// Recent conversions (last 30 days)
		$stats['recent_conversions'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE converted_at >= %s",
			date( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
		) );

		return $stats;
	}
}