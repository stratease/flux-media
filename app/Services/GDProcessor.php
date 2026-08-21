<?php
/**
 * GD image processor implementation.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\ImageProcessorInterface;
use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * GD-based image processor with WebP support.
 *
 * @since 0.1.0
 */
class GDProcessor implements ImageProcessorInterface {

	/**
	 * Logger instance.
	 *
	 * @since 0.1.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Multi-frame detector instance.
	 *
	 * @since 4.3.0
	 * @var MultiFrameDetector
	 */
	private $multi_frame_detector;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->multi_frame_detector = new MultiFrameDetector( $logger );
	}

	/**
	 * Get processor information.
	 *
	 * @since 0.1.0
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
			'animated_gif_support' => false, // GD cannot preserve animation.
			'multi_frame_support' => false,
			'heic_support' => false,
			'animated_heic_support' => false,
		];
	}

	/**
	 * Convert image to WebP format.
	 *
	 * @since 0.1.0
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

		// Check if this is a multi-frame source and warn.
		if ( $this->is_multi_frame( $source_path ) ) {
			$this->logger->warning( "Multi-frame source detected: {$source_path}. GD cannot preserve animation. Consider using Imagick." );
		}

		$image = $this->load_image( $source_path );
		if ( ! $image ) {
			$this->logger->error( "Failed to load image: {$source_path}" );
			return false;
		}

		$quality = $options['webp_quality'];
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
	 * @since 0.1.0
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

		// Check if this is a multi-frame source and warn.
		if ( $this->is_multi_frame( $source_path ) ) {
			$this->logger->warning( "Multi-frame source detected: {$source_path}. GD cannot preserve animation. Consider using Imagick." );
		}

		$image = $this->load_image( $source_path );
		if ( ! $image ) {
			$this->logger->error( "Failed to load image: {$source_path}" );
			return false;
		}

		$quality = $options['avif_quality'];
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
	 * @since 0.1.0
	 * @return bool True if WebP is supported, false otherwise.
	 */
	public function supports_webp() {
		return function_exists( 'imagewebp' );
	}

	/**
	 * Check if processor supports AVIF.
	 *
	 * @since 0.1.0
	 * @return bool True if AVIF is supported, false otherwise.
	 */
	public function supports_avif() {
		return function_exists( 'imageavif' );
	}

	/**
	 * Check if processor supports animated GIF conversion.
	 *
	 * @since TBD
	 * @return bool Always false - GD cannot preserve animation.
	 */
	public function supports_animated_gif() {
		return false; // GD cannot preserve animation.
	}

	/**
	 * Check if processor supports multi-frame conversion.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function supports_multi_frame() {
		return false;
	}

	/**
	 * Check if a source file contains multiple frames.
	 *
	 * @since 4.3.0
	 * @param string $file_path Path to the source file.
	 * @return bool
	 */
	public function is_multi_frame( $file_path ) {
		return $this->multi_frame_detector->is_multi_frame( $file_path );
	}

	/**
	 * Check if a GIF file is animated.
	 *
	 * @since TBD
	 * @param string $file_path Path to the GIF file.
	 * @return bool True if animated, false otherwise.
	 */
	public function is_animated_gif( $file_path ) {
		return $this->is_multi_frame( $file_path );
	}

	/**
	 * Load image from file using appropriate GD function.
	 *
	 * @since 0.1.0
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
