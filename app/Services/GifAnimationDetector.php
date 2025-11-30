<?php
/**
 * GIF animation detector service.
 *
 * @package FluxMedia
 * @since 2.0.1
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\LoggerInterface;

/**
 * Service to detect if a GIF file is animated.
 *
 * @since 2.0.1
 */
class GifAnimationDetector {

	/**
	 * Logger instance.
	 *
	 * @since 2.0.1
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.0.1
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Check if a GIF file is animated using Imagick.
	 *
	 * @since 2.0.1
	 * @param string $file_path Path to the GIF file.
	 * @return bool True if animated, false otherwise.
	 */
	public function is_animated_with_imagick( $file_path ) {
		if ( ! extension_loaded( 'imagick' ) ) {
			return false;
		}

		try {
			$imagick = new \Imagick( $file_path );
			$frame_count = $imagick->getNumberImages();
			$imagick->clear();
			$imagick->destroy();

			return $frame_count > 1;
		} catch ( \Exception $e ) {
			$this->logger->warning( "Failed to check animation with Imagick for {$file_path}: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Check if a GIF file is animated by reading the file binary.
	 *
	 * This method reads the GIF file to check for multiple image descriptors.
	 * An animated GIF contains multiple image descriptors (0x21 0xF9 pattern).
	 *
	 * @since 2.0.1
	 * @param string $file_path Path to the GIF file.
	 * @return bool True if animated, false otherwise.
	 */
	public function is_animated_by_file_read( $file_path ) {
		// Initialize WordPress filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			$this->logger->warning( "WordPress filesystem not available for GIF animation detection: {$file_path}" );
			return false;
		}

		if ( ! $wp_filesystem->exists( $file_path ) ) {
			return false;
		}

		// Check MIME type first
		$image_info = getimagesize( $file_path );
		if ( ! $image_info || $image_info['mime'] !== 'image/gif' ) {
			return false;
		}

		// Check file size to avoid reading extremely large files into memory
		// Most GIFs are reasonable in size, but we'll cap at 50MB for safety
		$file_size = $wp_filesystem->size( $file_path );
		if ( $file_size === false || $file_size > 50 * 1024 * 1024 ) {
			$this->logger->warning( "GIF file too large for animation detection: {$file_path} ({$file_size} bytes)" );
			return false;
		}

		// Read file contents using WordPress filesystem API
		$file_contents = $wp_filesystem->get_contents( $file_path );
		if ( $file_contents === false ) {
			$this->logger->warning( "Failed to read GIF file for animation detection: {$file_path}" );
			return false;
		}

		// Verify GIF header
		if ( strlen( $file_contents ) < 13 || substr( $file_contents, 0, 3 ) !== 'GIF' ) {
			return false;
		}

		// Count image descriptors (0x21 0xF9 pattern indicates graphic control extension)
		// Animated GIFs have multiple image descriptors
		// Look for image separator (0x2C) which indicates a new image frame
		$image_descriptor_count = 0;
		$length = strlen( $file_contents );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = ord( $file_contents[ $i ] );

			// Look for image separator (0x2C) which indicates a new image frame
			if ( $byte === 0x2C ) {
				$image_descriptor_count++;
				// If we find more than one image descriptor, it's animated
				if ( $image_descriptor_count > 1 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if a GIF file is animated.
	 *
	 * Tries Imagick first (more reliable), falls back to file reading.
	 *
	 * @since 2.0.1
	 * @param string $file_path Path to the GIF file.
	 * @return bool True if animated, false otherwise.
	 */
	public function is_animated( $file_path ) {
		// Try Imagick first if available
		if ( extension_loaded( 'imagick' ) ) {
			$result = $this->is_animated_with_imagick( $file_path );
			if ( $result !== false ) {
				return $result;
			}
		}

		// Fallback to file reading
		return $this->is_animated_by_file_read( $file_path );
	}
}

