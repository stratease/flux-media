<?php
/**
 * Imagick image processor implementation.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\ImageProcessorInterface;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
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
	 * @var Logger
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
	 * Multi-frame detector instance.
	 *
	 * @since 4.3.0
	 * @var MultiFrameDetector
	 */
	private $multi_frame_detector;

	/**
	 * HEIF capability probe.
	 *
	 * @since 4.3.0
	 * @var HeifCapabilityProbe
	 */
	private $heif_probe;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->imagick = new Imagick();
		$this->multi_frame_detector = new MultiFrameDetector( $logger );
		$this->heif_probe = new HeifCapabilityProbe();
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
			'animated_gif_support' => $this->supports_animated_gif(),
			'multi_frame_support' => $this->supports_multi_frame(),
			'heic_support' => $this->heif_probe->supports_static_heic(),
			'animated_heic_support' => $this->heif_probe->supports_animated_heic( in_array( 'WEBP', $formats, true ) ),
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
			// HEIF sequences preserve animation only via FFmpeg (HeifAnimationPolicy);
			// Imagick always writes a static first frame for those sources.
			$is_multi_frame = $this->is_multi_frame( $source_path )
				&& ! $this->multi_frame_detector->is_heif_sequence( $source_path );

			if ( $this->multi_frame_detector->is_heif_sequence( $source_path ) ) {
				$image->setIteratorIndex( 0 );
			}

			if ( $is_multi_frame ) {
				$result = $this->convert_multi_frame_to_webp( $image, $source_path, $destination_path, $options );
			} else {
				$result = $this->convert_static_to_webp( $image, $destination_path, $options );
			}

			$image->clear();
			$image->destroy();

			if ( $result === false ) {
				$this->logger->error( "Imagick writeImage() failed for WebP conversion to: {$destination_path}" );
				return false;
			}

			if ( ! file_exists( $destination_path ) ) {
				$this->logger->error( "WebP file was not created at: {$destination_path}" );
				return false;
			}

			if ( $is_multi_frame ) {
				$this->logger->debug( "Animated WebP conversion successful: {$destination_path}" );
			}

			return $result;
		} catch ( ImagickException $e ) {
			$this->logger->error( "Imagick WebP conversion failed: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Convert a static image to WebP.
	 *
	 * @since 4.3.0
	 * @param Imagick $image            Loaded Imagick instance.
	 * @param string  $destination_path Destination path.
	 * @param array   $options          Conversion options.
	 * @return bool
	 */
	private function convert_static_to_webp( Imagick $image, $destination_path, $options ) {
		$image->setImageFormat( 'WEBP' );
		$image->setImageCompressionQuality( $options['webp_quality'] );

		if ( $options['lossless'] ?? false ) {
			$image->setOption( 'webp:lossless', 'true' );
		} else {
			$image->setOption( 'webp:method', '4' );
			$image->setOption( 'webp:pass', '6' );
			$image->setOption( 'webp:preprocessing', '1' );
		}

		$image->stripImage();

		return $image->writeImage( $destination_path );
	}

	/**
	 * Convert a multi-frame source to animated WebP.
	 *
	 * @since 4.3.0
	 * @param Imagick $image            Loaded Imagick instance.
	 * @param string  $source_path      Original source path for loop metadata.
	 * @param string  $destination_path Destination path.
	 * @param array   $options          Conversion options.
	 * @return bool
	 */
	private function convert_multi_frame_to_webp( Imagick $image, $source_path, $destination_path, $options ) {
		$image = $image->coalesceImages();

		$original_image = new Imagick( $source_path );
		$loop_count = $original_image->getImageIterations();
		$original_image->clear();
		$original_image->destroy();

		$resize_width = $options['resize_width'] ?? null;
		$resize_height = $options['resize_height'] ?? null;

		do {
			if ( $resize_width && $resize_height ) {
				$image->resizeImage( $resize_width, $resize_height, Imagick::FILTER_LANCZOS, 1, true );
			}

			$image->setImageFormat( 'WEBP' );
			$image->setImageCompressionQuality( $options['webp_quality'] );

			if ( $options['lossless'] ?? false ) {
				$image->setOption( 'webp:lossless', 'true' );
			} else {
				$image->setOption( 'webp:method', '4' );
				$image->setOption( 'webp:pass', '6' );
				$image->setOption( 'webp:preprocessing', '1' );
			}

			$image->stripImage();
		} while ( $image->nextImage() );

		$image->setFirstIterator();
		$image->setImageIterations( $loop_count );

		return $image->writeImages( $destination_path, true );
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
			// HEIF sequences never use Imagick animated AVIF; always static first frame.
			$is_multi_frame = $this->is_multi_frame( $source_path )
				&& ! $this->multi_frame_detector->is_heif_sequence( $source_path );
			$version_info = $this->get_imagick_version_info();
			$supports_animated_avif = version_compare( $version_info['version'], '7.1.0', '>=' );

			if ( $this->multi_frame_detector->is_heif_sequence( $source_path ) ) {
				$image->setIteratorIndex( 0 );
				$this->logger->warning( "HEIF sequence detected; writing static AVIF (first frame) for: {$source_path}" );
			}

			if ( $is_multi_frame && ! $supports_animated_avif ) {
				$this->logger->warning( "Multi-frame source detected but ImageMagick version {$version_info['version']} does not support animated AVIF. Converting to static AVIF." );
				$is_multi_frame = false;
			}

			if ( $is_multi_frame ) {
				$result = $this->convert_multi_frame_to_avif( $image, $source_path, $destination_path, $options, $version_info );
			} else {
				$result = $this->convert_static_to_avif( $image, $destination_path, $options, $version_info );
			}

			$image->clear();
			$image->destroy();

			if ( $result === false ) {
				$this->logger->error( "Imagick writeImage() failed for AVIF conversion to: {$destination_path}" );
				return false;
			}

			if ( ! file_exists( $destination_path ) ) {
				$this->logger->error( "AVIF file was not created at: {$destination_path}" );
				return false;
			}

			if ( $is_multi_frame ) {
				$this->logger->debug( "Animated AVIF conversion successful: {$destination_path}" );
			} else {
				$this->logger->debug( "AVIF conversion successful: {$destination_path}" );
			}

			return $result;
		} catch ( ImagickException $e ) {
			$this->logger->error( "Imagick AVIF conversion failed: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Convert a static image to AVIF.
	 *
	 * @since 4.3.0
	 * @param Imagick $image            Loaded Imagick instance.
	 * @param string  $destination_path Destination path.
	 * @param array   $options          Conversion options.
	 * @param array   $version_info     ImageMagick version info.
	 * @return bool
	 */
	private function convert_static_to_avif( Imagick $image, $destination_path, $options, $version_info ) {
		$quality = $options['avif_quality'] ?? 70;
		$speed = $options['avif_speed'] ?? 6;

		$image->setImageFormat( 'AVIF' );
		$this->logger->debug( "ImageMagick version: {$version_info['version']}, AVIF capabilities: " . json_encode( $version_info['avif_capabilities'] ) );
		$this->apply_avif_settings( $image, $version_info, $quality, $speed );
		$image->stripImage();

		return $image->writeImage( $destination_path );
	}

	/**
	 * Convert a multi-frame source to animated AVIF.
	 *
	 * @since 4.3.0
	 * @param Imagick $image            Loaded Imagick instance.
	 * @param string  $source_path      Original source path for loop metadata.
	 * @param string  $destination_path Destination path.
	 * @param array   $options          Conversion options.
	 * @param array   $version_info     ImageMagick version info.
	 * @return bool
	 */
	private function convert_multi_frame_to_avif( Imagick $image, $source_path, $destination_path, $options, $version_info ) {
		$image = $image->coalesceImages();

		$original_image = new Imagick( $source_path );
		$loop_count = $original_image->getImageIterations();
		$original_image->clear();
		$original_image->destroy();

		$quality = $options['avif_quality'] ?? 70;
		$speed = $options['avif_speed'] ?? 6;
		$resize_width = $options['resize_width'] ?? null;
		$resize_height = $options['resize_height'] ?? null;

		$this->logger->debug( "ImageMagick version: {$version_info['version']}, AVIF capabilities: " . json_encode( $version_info['avif_capabilities'] ) );

		do {
			if ( $resize_width && $resize_height ) {
				$image->resizeImage( $resize_width, $resize_height, Imagick::FILTER_LANCZOS, 1, true );
			}

			$image->setImageFormat( 'AVIF' );
			$this->apply_avif_settings( $image, $version_info, $quality, $speed );
			$image->stripImage();
		} while ( $image->nextImage() );

		$image->setFirstIterator();
		$image->setImageIterations( $loop_count );

		return $image->writeImages( $destination_path, true );
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
	 * Check if processor supports animated GIF conversion.
	 *
	 * GIF support is built into ImageMagick by default and does not require
	 * additional configure options. This method checks if the 'GIF' format
	 * is available in ImageMagick's queryFormats() list.
	 *
	 * Requirements:
	 * - ImageMagick installed (with default GIF support - no special configure needed)
	 * - Imagick PHP extension installed and enabled
	 *
	 * @since TBD
	 * @return bool True if Imagick supports GIF format.
	 */
	public function supports_animated_gif() {
		$formats = $this->imagick->queryFormats();
		return in_array( 'GIF', $formats, true );
	}

	/**
	 * Check if processor supports multi-frame conversion.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function supports_multi_frame() {
		return $this->supports_animated_gif()
			|| $this->heif_probe->supports_animated_heic( $this->supports_webp() );
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
