<?php
/**
 * Attachment meta data handler for Flux Media Optimizer.
 *
 * Provides centralized getters and setters for all attachment meta fields
 * used by the plugin. This ensures consistent access patterns and makes
 * it easier to maintain and refactor meta field operations.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\App\Services;

/**
 * Handler for attachment meta data operations.
 *
 * @since 1.0.0
 */
class AttachmentMetaHandler {

	/**
	 * Format constants for validation.
	 *
	 * @since 1.0.0
	 */
	const FORMAT_AVIF = 'avif';
	const FORMAT_WEBP = 'webp';

	/**
	 * Meta key for converted formats.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERTED_FORMATS = '_flux_media_optimizer_converted_formats';

	/**
	 * Meta key for conversion date.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERSION_DATE = '_flux_media_optimizer_conversion_date';

	/**
	 * Meta key for conversion disabled flag.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERSION_DISABLED = '_flux_media_optimizer_conversion_disabled';

	/**
	 * Meta key for external job state.
	 *
	 * Stores the state of external processing job: 'queued', 'processing', 'completed', or 'failed'.
	 * 'queued' and 'processing' are treated identically (job in progress).
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const META_KEY_EXTERNAL_JOB_STATE = '_flux_media_optimizer_external_job_state';

	/**
	 * Meta key for external job started timestamp (Unix time).
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const META_KEY_EXTERNAL_JOB_STARTED_AT = '_flux_media_optimizer_external_job_started_at';

	/**
	 * Meta key for external job retry attempt count.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const META_KEY_EXTERNAL_JOB_RETRY_COUNT = '_flux_media_optimizer_external_job_retry_count';

	/**
	 * In-flight external job states.
	 *
	 * @since 4.2.0
	 * @var string[]
	 */
	private const IN_FLIGHT_JOB_STATES = [ 'queued', 'processing' ];

	/**
	 * Meta key for file URLs.
	 *
	 * Stores array of ALL file URLs (both local and external) for efficient lookup.
	 * Structure: array of URL strings, e.g., ['https://cdn.example.com/file1.webp', 'https://cdn.example.com/file2.avif', 'https://example.com/wp-content/uploads/file.webp'].
	 * This includes both external CDN URLs (external service) and local WordPress upload URLs.
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const META_KEY_FILE_URLS = '_flux_media_optimizer_file_urls';

	/**
	 * Meta key for converted files by size.
	 *
	 * Stores converted files organized by size with URLs and file sizes together.
	 * Structure: ['size_name' => ['format' => ['url' => 'url_string', 'filesize' => file_size_in_bytes]]]
	 * Values are always URLs (local WordPress upload URLs or external CDN URLs).
	 * URLs are generated immediately when files are stored, never stored as file paths.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERTED_FILES_BY_SIZE = '_flux_media_optimizer_converted_files_by_size';


	/**
	 * Get converted formats for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of format strings, or empty array if not found.
	 *               Example: ['webp', 'avif']
	 */
	public static function get_converted_formats( $attachment_id ) {
		$formats = get_post_meta( $attachment_id, self::META_KEY_CONVERTED_FORMATS, true );
		return is_array( $formats ) ? $formats : [];
	}

	/**
	 * Set converted formats for an attachment.
	 *
	 * @since 1.0.0
	 * @param int   $attachment_id Attachment ID.
	 * @param array $formats Array of format strings.
	 *                       Example: ['webp', 'avif']
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_converted_formats( $attachment_id, $formats ) {
		return update_post_meta( $attachment_id, self::META_KEY_CONVERTED_FORMATS, $formats );
	}

	/**
	 * Delete converted formats meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_converted_formats( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_CONVERTED_FORMATS );
	}

	/**
	 * Get conversion date for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Conversion date string, or null if not found.
	 */
	public static function get_conversion_date( $attachment_id ) {
		$date = get_post_meta( $attachment_id, self::META_KEY_CONVERSION_DATE, true );
		return ! empty( $date ) ? $date : null;
	}

