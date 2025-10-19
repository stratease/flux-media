<?php
/**
 * Imagick image processor implementation.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\ImageProcessorInterface;
use FluxMedia\App\Services\LoggerInterface;
use Imagick;
use ImagickException;

/**
 * Imagick-based image processor with high-quality conversion settings.
 *
 * @since 0.1.0
 */
class ImagickProcessor implements ImageProcessorInterface {

	/**
	 * Logger instance.
	 *
	 * @since 0.1.0
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Imagick instance.
	 *
	 * @since 0.1.0
	 * @var Imagick
	 */
	private $imagick;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
		$this->imagick = new Imagick();
	}

	/**
	 * Get processor information.
	 *
	 * @since 0.1.0
	 * @return array Processor information.
	 */
	public function get_info() {
		$formats = $this->imagick->queryFormats();
		$version_info = $this->get_imagick_version_info();
		
		return [
			'available' => true,
			'type' => 'imagick',
			'version' => $this->imagick->getVersion()['versionString'],
			'version_info' => $version_info,
			'webp_support' => in_array( 'WEBP', $formats, true ),
			'avif_support' => in_array( 'AVIF', $formats, true ),
			'avif_capabilities' => $version_info['avif_capabilities'],
			'supported_formats' => $formats,
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
			$this->logger->error( 'Imagick does not support WebP format' );
			return false;
		}

		try {
			$image = new Imagick( $source_path );
			
			// Set optimized WebP options for better compression.
			$image->setImageFormat( 'WEBP' );
			$image->setImageCompressionQuality( $options['webp_quality'] );
			
			// Enable lossless compression if requested.
			if ( $options['lossless'] ?? false ) {
				$image->setOption( 'webp:lossless', 'true' );
			} else {
				// Use optimized WebP options for better compression and smaller file sizes.
				$image->setOption( 'webp:method', '4' ); // Balanced compression method (was 6).
				$image->setOption( 'webp:pass', '6' ); // Fewer passes for faster/smaller files (was 10).
				$image->setOption( 'webp:preprocessing', '1' ); // Less aggressive preprocessing (was 2).
			}

			// Strip metadata for smaller file size.
			$image->stripImage();

			// Write the converted image.
			$result = $image->writeImage( $destination_path );
			$image->clear();
			$image->destroy();

			// Check if writeImage actually succeeded
			if ( $result === false ) {
				$this->logger->error( "Imagick writeImage() failed for WebP conversion to: {$destination_path}" );
				return false;
			}

			// Verify the file was actually created
			if ( ! file_exists( $destination_path ) ) {
				$this->logger->error( "WebP file was not created at: {$destination_path}" );
				return false;
			}

			return $result;
		} catch ( ImagickException $e ) {
			$this->logger->error( "Imagick WebP conversion failed: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Convert image to AVIF format with version-specific optimization.
	 *
	 * @since 0.1.0
	 * @param string $source_path Source image path.
	 * @param string $destination_path Destination path.
	 * @param array  $options Conversion options.
	 * @return bool True on success, false on failure.
	 */
	public function convert_to_avif( $source_path, $destination_path, $options = [] ) {
		if ( ! $this->supports_avif() ) {
			$this->logger->error( 'Imagick does not support AVIF format' );
			return false;
		}

		try {
			$image = new Imagick( $source_path );
			
			// Set AVIF format
			$image->setImageFormat( 'AVIF' );
			
			// Get ImageMagick version and capabilities
			$version_info = $this->get_imagick_version_info();
			$quality = $options['avif_quality'] ?? 70;
			$speed = $options['avif_speed'] ?? 6;
			
			$this->logger->debug( "ImageMagick version: {$version_info['version']}, AVIF capabilities: " . json_encode( $version_info['avif_capabilities'] ) );
			
			// Apply version-specific AVIF settings
			$this->apply_avif_settings( $image, $version_info, $quality, $speed );
			
			// Strip metadata for smaller file size
			$image->stripImage();

			// Write the converted image
			$result = $image->writeImage( $destination_path );
			$image->clear();
			$image->destroy();

			// Check if writeImage actually succeeded
			if ( $result === false ) {
				$this->logger->error( "Imagick writeImage() failed for AVIF conversion to: {$destination_path}" );
				return false;
			}

			// Verify the file was actually created
			if ( ! file_exists( $destination_path ) ) {
				$this->logger->error( "AVIF file was not created at: {$destination_path}" );
				return false;
			}

			$this->logger->debug( "AVIF conversion successful: {$destination_path}" );
			return $result;
		} catch ( ImagickException $e ) {
			$this->logger->error( "Imagick AVIF conversion failed: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Check if processor supports WebP.
	 *
	 * @since 0.1.0
	 * @return bool True if WebP is supported, false otherwise.
	 */
	public function supports_webp() {
		$formats = $this->imagick->queryFormats();
		return in_array( 'WEBP', $formats, true );
	}

	/**
	 * Check if processor supports AVIF.
	 *
	 * @since 0.1.0
	 * @return bool True if AVIF is supported, false otherwise.
	 */
	public function supports_avif() {
		$formats = $this->imagick->queryFormats();
		return in_array( 'AVIF', $formats, true );
	}

	/**
	 * Get ImageMagick version information and AVIF capabilities.
	 *
	 * @since TBD
	 * @return array Version info with capabilities.
	 */
	private function get_imagick_version_info() {
		$version_string = $this->imagick->getVersion();
		$version = 'unknown';
		$avif_capabilities = [
			'supports_crf' => false,
			'supports_speed' => false,
			'version_category' => 'unknown',
		];

		// Extract version number from version string
		if ( preg_match( '/ImageMagick (\d+\.\d+\.\d+)/', $version_string['versionString'], $matches ) ) {
			$version = $matches[1];
			$version_parts = explode( '.', $version );
			$major = (int) $version_parts[0];
			$minor = (int) $version_parts[1];
			$patch = (int) $version_parts[2];

			// Determine version category and capabilities
			if ( $major === 6 ) {
				// ImageMagick 6.x (common with Imagick 3.7.x)
				$avif_capabilities['version_category'] = '6.x';
				$avif_capabilities['supports_crf'] = false;
				$avif_capabilities['supports_speed'] = false;
			} elseif ( $major === 7 ) {
				if ( $minor < 1 ) {
					// ImageMagick 7.0.x (early AVIF support)
					$avif_capabilities['version_category'] = '7.0.x';
					$avif_capabilities['supports_crf'] = false;
					$avif_capabilities['supports_speed'] = false;
				} else {
					// ImageMagick 7.1.0+ (reliable AVIF support)
					$avif_capabilities['version_category'] = '7.1.0+';
					$avif_capabilities['supports_crf'] = true;
					$avif_capabilities['supports_speed'] = true;
				}
			}
		}

		return [
			'version' => $version,
			'version_string' => $version_string['versionString'],
			'avif_capabilities' => $avif_capabilities,
		];
	}

	/**
	 * Apply version-specific AVIF settings based on ImageMagick capabilities.
	 *
	 * @since TBD
	 * @param Imagick $image ImageMagick instance.
	 * @param array   $version_info Version information.
	 * @param int     $quality Quality setting (0-100).
	 * @param int     $speed Speed setting (0-10).
	 * @return void
	 */
	private function apply_avif_settings( $image, $version_info, $quality, $speed ) {
		$capabilities = $version_info['avif_capabilities'];
		$version_category = $capabilities['version_category'];

		$this->logger->debug( "Applying AVIF settings for ImageMagick {$version_category}: quality={$quality}, speed={$speed}" );

		$image->setOption( 'avif:speed', (string) $speed );
		// Apply speed settings (only for versions that support it)
		if ( $capabilities['supports_speed'] ) {
			$this->logger->debug( "Applied avif:speed={$speed} (ImageMagick 7.1.0+)" );
		} else {
			// Older versions - speed setting may be ignored, but set it anyway as fallback
			$this->logger->debug( "Applied avif:speed={$speed} (fallback for older version)" );
		}

		// Apply quality settings based on version capabilities
		if ( $capabilities['supports_crf'] ) {
			// ImageMagick 7.1.0+ - use CRF for precise quality control
			$crf_value = $this->quality_to_crf( $quality );
			$image->setOption( 'avif:crf', (string) $crf_value );
			$this->logger->debug( "Applied avif:crf={$crf_value} (converted from quality={$quality})" );
		} else {
			// Older versions - use setImageCompressionQuality as fallback
			$image->setImageCompressionQuality( $quality );
			$this->logger->debug( "Applied setImageCompressionQuality={$quality} (fallback for older version)" );
		}

		// Apply color space settings (works across all versions)
		$this->apply_avif_color_settings( $image );
	}

	/**
	 * Convert quality setting (0-100) to CRF value (0-63).
	 *
	 * @since TBD
	 * @param int $quality Quality setting (0-100).
	 * @return int CRF value (0-63).
	 */
	private function quality_to_crf( $quality ) {
		// Convert quality (0-100) to CRF (0-63)
		// Higher quality = lower CRF (better quality, larger files)
		// Lower quality = higher CRF (worse quality, smaller files)
		$crf = (int) ( 63 - ( $quality * 0.63 ) );
		
		// Ensure CRF is within valid range
		$crf = max( 0, min( 63, $crf ) );
		
		return $crf;
	}

	/**
	 * Apply AVIF color space settings.
	 *
	 * @since TBD
	 * @param Imagick $image ImageMagick instance.
	 * @return void
	 */
	private function apply_avif_color_settings( $image ) {
		try {
			// Determine color space based on image
			$colorspace = $image->getImageColorspace();
			$colorprim = ( $colorspace === Imagick::COLORSPACE_SRGB ) ? 'bt709' : 'bt2020';
			
			// Apply color space settings
			$image->setOption( 'avif:colorprim', $colorprim );
			$image->setOption( 'avif:transfer', $colorprim );
			$image->setOption( 'avif:colormatrix', $colorprim );
			
			$this->logger->debug( "Applied AVIF color settings: colorprim={$colorprim}" );
		} catch ( ImagickException $e ) {
			// Color settings are optional, log but don't fail
			$this->logger->debug( "Could not apply AVIF color settings: {$e->getMessage()}" );
		}
	}

	/**
	 * Get version-specific AVIF recommendations for current ImageMagick version.
	 *
	 * @since TBD
	 * @return array Version-specific recommendations.
	 */
	public function get_avif_recommendations() {
		$version_info = $this->get_imagick_version_info();
		$version_category = $version_info['avif_capabilities']['version_category'];
		
		$recommendations = \FluxMedia\App\Services\Settings::get_avif_version_recommendations();
		
		return [
			'current_version' => $version_info['version'],
			'version_category' => $version_category,
			'recommendations' => $recommendations[ $version_category ] ?? $recommendations['6.x'],
			'capabilities' => $version_info['avif_capabilities'],
		];
	}
}
