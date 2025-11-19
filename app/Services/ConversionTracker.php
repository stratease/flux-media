<?php
/**
 * Conversion tracking service.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\Logger;
use FluxMedia\App\Services\Converter;

/**
 * Handles tracking of converted files in the database.
 *
 * @since 0.1.0
 */
class ConversionTracker {

	/**
	 * Database table name.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Logger instance.
	 *
	 * @since 0.1.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'flux_media_optimizer_conversions';
	}

	/**
	 * Record a conversion for an attachment.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $file_type File type (webp, avif, av1, webm).
	 * @param int    $original_size Original file size in bytes.
	 * @param int    $converted_size Converted file size in bytes.
	 * @param string $size_name Image size name (full, thumbnail, medium, etc.). Default 'full'.
	 * @return bool True on success, false on failure.
	 */
	public function record_conversion( $attachment_id, $file_type, $original_size = 0, $converted_size = 0, $size_name = 'full' ) {
		global $wpdb;

		// Validate inputs
		if ( ! $attachment_id || ! $file_type ) {
			return false;
		}

		// Ensure size_name is valid (default to 'full' if empty)
		if ( empty( $size_name ) ) {
			$size_name = 'full';
		}

		// Calculate savings
		$size_savings = max( 0, $original_size - $converted_size );

		// Use INSERT ... ON DUPLICATE KEY UPDATE for atomic operation
		$result = $wpdb->query( $wpdb->prepare(
			"INSERT INTO {$this->table_name} (attachment_id, file_type, size_name, original_size, converted_size, size_savings, converted_at) 
			 VALUES (%d, %s, %s, %d, %d, %d, %s) 
			 ON DUPLICATE KEY UPDATE 
			 original_size = VALUES(original_size),
			 converted_size = VALUES(converted_size),
			 size_savings = VALUES(size_savings),
			 converted_at = VALUES(converted_at)",
			$attachment_id,
			$file_type,
			$size_name,
			$original_size,
			$converted_size,
			$size_savings,
			current_time( 'mysql' )
		) );

		if ( $result !== false ) {
			// Clear related caches
			wp_cache_delete( 'flux_media_optimizer_conversion_stats', 'flux_media_optimizer' );
			wp_cache_delete( 'flux_media_optimizer_savings_stats', 'flux_media_optimizer' );
		}

		return $result !== false;
	}