	/**
	 * Set conversion date for an attachment.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $date Conversion date string (typically MySQL datetime format).
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_conversion_date( $attachment_id, $date ) {
		return update_post_meta( $attachment_id, self::META_KEY_CONVERSION_DATE, $date );
	}

	/**
	 * Set conversion date to current time for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_conversion_date_now( $attachment_id ) {
		return self::set_conversion_date( $attachment_id, current_time( 'mysql' ) );
	}

	/**
	 * Delete conversion date meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_conversion_date( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_CONVERSION_DATE );
	}

	/**
	 * Check if conversion is disabled for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if conversion is disabled, false otherwise.
	 */
	public static function is_conversion_disabled( $attachment_id ) {
		return (bool) get_post_meta( $attachment_id, self::META_KEY_CONVERSION_DISABLED, true );
	}

	/**
	 * Set conversion disabled flag for an attachment.
	 *
	 * @since 1.0.0
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $disabled Whether conversion should be disabled.
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_conversion_disabled( $attachment_id, $disabled = true ) {
		return update_post_meta( $attachment_id, self::META_KEY_CONVERSION_DISABLED, $disabled ? '1' : '' );
	}

	/**
	 * Enable conversion for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function enable_conversion( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_CONVERSION_DISABLED );
	}

	/**
	 * Disable conversion for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function disable_conversion( $attachment_id ) {
		return self::set_conversion_disabled( $attachment_id, true );
	}

	/**
	 * Check if attachment has any converted files.
	 *
	 * @since 1.0.0
	 * @since 3.0.0 Updated to use get_converted_files_grouped_by_size() instead of legacy format.
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if converted files exist, false otherwise.
	 */
	public static function has_converted_files( $attachment_id ) {
		$files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		return ! empty( $files_by_size );
	}

