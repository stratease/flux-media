<?php
/**
 * GD image processor implementation.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Processors;

use FluxMedia\Interfaces\ImageProcessorInterface;
use FluxMedia\Interfaces\LoggerInterface;

/**
 * GD-based image processor with WebP support.
 *
 * @since 1.0.0
 */
class GDProcessor implements ImageProcessorInterface {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Get processor information.
	 *
	 * @since 1.0.0
	 * @return array Processor information.
	 */
	public function get_info() {
		$gd_info = gd_info();
		
		return [
			'available' => true,
			'type' => 'gd',
			'version' => $gd_info['GD Version'] ?? 'Unknown',
			'webp_support' => $this->supports_webp(),
			'avif_support' => $this->supports_avif(),
			'jpeg_support' => $gd_info['JPEG Support'] ?? false,
			'png_support' => $gd_info['PNG Support'] ?? false,
			'gif_support' => $gd_info['GIF Read Support'] ?? false,
		];
	}

	/**
	 * Convert image to WebP format.
	 *
	 * @since 1.0.0
	 * @param string $source_path Source image path.
	 * @param string $destination_path Destination path.
	 * @param array  $options Conversion options.
	 * @return bool True on success, false on failure.
	 */
	public function convert_to_webp( $source_path, $destination_path, $options = [] ) {
		if ( ! $this->supports_webp() ) {
			$this->logger->error( 'GD does not support WebP format' );
			return false;
		}

		$image = $this->load_image( $source_path );
		if ( ! $image ) {
			$this->logger->error( "Failed to load image: {$source_path}" );
			return false;
		}

		$quality = $options['quality'];
		$result = imagewebp( $image, $destination_path, $quality );

		// Clean up memory.
		imagedestroy( $image );

		if ( ! $result ) {
			$this->logger->error( "Failed to save WebP image: {$destination_path}" );
		}

		return $result;
	}

	/**
	 * Convert image to AVIF format.
	 *
	 * @since 1.0.0
	 * @param string $source_path Source image path.
	 * @param string $destination_path Destination path.
	 * @param array  $options Conversion options.
	 * @return bool True on success, false on failure.
	 */
	public function convert_to_avif( $source_path, $destination_path, $options = [] ) {
		if ( ! $this->supports_avif() ) {
			$this->logger->error( 'GD does not support AVIF format' );
			return false;
		}

		$image = $this->load_image( $source_path );
		if ( ! $image ) {
			$this->logger->error( "Failed to load image: {$source_path}" );
			return false;
		}

		$quality = $options['quality'];
		$result = imageavif( $image, $destination_path, $quality );

		// Clean up memory.
		imagedestroy( $image );

		if ( ! $result ) {
			$this->logger->error( "Failed to save AVIF image: {$destination_path}" );
		}

		return $result;
	}

	/**
	 * Check if processor supports WebP.
	 *
	 * @since 1.0.0
	 * @return bool True if WebP is supported, false otherwise.
	 */
	public function supports_webp() {
		return function_exists( 'imagewebp' );
	}

	/**
	 * Check if processor supports AVIF.
	 *
	 * @since 1.0.0
	 * @return bool True if AVIF is supported, false otherwise.
	 */
	public function supports_avif() {
		return function_exists( 'imageavif' );
	}

	/**
	 * Load image from file using appropriate GD function.
	 *
	 * @since 1.0.0
	 * @param string $file_path Path to the image file.
	 * @return resource|false GD image resource or false on failure.
	 */
	private function load_image( $file_path ) {
		$image_info = getimagesize( $file_path );
		if ( ! $image_info ) {
			return false;
		}

		$mime_type = $image_info['mime'];

		switch ( $mime_type ) {
			case 'image/jpeg':
				return imagecreatefromjpeg( $file_path );
			case 'image/png':
				return imagecreatefrompng( $file_path );
			case 'image/gif':
				return imagecreatefromgif( $file_path );
			case 'image/webp':
				return imagecreatefromwebp( $file_path );
			default:
				$this->logger->error( "Unsupported image format: {$mime_type}" );
				return false;
		}
	}
}