	/**
	 * Get all conversions for an attachment.
	 *
	 * Includes all sizes (full, thumbnail, medium, etc.).
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array Array of conversion records with size_name included.
	 */
	public function get_attachment_conversions( $attachment_id ) {
		global $wpdb;

		if ( ! $attachment_id ) {
			return [];
		}

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT file_type, size_name, original_size, converted_size, size_savings, converted_at 
			 FROM {$this->table_name} WHERE attachment_id = %d ORDER BY size_name, file_type, converted_at DESC",
			$attachment_id
		), ARRAY_A );

		// Calculate savings percentage for each result
		foreach ( $results as &$result ) {
			$result['savings_percentage'] = $result['original_size'] > 0 ? 
				round( ( $result['size_savings'] / $result['original_size'] ) * 100, 2 ) : 0;
		}

		return $results ?: [];
	}

	/**
	 * Check if an attachment has been converted to a specific file type.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * Includes all sizes in the count (full, thumbnail, medium, etc.).
	 *
	 * @since 1.0.0
	 * @return array Statistics array.
	 */
	public function get_conversion_stats() {
		// Check cache first
		$cache_key = 'flux_media_optimizer_conversion_stats';
		$stats = wp_cache_get( $cache_key, 'flux_media_optimizer' );
		
		if ( false !== $stats ) {
			return $stats;
		}

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

		// Cache the results for 5 minutes
		wp_cache_set( $cache_key, $stats, 'flux_media_optimizer', 300 );

		return $stats;
	}

	/**
	 * Get aggregate file size savings statistics.
	 *
	 * Includes savings from all sizes (full, thumbnail, medium, etc.) for accurate totals.
	 *
	 * @since 1.0.0
	 * @return array Savings statistics array.
	 */
	public function get_savings_stats() {
		// Check cache first
		$cache_key = 'flux_media_optimizer_savings_stats';
		$stats = wp_cache_get( $cache_key, 'flux_media_optimizer' );
		
		if ( false !== $stats ) {
			return $stats;
		}

		global $wpdb;

		$stats = [];

		// Total savings
		$total_savings = $wpdb->get_var( "SELECT SUM(size_savings) FROM {$this->table_name}" );
		$total_original = $wpdb->get_var( "SELECT SUM(original_size) FROM {$this->table_name}" );
		$total_converted = $wpdb->get_var( "SELECT SUM(converted_size) FROM {$this->table_name}" );

		$stats['total_savings_bytes'] = (int) $total_savings;
		$stats['total_original_bytes'] = (int) $total_original;
		$stats['total_converted_bytes'] = (int) $total_converted;
		$stats['total_savings_percentage'] = $total_original > 0 ? round( ( $total_savings / $total_original ) * 100, 2 ) : 0;

		// Savings by file type
		$type_savings = $wpdb->get_results(
			"SELECT file_type, SUM(original_size) as total_original, SUM(converted_size) as total_converted, SUM(size_savings) as total_savings, COUNT(*) as count 
			 FROM {$this->table_name} GROUP BY file_type",
			ARRAY_A
		);

		$stats['by_type'] = [];
		foreach ( $type_savings as $stat ) {
			$stats['by_type'][ $stat['file_type'] ] = [
				'count' => (int) $stat['count'],
				'total_original_bytes' => (int) $stat['total_original'],
				'total_converted_bytes' => (int) $stat['total_converted'],
				'total_savings_bytes' => (int) $stat['total_savings'],
				'savings_percentage' => $stat['total_original'] > 0 ? round( ( $stat['total_savings'] / $stat['total_original'] ) * 100, 2 ) : 0,
			];
		}

		// Recent savings (last 30 days)
		$recent_savings = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM(original_size) as total_original, SUM(converted_size) as total_converted, SUM(size_savings) as total_savings, COUNT(*) as count 
			 FROM {$this->table_name} WHERE converted_at >= %s",
			gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) )
		), ARRAY_A );

		$stats['recent'] = [
			'count' => (int) $recent_savings['count'],
			'total_original_bytes' => (int) $recent_savings['total_original'],
			'total_converted_bytes' => (int) $recent_savings['total_converted'],
			'total_savings_bytes' => (int) $recent_savings['total_savings'],
			'savings_percentage' => $recent_savings['total_original'] > 0 ? round( ( $recent_savings['total_savings'] / $recent_savings['total_original'] ) * 100, 2 ) : 0,
		];

		// Cache the results for 5 minutes
		wp_cache_set( $cache_key, $stats, 'flux_media_optimizer', 300 );

		return $stats;
	}

	/**
	 * Get conversion statistics for a specific attachment.
	 *
	 * Includes all sizes (full, thumbnail, medium, etc.) for accurate totals.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array Attachment conversion statistics.
	 */
	public function get_attachment_stats( $attachment_id ) {
		global $wpdb;

		if ( ! $attachment_id ) {
			return [];
		}

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT SUM(original_size) as total_original, SUM(converted_size) as total_converted, SUM(size_savings) as total_savings, COUNT(*) as count 
			 FROM {$this->table_name} WHERE attachment_id = %d",
			$attachment_id
		), ARRAY_A );

		if ( ! $stats ) {
			return [];
		}

		return [
			'count' => (int) $stats['count'],
			'total_original_bytes' => (int) $stats['total_original'],
			'total_converted_bytes' => (int) $stats['total_converted'],
			'total_savings_bytes' => (int) $stats['total_savings'],
			'savings_percentage' => $stats['total_original'] > 0 ? round( ( $stats['total_savings'] / $stats['total_original'] ) * 100, 2 ) : 0,
		];
	}
}