	/**
	 * Get all converted files grouped by size for an attachment.
	 *
	 * Returns files organized by size and format.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of size_name => format => data mappings.
	 *               Format: ['size_name' => ['format' => ['url' => 'url_or_path', 'filesize' => int]]]
	 */
	public static function get_converted_files_grouped_by_size( $attachment_id ) {
		$files = get_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES_BY_SIZE, true );
		return is_array( $files ) ? $files : [];
	}

	/**
	 * Set all converted files grouped by size for an attachment.
	 *
	 * Sets files organized by size and format.
	 *
	 * @since 1.0.0
	 * @param int   $attachment_id Attachment ID.
	 * @param array $files Array of size_name => format => data mappings.
	 *                     Format: ['size_name' => ['format' => ['url' => 'url_or_path', 'filesize' => int]]]
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_converted_files_grouped_by_size( $attachment_id, $files ) {
		return update_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES_BY_SIZE, $files );
	}

	/**
	 * Get converted files for a specific size with fallback logic.
	 *
	 * Returns an array with format keys (avif, webp) mapping to file paths or URLs.
	 * Extracts URLs from unified structure.
	 *
	 * Falls back through: preferred size → full size.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $preferred_size Preferred size name (e.g., 'post-thumbnail', 'medium', 'full'). Default 'full'.
	 * @return array Converted files array for the size with format keys, or empty array if none found.
	 *               Example: [
	 *                   'webp' => '/path/to/file.webp',  // or 'https://cdn.example.com/file.webp'
	 *                   'avif' => '/path/to/file.avif',  // or 'https://cdn.example.com/file.avif'
	 *               ]
	 */
	public static function get_converted_files_for_size( $attachment_id, $preferred_size = 'full' ) {
		$converted_files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		
		// Try preferred size first
		if ( ! empty( $converted_files_by_size ) && isset( $converted_files_by_size[ $preferred_size ] ) ) {
			$files_data = $converted_files_by_size[ $preferred_size ];
			if ( is_array( $files_data ) ) {
				// Extract URLs from unified structure.
				$files = [];
				foreach ( $files_data as $format => $data ) {
					if ( is_array( $data ) && isset( $data['url'] ) ) {
						$files[ $format ] = $data['url'];
					}
				}
				// Ensure it's an array with format keys (avif/webp) at root level
				if ( ! empty( $files ) && ( isset( $files[ self::FORMAT_AVIF ] ) || isset( $files[ self::FORMAT_WEBP ] ) ) ) {
					return $files;
				}
			}
		}
		
		// Fallback to full size
		if ( ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) ) {
			$files_data = $converted_files_by_size['full'];
			if ( is_array( $files_data ) ) {
				// Extract URLs from unified structure.
				$files = [];
				foreach ( $files_data as $format => $data ) {
					if ( is_array( $data ) && isset( $data['url'] ) ) {
						$files[ $format ] = $data['url'];
					}
				}
				// Ensure it's an array with format keys (avif/webp) at root level
				if ( ! empty( $files ) && ( isset( $files[ self::FORMAT_AVIF ] ) || isset( $files[ self::FORMAT_WEBP ] ) ) ) {
					return $files;
				}
			}
		}
		
		return [];
	}

	/**
	 * Set converted files for a specific size.
	 *
	 * Sets files for a specific size with format keys.
	 * Values can be file paths (local processing) or CDN URLs (external service).
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_name Size name (e.g., 'thumbnail', 'medium', 'full').
	 * @param array  $files Array of format => file_path_or_url mappings for the size.
	 *                      Example: [
	 *                          'webp' => '/path/to/file.webp',  // or 'https://cdn.example.com/file.webp'
	 *                          'avif' => '/path/to/file.avif',  // or 'https://cdn.example.com/file.avif'
	 *                      ]
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_converted_files_for_size( $attachment_id, $size_name, $files ) {
		$files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		$files_by_size[ $size_name ] = $files;
		return self::set_converted_files_grouped_by_size( $attachment_id, $files_by_size );
	}

	/**
	 * Delete all converted files grouped by size meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_converted_files_grouped_by_size( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES_BY_SIZE );
	}

	/**
	 * Delete converted files for a specific size.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_name Size name (e.g., 'thumbnail', 'medium', 'full').
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function delete_converted_files_for_size( $attachment_id, $size_name ) {
		$files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		if ( isset( $files_by_size[ $size_name ] ) ) {
			unset( $files_by_size[ $size_name ] );
			return self::set_converted_files_grouped_by_size( $attachment_id, $files_by_size );
		}
		return true;
	}

	/**
	 * Delete all conversion-related meta for an attachment, including size-specific data.
	 *
	 * @since 1.0.0
	 * @since 3.0.0 Removed legacy delete_converted_files() call as legacy format is obsolete.
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function delete_all( $attachment_id ) {
		self::delete_converted_formats( $attachment_id );
		self::delete_conversion_date( $attachment_id );
		self::delete_converted_files_grouped_by_size( $attachment_id );
		self::enable_conversion( $attachment_id );
	}

	/**
	 * Clear all conversion-related meta data for an attachment.
	 *
	 * Centralized method to clear all conversion-related meta data including:
	 * - Converted formats
	 * - Conversion date
	 * - Conversion disabled flag
	 * - External job state
	 * - CDN URLs
	 * - Converted files by size
	 * - Conversion tracking data (database table)
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function clear_all_attachment_meta( $attachment_id ) {
		// Clear all meta keys
		self::delete_converted_formats( $attachment_id );
		self::delete_conversion_date( $attachment_id );
		// Enable conversion (which deletes the disabled flag meta)
		self::enable_conversion( $attachment_id );
		self::delete_external_job_lifecycle_meta( $attachment_id );
		self::delete_file_urls( $attachment_id );
		self::delete_converted_files_grouped_by_size( $attachment_id );

		// Clear conversion tracking data (database table)
		\FluxMedia\App\Services\ConversionTracker::delete_attachment_conversions( $attachment_id );
	}

	/**
	 * Get converted file size for a specific format and size.
	 *
	 * Extracts filesize from the unified structure.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, original, etc.).
	 * @param string $size          Size name (full, thumbnail, medium, etc.). Default 'full'.
	 * @return int|null File size in bytes, or null if not found.
	 */
	public static function get_converted_file_size( $attachment_id, $format, $size = 'full' ) {
		$converted_files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		
		if ( ! empty( $converted_files_by_size ) ) {
			// Check requested size first.
			if ( isset( $converted_files_by_size[ $size ][ $format ] ) ) {
				$data = $converted_files_by_size[ $size ][ $format ];
				
				if ( is_array( $data ) && isset( $data['filesize'] ) ) {
					return (int) $data['filesize'];
				}
			}
			
			// Fallback to full size if requested size not found.
			if ( 'full' !== $size && isset( $converted_files_by_size['full'][ $format ] ) ) {
				$data = $converted_files_by_size['full'][ $format ];
				
				if ( is_array( $data ) && isset( $data['filesize'] ) ) {
					return (int) $data['filesize'];
				}
			}
		}
		
		return null;
	}

	/**
	 * Check if a value is a URL (starts with http:// or https://).
	 *
	 * @since 3.0.0
	 * @param mixed $value Value to check.
	 * @return bool True if value is a URL, false otherwise.
	 */
	public static function is_file_url( $value ) {
		return is_string( $value ) && ( strpos( $value, 'http://' ) === 0 || strpos( $value, 'https://' ) === 0 );
	}

	/**
	 * Get converted file URL for an attachment.
	 *
	 * Reads from meta storage and returns stored URL.
	 * URLs are always stored (never file paths), so no conversion is needed.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, av1, webm, original, etc.).
	 * @param string $size          Size name (full, thumbnail, medium, etc.). Default 'full'.
	 * @return string|null URL or null if not found.
	 */
	public static function get_converted_file_url( $attachment_id, $format, $size = 'full' ) {
		$converted_files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		
		if ( ! empty( $converted_files_by_size ) ) {
			// Check requested size first.
			if ( isset( $converted_files_by_size[ $size ][ $format ] ) ) {
				$data = $converted_files_by_size[ $size ][ $format ];
				
				if ( is_array( $data ) && isset( $data['url'] ) && ! empty( $data['url'] ) ) {
					// URL is always stored, return it directly.
					return esc_url_raw( $data['url'] );
				}
			}
			
			// Fallback to full size if requested size not found.
			if ( 'full' !== $size && isset( $converted_files_by_size['full'][ $format ] ) ) {
				$data = $converted_files_by_size['full'][ $format ];
				
				if ( is_array( $data ) && isset( $data['url'] ) && ! empty( $data['url'] ) ) {
					// URL is always stored, return it directly.
					return esc_url_raw( $data['url'] );
				}
			}
		}

		return null;
	}

	/**
	 * Generate file URL from file path.
	 *
	 * Handles all file types:
	 * - Images: Converts local file paths to WordPress upload URLs
	 * - Videos: Handles special AV1 filename format (file-av1.mp4) and converts to URL
	 * - CDN URLs: Returns as-is (already URLs)
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path or URL.
	 * @param string $format        Format (webp, avif, av1, webm, original, etc.).
	 * @return string|null Generated URL or null if conversion fails.
	 */
	private static function generate_file_url( $attachment_id, $file_path, $format ) {
		// If it's already a URL, return as-is.
		if ( self::is_file_url( $file_path ) ) {
			return esc_url_raw( $file_path );
		}
		
		// For image formats (webp, avif, original), convert file path to WordPress upload URL.
		if ( in_array( $format, [ 'webp', 'avif', 'original' ], true ) ) {
			// Check if file exists before converting.
			if ( file_exists( $file_path ) ) {
				$upload_dir = wp_upload_dir();
				$upload_path = wp_normalize_path( $upload_dir['basedir'] );
				$file_path_normalized = wp_normalize_path( $file_path );
				
				if ( strpos( $file_path_normalized, $upload_path ) === 0 ) {
					// File is in uploads directory, convert to URL.
					$relative_path = str_replace( $upload_path, '', $file_path_normalized );
					$relative_path = ltrim( $relative_path, '/' );
					return $upload_dir['baseurl'] . '/' . $relative_path;
				}
			}
		}
		
		// For video formats (av1, webm), handle special AV1 filename and convert to URL.
		if ( in_array( $format, [ 'av1', 'webm' ], true ) ) {
			if ( file_exists( $file_path ) ) {
				$upload_dir = wp_upload_dir();
				$upload_path = wp_normalize_path( $upload_dir['basedir'] );
				$file_path_normalized = wp_normalize_path( $file_path );
				
				if ( strpos( $file_path_normalized, $upload_path ) === 0 ) {
					// File is in uploads directory, convert to URL.
					// Handle AV1 special case: file-av1.mp4 should map to correct URL.
					$relative_path = str_replace( $upload_path, '', $file_path_normalized );
					$relative_path = ltrim( $relative_path, '/' );
					return $upload_dir['baseurl'] . '/' . $relative_path;
				}
				
				// Fallback: use attachment URL if file exists but not in uploads directory.
				return wp_get_attachment_url( $attachment_id );
			}
		}
		
		return null;
	}

	/**
	 * Check if a converted file exists.
	 *
	 * For URLs: checks if URL exists in meta (assumes exists if in meta).
	 * For file paths: uses file system check.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, av1, webm, original, etc.).
	 * @param string $size          Size name (full, thumbnail, medium, etc.). Default 'full'.
	 * @return bool True if file exists, false otherwise.
	 */
	public static function file_exists( $attachment_id, $format, $size = 'full' ) {
		$url = self::get_converted_file_url( $attachment_id, $format, $size );
		
		if ( empty( $url ) ) {
			return false;
		}
		
		// If it's a CDN URL (starts with http/https), assume it exists if it's in meta.
		if ( self::is_file_url( $url ) ) {
			return true;
		}
		
		// For local URLs, check if file_path exists in meta, then check file system.
		$converted_files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		$data = null;
		
		if ( ! empty( $converted_files_by_size ) ) {
			if ( isset( $converted_files_by_size[ $size ][ $format ] ) ) {
				$data = $converted_files_by_size[ $size ][ $format ];
			} elseif ( 'full' !== $size && isset( $converted_files_by_size['full'][ $format ] ) ) {
				$data = $converted_files_by_size['full'][ $format ];
			}
		}
		
		// If file_path is stored in meta, check that file.
		if ( is_array( $data ) && isset( $data['file_path'] ) && ! empty( $data['file_path'] ) ) {
			return file_exists( $data['file_path'] );
		}
		
		// Fallback: try to get file path from URL for local files.
		$upload_dir = wp_upload_dir();
		$base_url = $upload_dir['baseurl'];
		
		if ( strpos( $url, $base_url ) === 0 ) {
			$relative_path = str_replace( $base_url, '', $url );
			$relative_path = ltrim( $relative_path, '/' );
			$file_path = $upload_dir['basedir'] . '/' . $relative_path;
			return file_exists( $file_path );
		}
		
		// If we have a URL in meta, assume it exists.
		return true;
	}

	/**
	 * Get file size for a converted file.
	 *
	 * Always reads from meta storage - this is the source of truth.
	 * File sizes should always be stored in meta during conversion.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, av1, webm, original, etc.).
	 * @param string $size          Size name (full, thumbnail, medium, etc.). Default 'full'.
	 * @return int|null File size in bytes, or null if not found.
	 */
	public static function get_file_size( $attachment_id, $format, $size = 'full' ) {
		// Always get from meta storage - this is the source of truth.
		$meta_size = self::get_converted_file_size( $attachment_id, $format, $size );
		
		// Return meta size (even if 0 or null) - we should always store file sizes in meta.
		return $meta_size;
	}

	/**
	 * Set file URL and size for a specific format and size.
	 *
	 * Updates the unified structure. Always generates and stores URL in 'url' field.
	 * If a file path is provided, it will be converted to a URL immediately.
	 * URLs are always stored (never file paths).
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, av1, webm, original, etc.).
	 * @param string $size_name     Size name (full, thumbnail, medium, etc.).
	 * @param string $url_or_path   URL or file path (will be converted to URL if path).
	 * @param int    $file_size     File size in bytes (required).
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_file_url_and_size( $attachment_id, $format, $size_name, $url_or_path, $file_size ) {
		$files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		
		if ( ! isset( $files_by_size[ $size_name ] ) ) {
			$files_by_size[ $size_name ] = [];
		}
		
		// Generate URL immediately - convert file path to URL if needed.
		$url = null;
		
		if ( ! empty( $url_or_path ) ) {
			if ( self::is_file_url( $url_or_path ) ) {
				// Already a URL, use as-is.
				$url = $url_or_path;
			} else {
				// It's a file path, convert to URL.
				$url = self::generate_file_url( $attachment_id, $url_or_path, $format );
				// If conversion fails, use wp_get_attachment_url as fallback for original format.
				if ( ! $url && $format === 'original' ) {
					$url = wp_get_attachment_url( $attachment_id );
				}
			}
		}
		
		// Build storage structure - always use URL in 'url' field.
		$storage_data = [
			'url' => $url ? esc_url_raw( $url ) : '',
			'filesize' => (int) $file_size,
		];
		
		$files_by_size[ $size_name ][ $format ] = $storage_data;
		
		// Store in META_KEY_CONVERTED_FILES_BY_SIZE.
		$result = self::set_converted_files_grouped_by_size( $attachment_id, $files_by_size );
		
		// Also update META_KEY_FILE_URLS with all URLs for efficient lookup.
		if ( $url ) {
			self::update_file_urls_meta( $attachment_id );
		}
		
		return $result;
	}
	
	/**
	 * Update file URLs meta from converted files by size structure.
	 *
	 * Extracts all URLs from META_KEY_CONVERTED_FILES_BY_SIZE and stores them
	 * in META_KEY_FILE_URLS for efficient lookup. Handles all file types.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	private static function update_file_urls_meta( $attachment_id ) {
		$converted_files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		$all_urls = [];
		
		foreach ( $converted_files_by_size as $size_formats ) {
			if ( ! is_array( $size_formats ) ) {
				continue;
			}
			foreach ( $size_formats as $format_data ) {
				if ( is_array( $format_data ) && isset( $format_data['url'] ) && is_string( $format_data['url'] ) && ! empty( $format_data['url'] ) ) {
					// Store all URLs (local and external).
					$all_urls[] = $format_data['url'];
				}
			}
		}
		
		// Store all URLs in META_KEY_FILE_URLS.
		if ( ! empty( $all_urls ) ) {
			self::set_file_urls( $attachment_id, array_unique( $all_urls ) );
		}
	}

	/**
	 * Get external job state for an attachment.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Job state ('queued', 'processing', 'completed', 'failed') or null if not set.
	 */
	public static function get_external_job_state( $attachment_id ) {
		$state = get_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_STATE, true );
		return ! empty( $state ) ? $state : null;
	}

	/**
	 * Set external job state for an attachment.
	 *
	 * Manages lifecycle meta: started timestamp for in-flight jobs, cleanup on completion.
	 *
	 * @since 3.0.0
	 * @since 4.2.0 Records started timestamp and preserves retry count for failed jobs.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $state         Job state ('queued', 'processing', 'completed', 'failed').
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_external_job_state( $attachment_id, $state ) {
		$valid_states = [ 'queued', 'processing', 'completed', 'failed' ];
		if ( ! in_array( $state, $valid_states, true ) ) {
			return false;
		}

		if ( $state === 'queued' ) {
			self::set_external_job_started_at( $attachment_id, time() );
		} elseif ( $state === 'processing' ) {
			if ( self::get_external_job_started_at( $attachment_id ) <= 0 ) {
				self::set_external_job_started_at( $attachment_id, time() );
			}
		} elseif ( $state === 'completed' ) {
			self::delete_external_job_started_at( $attachment_id );
			self::reset_external_job_retry_count( $attachment_id );
		}

		return update_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_STATE, $state );
	}

	/**
	 * Get external job started timestamp for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @return int Unix timestamp, or 0 if not set.
	 */
	public static function get_external_job_started_at( $attachment_id ) {
		$started_at = get_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_STARTED_AT, true );
		return is_numeric( $started_at ) ? (int) $started_at : 0;
	}

	/**
	 * Set external job started timestamp for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @param int $timestamp     Unix timestamp.
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_external_job_started_at( $attachment_id, $timestamp ) {
		$timestamp = absint( $timestamp );
		if ( $timestamp <= 0 ) {
			return false;
		}

		return update_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_STARTED_AT, $timestamp );
	}

	/**
	 * Delete external job started timestamp meta for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_external_job_started_at( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_STARTED_AT );
	}

	/**
	 * Get external job retry count for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @return int Retry count (0 if not set).
	 */
	public static function get_external_job_retry_count( $attachment_id ) {
		$retry_count = get_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_RETRY_COUNT, true );
		return is_numeric( $retry_count ) ? (int) $retry_count : 0;
	}

	/**
	 * Increment external job retry count for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @return int New retry count after increment.
	 */
	public static function increment_external_job_retry_count( $attachment_id ) {
		$new_count = self::get_external_job_retry_count( $attachment_id ) + 1;
		update_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_RETRY_COUNT, $new_count );
		return $new_count;
	}

	/**
	 * Reset external job retry count for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function reset_external_job_retry_count( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_RETRY_COUNT );
	}

	/**
	 * Delete all external job lifecycle meta for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function delete_external_job_lifecycle_meta( $attachment_id ) {
		self::delete_external_job_state( $attachment_id );
		self::delete_external_job_started_at( $attachment_id );
		self::reset_external_job_retry_count( $attachment_id );
	}

	/**
	 * Delete external job state meta for an attachment.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_external_job_state( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_STATE );
	}

	/**
	 * Count attachments with a specific external job state.
	 *
	 * @since 4.2.0
	 * @param string $state Job state ('queued', 'processing', 'completed', 'failed').
	 * @return int Attachment count.
	 */
	public static function count_attachments_by_external_job_state( $state ) {
		$query = new \WP_Query(
			[
				'post_type' => 'attachment',
				'post_status' => 'any',
				'posts_per_page' => 1,
				'fields' => 'ids',
				'no_found_rows' => false,
				'meta_query' => [
					[
						'key' => self::META_KEY_EXTERNAL_JOB_STATE,
						'value' => $state,
						'compare' => '=',
					],
				],
			]
		);

		return (int) $query->found_posts;
	}

	/**
	 * Get file URLs for an attachment.
	 *
	 * Returns all URLs (both local and external) stored for efficient lookup.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of URL strings (local and external), or empty array if not found.
	 */
	public static function get_file_urls( $attachment_id ) {
		$urls = get_post_meta( $attachment_id, self::META_KEY_FILE_URLS, true );
		return is_array( $urls ) ? $urls : [];
	}

	/**
	 * Set file URLs for an attachment.
	 *
	 * Stores all URLs (both local and external) for efficient lookup.
	 *
	 * @since 3.0.0
	 * @param int   $attachment_id Attachment ID.
	 * @param array $file_urls     Array of URL strings (local and external).
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_file_urls( $attachment_id, $file_urls ) {
		// Validate that all values are strings (URLs).
		if ( ! is_array( $file_urls ) ) {
			return false;
		}
		
		// Filter to ensure all values are strings and sanitize URLs.
		$sanitized_urls = [];
		foreach ( $file_urls as $url ) {
			if ( is_string( $url ) && ! empty( $url ) ) {
				$sanitized_urls[] = esc_url_raw( $url );
			}
		}
		
		// Remove duplicates.
		$sanitized_urls = array_unique( $sanitized_urls );
		
		return update_post_meta( $attachment_id, self::META_KEY_FILE_URLS, $sanitized_urls );
	}

	/**
	 * Delete file URLs meta for an attachment.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_file_urls( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_FILE_URLS );
	}
}
