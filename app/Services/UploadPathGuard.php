<?php
/**
 * Validates filesystem paths against the WordPress uploads directory.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Uploads-root containment helpers for local path and URL safety checks.
 *
 * Content read/write/delete stays on WP_Filesystem at call sites. This class uses
 * native file_exists()/realpath() intentionally: WP_Filesystem has no symlink-safe
 * canonicalization API, and containment checks require resolving .. and links
 * before prefix comparison. Prefer wp_normalize_path() and wp_parse_url() for
 * string normalization. Linux hosts are the primary target; path comparisons are
 * case-sensitive after normalization.
 *
 * Destinations that do not exist yet must be derived from a validated existing
 * source or parent directory; do not call realpath() on nonexistent outputs.
 *
 * @since 4.3.0
 */
class UploadPathGuard {

	/**
	 * Resolve the WordPress uploads basedir, failing closed on upload-dir errors.
	 *
	 * @since 4.3.0
	 * @return string|false Absolute basedir or false when unavailable.
	 */
	public static function get_uploads_basedir() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		if ( ! is_array( $upload_dir ) || ! empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) ) {
			return false;
		}

		return wp_normalize_path( (string) $upload_dir['basedir'] );
	}

	/**
	 * Resolve the WordPress uploads baseurl, failing closed on upload-dir errors.
	 *
	 * @since 4.3.0
	 * @return string|false Base URL or false when unavailable.
	 */
	public static function get_uploads_baseurl() {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		if ( ! is_array( $upload_dir ) || ! empty( $upload_dir['error'] ) || empty( $upload_dir['baseurl'] ) ) {
			return false;
		}

		return untrailingslashit( (string) $upload_dir['baseurl'] );
	}

	/**
	 * Whether an existing path resolves inside the uploads root.
	 *
	 * @since 4.3.0
	 * @param string $path Candidate filesystem path.
	 * @param string $root Uploads basedir.
	 * @return bool True when the canonical path is within the uploads root.
	 */
	public static function is_existing_path_within( $path, $root ) {
		return false !== self::get_canonical_path_within( $path, $root );
	}

	/**
	 * Return the canonical absolute path when it resolves inside the uploads root.
	 *
	 * @since 4.3.0
	 * @param string $path Candidate filesystem path.
	 * @param string $root Uploads basedir.
	 * @return string|false Canonical path or false when outside/unavailable.
	 */
	public static function get_canonical_path_within( $path, $root ) {
		if ( ! is_string( $path ) || $path === '' || ! is_string( $root ) || $root === '' ) {
			return false;
		}

		if ( ! file_exists( $path ) || ! file_exists( $root ) ) {
			return false;
		}

		$real_path = realpath( $path );
		$real_root = realpath( $root );

		if ( false === $real_path || false === $real_root ) {
			return false;
		}

		$normalized_path = wp_normalize_path( $real_path );
		$normalized_root = untrailingslashit( wp_normalize_path( $real_root ) );

		if ( $normalized_path === $normalized_root ) {
			return $normalized_path;
		}

		if ( 0 !== strpos( $normalized_path, $normalized_root . '/' ) ) {
			return false;
		}

		return $normalized_path;
	}

	/**
	 * Relative path under uploads for an existing contained file/directory.
	 *
	 * @since 4.3.0
	 * @param string $path Absolute filesystem path.
	 * @param string $root Uploads basedir.
	 * @return string|false Relative path without leading slash, or false.
	 */
	public static function get_relative_path_within( $path, $root ) {
		$canonical = self::get_canonical_path_within( $path, $root );
		if ( false === $canonical ) {
			return false;
		}

		$normalized_root = untrailingslashit( wp_normalize_path( (string) realpath( $root ) ) );
		if ( $canonical === $normalized_root ) {
			return '';
		}

		return ltrim( substr( $canonical, strlen( $normalized_root ) ), '/' );
	}

	/**
	 * Whether a destination path would stay inside uploads when written.
	 *
	 * Validates the parent directory (must exist) rather than the destination file.
	 *
	 * @since 4.3.0
	 * @param string $destination_path Absolute destination path (may not exist yet).
	 * @param string $root             Uploads basedir.
	 * @return bool True when the parent directory is within uploads.
	 */
	public static function is_destination_within( $destination_path, $root ) {
		if ( ! is_string( $destination_path ) || $destination_path === '' ) {
			return false;
		}

		$parent = dirname( $destination_path );
		return self::is_existing_path_within( $parent, $root );
	}

	/**
	 * Convert a local uploads URL into a filesystem path when it matches base URL.
	 *
	 * Compares scheme, host, and port via wp_parse_url(), then enforces path
	 * boundary and uploads containment via realpath().
	 *
	 * @since 4.3.0
	 * @param string $url      Absolute URL.
	 * @param string $base_url Uploads base URL.
	 * @param string $base_dir Uploads base directory.
	 * @return string|false Absolute path or false when conversion is unsafe/invalid.
	 */
	public static function local_upload_url_to_path( $url, $base_url, $base_dir ) {
		if ( ! is_string( $url ) || $url === '' || ! is_string( $base_url ) || $base_url === '' || ! is_string( $base_dir ) || $base_dir === '' ) {
			return false;
		}

		$url_parts = self::parse_url_parts( $url );
		$base_parts = self::parse_url_parts( $base_url );
		if ( false === $url_parts || false === $base_parts ) {
			return false;
		}

		if ( ! self::url_origins_match( $url_parts, $base_parts ) ) {
			return false;
		}

		$base_path = isset( $base_parts['path'] ) ? untrailingslashit( $base_parts['path'] ) : '';
		$url_path = isset( $url_parts['path'] ) ? (string) $url_parts['path'] : '';

		if ( $base_path !== '' ) {
			if ( $url_path !== $base_path && 0 !== strpos( $url_path, $base_path . '/' ) ) {
				return false;
			}
			$relative = ( $url_path === $base_path ) ? '' : ltrim( substr( $url_path, strlen( $base_path ) ), '/' );
		} else {
			$relative = ltrim( $url_path, '/' );
		}

		if ( $relative === '' || false !== strpos( $relative, '..' ) ) {
			return false;
		}

		// Decode percent-encoding before joining so containment sees the real path.
		$relative = rawurldecode( $relative );
		if ( false !== strpos( $relative, '..' ) ) {
			return false;
		}

		$path = trailingslashit( wp_normalize_path( $base_dir ) ) . $relative;
		$path = wp_normalize_path( $path );

		return self::get_canonical_path_within( $path, $base_dir );
	}

	/**
	 * Parse a URL into components via wp_parse_url when available.
	 *
	 * @since 4.3.0
	 * @param string $url URL string.
	 * @return array|false Parsed parts or false.
	 */
	private static function parse_url_parts( $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed = wp_parse_url( $url );
		} else {
			$parsed = parse_url( $url );
		}

		return is_array( $parsed ) ? $parsed : false;
	}

	/**
	 * Whether two parsed URLs share the same origin (scheme, host, port).
	 *
	 * @since 4.3.0
	 * @param array $left  Parsed URL parts.
	 * @param array $right Parsed URL parts.
	 * @return bool
	 */
	private static function url_origins_match( array $left, array $right ) {
		$left_scheme = isset( $left['scheme'] ) ? strtolower( (string) $left['scheme'] ) : '';
		$right_scheme = isset( $right['scheme'] ) ? strtolower( (string) $right['scheme'] ) : '';
		if ( $left_scheme === '' || $left_scheme !== $right_scheme ) {
			return false;
		}

		$left_host = isset( $left['host'] ) ? strtolower( (string) $left['host'] ) : '';
		$right_host = isset( $right['host'] ) ? strtolower( (string) $right['host'] ) : '';
		if ( $left_host === '' || $left_host !== $right_host ) {
			return false;
		}

		$left_port = isset( $left['port'] ) ? (int) $left['port'] : self::default_port_for_scheme( $left_scheme );
		$right_port = isset( $right['port'] ) ? (int) $right['port'] : self::default_port_for_scheme( $right_scheme );

		return $left_port === $right_port;
	}

	/**
	 * Default TCP port for a URL scheme.
	 *
	 * @since 4.3.0
	 * @param string $scheme URL scheme.
	 * @return int
	 */
	private static function default_port_for_scheme( $scheme ) {
		if ( 'https' === $scheme ) {
			return 443;
		}
		if ( 'http' === $scheme ) {
			return 80;
		}
		return 0;
	}
}
