<?php
/**
 * GIF animation detector service.
 *
 * @package FluxMedia
 * @since TBD
 */

namespace FluxMedia\App\Services;

/**
 * Service to detect if a GIF file is animated.
 *
 * @since TBD
 */
class GifAnimationDetector {

	/**
	 * Logger instance.
	 *
	 * @since TBD
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since TBD
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Check if a GIF file is animated using Imagick.
	 *
	 * @since TBD
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
	 * @since TBD
	 * @param string $file_path Path to the GIF file.
	 * @return bool True if animated, false otherwise.
	 */
	public function is_animated_by_file_read( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Check MIME type first
		$image_info = getimagesize( $file_path );
		if ( ! $image_info || $image_info['mime'] !== 'image/gif' ) {
			return false;
		}

		// Read file to check for multiple image descriptors
		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return false;
		}

		// Read first 13 bytes (GIF header)
		$header = fread( $handle, 13 );
		if ( substr( $header, 0, 3 ) !== 'GIF' ) {
			fclose( $handle );
			return false;
		}

		// Count image descriptors (0x21 0xF9 pattern indicates graphic control extension)
		// Animated GIFs have multiple image descriptors
		$image_descriptor_count = 0;
		$chunk_size = 8192;
		$previous_byte = null;

		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, $chunk_size );
			if ( $chunk === false ) {
				break;
			}

			$length = strlen( $chunk );
			for ( $i = 0; $i < $length; $i++ ) {
				$byte = ord( $chunk[ $i ] );

				// Look for image separator (0x2C) which indicates a new image frame
				if ( $byte === 0x2C ) {
					$image_descriptor_count++;
					// If we find more than one image descriptor, it's animated
					if ( $image_descriptor_count > 1 ) {
						fclose( $handle );
						return true;
					}
				}

				$previous_byte = $byte;
			}
		}

		fclose( $handle );
		return false;
	}

	/**
	 * Check if a GIF file is animated.
	 *
	 * Tries Imagick first (more reliable), falls back to file reading.
	 *
	 * @since TBD
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

