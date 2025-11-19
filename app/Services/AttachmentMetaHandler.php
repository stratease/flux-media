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
	 * Meta key for converted files by size.
	 *
	 * Stores converted files organized by size: ['size_name' => ['format' => 'file_path']]
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const META_KEY_CONVERTED_FILES_BY_SIZE = '_flux_media_optimizer_converted_files_by_size';

	/**
	 * Get converted files for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of format => file_path mappings, or empty array if not found.
	 */
	public static function get_converted_files( $attachment_id ) {
		$files = get_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES, true );
		return is_array( $files ) ? $files : [];
	}

	/**
	 * Set converted files for an attachment.
	 *
	 * @since 1.0.0
	 * @param int   $attachment_id Attachment ID.
	 * @param array $files Array of format => file_path mappings.
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
	 * Get converted files by size for an attachment.
	 *
	 * Returns files organized by size and format: ['size_name' => ['format' => 'file_path']]
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of size_name => format => file_path mappings, or empty array if not found.
	 */
	public static function get_converted_files_by_size( $attachment_id ) {
		$files = get_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES_BY_SIZE, true );
		return is_array( $files ) ? $files : [];
	}

	/**
	 * Set converted files by size for an attachment.
	 *
	 * @since 1.0.0
	 * @param int   $attachment_id Attachment ID.
	 * @param array $files Array of size_name => format => file_path mappings.
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_converted_files_by_size( $attachment_id, $files ) {
		return update_post_meta( $attachment_id, self::META_KEY_CONVERTED_FILES_BY_SIZE, $files );
	}

	/**
	 * Get converted files for a specific size.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_name Size name (e.g., 'thumbnail', 'medium', 'full').
	 * @return array Array of format => file_path mappings for the specified size, or empty array if not found.
	 */
	public static function get_converted_files_for_size( $attachment_id, $size_name ) {
		$files_by_size = self::get_converted_files_by_size( $attachment_id );
		if ( isset( $files_by_size[ $size_name ] ) && is_array( $files_by_size[ $size_name ] ) ) {
			return $files_by_size[ $size_name ];
		}
		return [];
	}

	/**
	 * Set converted files for a specific size.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_name Size name (e.g., 'thumbnail', 'medium', 'full').
	 * @param array  $files Array of format => file_path mappings for the size.
	 * @return bool|int Meta ID if the key didn't exist, true on successful update, false on failure.
	 */
	public static function set_converted_files_for_size( $attachment_id, $size_name, $files ) {
		$files_by_size = self::get_converted_files_by_size( $attachment_id );
		$files_by_size[ $size_name ] = $files;
		return self::set_converted_files_by_size( $attachment_id, $files_by_size );
	}

	/**
	 * Delete converted files by size meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_converted_files_by_size( $attachment_id ) {
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
		$files_by_size = self::get_converted_files_by_size( $attachment_id );
		if ( isset( $files_by_size[ $size_name ] ) ) {
			unset( $files_by_size[ $size_name ] );
			return self::set_converted_files_by_size( $attachment_id, $files_by_size );
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
		self::delete_converted_files_by_size( $attachment_id );
		self::enable_conversion( $attachment_id );
	}
}

