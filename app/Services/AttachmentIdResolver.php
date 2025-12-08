<?php
/**
 * Attachment ID resolver service.
 *
 * Provides centralized methods to resolve attachment_id from various inputs
 * (URLs, file paths, CDN URLs).
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

/**
 * Service for resolving attachment IDs from various inputs.
 *
 * @since 3.0.0
 */
class AttachmentIdResolver {

	/**
	 * Resolve attachment ID from a URL or file path.
	 *
	 * Automatically detects type and calls appropriate method.
	 *
	 * @since 3.0.0
	 * @param string $input URL or file path.
	 * @return int|null Attachment ID or null if not found.
	 */
	public static function resolve( $input ) {
		if ( empty( $input ) || ! is_string( $input ) ) {
			return null;
		}

		// Check if it's a URL (starts with http:// or https://).
		if ( strpos( $input, 'http://' ) === 0 || strpos( $input, 'https://' ) === 0 ) {
			return self::from_url( $input );
		}

		// Otherwise, treat as file path.
		return self::from_file_path( $input );
	}

	/**
	 * Resolve attachment ID from a URL (WordPress URL or CDN URL).
	 *
	 * @since 3.0.0
	 * @param string $url URL to resolve.
	 * @return int|null Attachment ID or null if not found.
	 */
	public static function from_url( $url ) {
		if ( empty( $url ) || ! is_string( $url ) ) {
			return null;
		}

		// First, try WordPress attachment_url_to_postid() for WordPress URLs.
		$attachment_id = attachment_url_to_postid( $url );
		if ( $attachment_id ) {
			return $attachment_id;
		}

		// If that fails, try database lookup for WordPress URLs.
		$attachment_id = self::from_wordpress_url( $url );
		if ( $attachment_id ) {
			return $attachment_id;
		}

		// If it's a CDN URL, try to resolve from CDN URL.
		return self::from_cdn_url( $url );
	}

	/**
	 * Resolve attachment ID from a WordPress URL using database lookup.
	 *
	 * @since 3.0.0
	 * @param string $url WordPress URL.
	 * @return int|null Attachment ID or null if not found.
	 */
	private static function from_wordpress_url( $url ) {
		global $wpdb;

		// Extract file path from URL.
		$upload_dir = wp_upload_dir();
		$base_url = $upload_dir['baseurl'];
		
		if ( strpos( $url, $base_url ) !== 0 ) {
			return null;
		}

		// Get relative path.
		$relative_path = str_replace( $base_url, '', $url );
		$relative_path = ltrim( $relative_path, '/' );

		// Query by _wp_attached_file meta.
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			$relative_path
		) );

		return $attachment_id ? (int) $attachment_id : null;
	}

	/**
	 * Resolve attachment ID from a local file path.
	 *
	 * @since 3.0.0
	 * @param string $file_path File path to resolve.
	 * @return int|null Attachment ID or null if not found.
	 */
	public static function from_file_path( $file_path ) {
		if ( empty( $file_path ) || ! is_string( $file_path ) ) {
			return null;
		}

		global $wpdb;

		// Normalize path.
		$file_path = wp_normalize_path( $file_path );
		
		// Get upload directory.
		$upload_dir = wp_upload_dir();
		$upload_path = wp_normalize_path( $upload_dir['basedir'] );

		// Extract relative path.
		if ( strpos( $file_path, $upload_path ) !== 0 ) {
			return null;
		}

		$relative_path = str_replace( $upload_path, '', $file_path );
		$relative_path = ltrim( $relative_path, '/' );

		// Query by _wp_attached_file meta.
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			$relative_path
		) );

		return $attachment_id ? (int) $attachment_id : null;
	}

	/**
	 * Resolve attachment ID from a CDN URL.
	 *
	 * Queries attachment meta for matching CDN URLs.
	 *
	 * @since 3.0.0
	 * @param string $cdn_url CDN URL to resolve.
	 * @return int|null Attachment ID or null if not found.
	 */
	public static function from_cdn_url( $cdn_url ) {
		if ( empty( $cdn_url ) || ! is_string( $cdn_url ) ) {
			return null;
		}

		// Find by matching CDN URL in attachment meta.
		return self::from_cdn_url_in_meta( $cdn_url );
	}

	/**
	 * Resolve attachment ID from CDN URL stored in attachment meta.
	 *
	 * Uses dedicated CDN URLs meta field for efficient lookup.
	 *
	 * @since 3.0.0
	 * @param string $cdn_url CDN URL to find.
	 * @return int|null Attachment ID or null if not found.
	 */
	private static function from_cdn_url_in_meta( $cdn_url ) {
		global $wpdb;

		// Use dedicated CDN URLs meta field for efficient lookup.
		$meta_key = AttachmentMetaHandler::META_KEY_CDN_URLS;
		
		// Query attachments with this meta key and search for matching URL.
		// Use LIKE to find the URL in the serialized array.
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
			$meta_key,
			'%' . $wpdb->esc_like( $cdn_url ) . '%'
		), ARRAY_A );

		foreach ( $results as $row ) {
			$meta_value = maybe_unserialize( $row['meta_value'] );
			if ( ! is_array( $meta_value ) ) {
				continue;
			}

			// Check if the CDN URL exists in the array.
			if ( in_array( $cdn_url, $meta_value, true ) ) {
							return (int) $row['post_id'];
			}
		}

		return null;
	}
}

