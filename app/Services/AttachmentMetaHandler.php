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
	 * Meta key for converted files.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERTED_FILES = '_flux_media_optimizer_converted_files';

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
	 * Meta key for CDN URLs.
	 *
	 * Stores array of CDN URLs for efficient lookup.
	 * Structure: array of URL strings, e.g., ['https://cdn.example.com/file1.webp', 'https://cdn.example.com/file2.avif'].
	 *
	 * @since 3.0.0
	 * @var string
	 */
	const META_KEY_CDN_URLS = '_flux_media_optimizer_cdn_urls';

	/**
	 * Meta key for converted files by size.
	 *
	 * Stores converted files organized by size with URLs/paths and file sizes together.
	 * Structure: ['size_name' => ['format' => ['url' => 'file_path_or_url', 'filesize' => file_size_in_bytes]]]
	 * Values can be file paths (local processing) or CDN URLs (external service).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERTED_FILES_BY_SIZE = '_flux_media_optimizer_converted_files_by_size';

	/**
	 * Get converted files for an attachment.
	 *
	 * Returns legacy format structure (full size only).
	 * Values can be file paths (local processing) or CDN URLs (external service).
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of format => file_path_or_url mappings, or empty array if not found.
	 *               Example: [
	 *                   'webp' => '/path/to/file.webp',  // or 'https://cdn.example.com/file.webp'
	 *                   'avif' => '/path/to/file.avif',  // or 'https://cdn.example.com/file.avif'
	 *               ]
	 */
	public static function get_converted_files( $attachment_id ) {
		$files = get_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES, true );
		return is_array( $files ) ? $files : [];
	}

	/**
	 * Set converted files for an attachment.
	 *
	 * Sets legacy format structure (full size only).
	 * Values can be file paths (local processing) or CDN URLs (external service).
	 *
	 * @since 1.0.0
	 * @param int   $attachment_id Attachment ID.
	 * @param array $files Array of format => file_path_or_url mappings.
	 *                     Example: [
	 *                         'webp' => '/path/to/file.webp',  // or 'https://cdn.example.com/file.webp'
	 *                         'avif' => '/path/to/file.avif',  // or 'https://cdn.example.com/file.avif'
	 *                     ]
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_converted_files( $attachment_id, $files ) {
		return update_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES, $files );
	}

	/**
	 * Delete converted files meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_converted_files( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES );
	}

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
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if converted files exist, false otherwise.
	 */
	public static function has_converted_files( $attachment_id ) {
		$files = self::get_converted_files( $attachment_id );
		return ! empty( $files );
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
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function delete_all( $attachment_id ) {
		self::delete_converted_files( $attachment_id );
		self::delete_converted_formats( $attachment_id );
		self::delete_conversion_date( $attachment_id );
		self::delete_converted_files_grouped_by_size( $attachment_id );
		self::enable_conversion( $attachment_id );
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
	 * Reads from meta storage and returns URL or file path.
	 * For file paths, converts to URL if needed.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, av1, webm, original, etc.).
	 * @param string $size          Size name (full, thumbnail, medium, etc.). Default 'full'.
	 * @return string|null URL or file path, or null if not found.
	 */
	public static function get_converted_file_url( $attachment_id, $format, $size = 'full' ) {
		// Allow filtering before processing.
		$pre_filter = apply_filters( 'flux_media_optimizer_get_converted_file_url_pre', null, $attachment_id, $format, $size );
		if ( $pre_filter !== null ) {
			return $pre_filter;
		}

		$converted_files_by_size = self::get_converted_files_grouped_by_size( $attachment_id );
		
		if ( ! empty( $converted_files_by_size ) ) {
			// Check requested size first.
			if ( isset( $converted_files_by_size[ $size ][ $format ] ) ) {
				$data = $converted_files_by_size[ $size ][ $format ];
				
				if ( is_array( $data ) && isset( $data['url'] ) && ! empty( $data['url'] ) ) {
					// Check if it's actually a URL.
					if ( self::is_file_url( $data['url'] ) ) {
						return esc_url_raw( $data['url'] );
					}
					// If it's a file path (legacy data), convert to URL.
					return self::convert_file_path_to_url( $attachment_id, $data['url'], $format );
				}
			}
			
			// Fallback to full size if requested size not found.
			if ( 'full' !== $size && isset( $converted_files_by_size['full'][ $format ] ) ) {
				$data = $converted_files_by_size['full'][ $format ];
				
				if ( is_array( $data ) && isset( $data['url'] ) && ! empty( $data['url'] ) ) {
					// Check if it's actually a URL.
					if ( self::is_file_url( $data['url'] ) ) {
						return esc_url_raw( $data['url'] );
					}
					// If it's a file path (legacy data), convert to URL.
					return self::convert_file_path_to_url( $attachment_id, $data['url'], $format );
				}
			}
		}

		return null;
	}

	/**
	 * Convert file path to URL.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path.
	 * @param string $format        Format (webp, avif, av1, webm, etc.).
	 * @return string|null URL or null if conversion fails.
	 */
	private static function convert_file_path_to_url( $attachment_id, $file_path, $format ) {
		// For image formats, try to use WordPressImageRenderer helper if available.
		if ( in_array( $format, [ 'webp', 'avif' ], true ) ) {
			// Check if file exists before converting.
			if ( file_exists( $file_path ) ) {
				// Try to get URL using WordPress helper.
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
		
		// For videos, return attachment URL if file exists.
		if ( in_array( $format, [ 'av1', 'webm' ], true ) ) {
			if ( file_exists( $file_path ) ) {
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
	 * Updates the unified structure. Always stores URL in 'url' field.
	 * If a file path is provided, it will be converted to a URL.
	 * Optionally stores file path in 'file_path' key for local files.
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
		
		// Convert file path to URL if needed.
		$url = $url_or_path;
		$file_path = null;
		
		if ( ! empty( $url_or_path ) && ! self::is_file_url( $url_or_path ) ) {
			// It's a file path, convert to URL and store path separately.
			$file_path = $url_or_path;
			$url = self::convert_file_path_to_url( $attachment_id, $url_or_path, $format );
			// If conversion fails, use wp_get_attachment_url as fallback for original format.
			if ( ! $url && $format === 'original' ) {
				$url = wp_get_attachment_url( $attachment_id );
			}
		}
		
		// Build storage structure - always use URL in 'url' field.
		$storage_data = [
			'url' => $url ? esc_url_raw( $url ) : '',
			'filesize' => (int) $file_size,
		];
		
		// Optionally store file path for local files.
		if ( $file_path ) {
			$storage_data['file_path'] = $file_path;
		}
		
		$files_by_size[ $size_name ][ $format ] = $storage_data;
		
		return self::set_converted_files_grouped_by_size( $attachment_id, $files_by_size );
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
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $state         Job state ('queued', 'processing', 'completed', 'failed').
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_external_job_state( $attachment_id, $state ) {
		$valid_states = [ 'queued', 'processing', 'completed', 'failed' ];
		if ( ! in_array( $state, $valid_states, true ) ) {
			return false;
		}
		return update_post_meta( $attachment_id, self::META_KEY_EXTERNAL_JOB_STATE, $state );
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
	 * Get CDN URLs for an attachment.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of CDN URL strings, or empty array if not found.
	 */
	public static function get_cdn_urls( $attachment_id ) {
		$urls = get_post_meta( $attachment_id, self::META_KEY_CDN_URLS, true );
		return is_array( $urls ) ? $urls : [];
	}

	/**
	 * Set CDN URLs for an attachment.
	 *
	 * @since 3.0.0
	 * @param int   $attachment_id Attachment ID.
	 * @param array $cdn_urls      Array of CDN URL strings.
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_cdn_urls( $attachment_id, $cdn_urls ) {
		// Validate that all values are strings (URLs).
		if ( ! is_array( $cdn_urls ) ) {
			return false;
		}
		
		// Filter to ensure all values are strings and sanitize URLs.
		$sanitized_urls = [];
		foreach ( $cdn_urls as $url ) {
			if ( is_string( $url ) && ! empty( $url ) ) {
				$sanitized_urls[] = esc_url_raw( $url );
			}
		}
		
		// Remove duplicates.
		$sanitized_urls = array_unique( $sanitized_urls );
		
		return update_post_meta( $attachment_id, self::META_KEY_CDN_URLS, $sanitized_urls );
	}

	/**
	 * Delete CDN URLs meta for an attachment.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_cdn_urls( $attachment_id ) {
		return delete_post_meta( $attachment_id, self::META_KEY_CDN_URLS );
	}
}
