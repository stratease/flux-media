<?php
/**
 * Image conversion service with GD/Imagick wrapper.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Services;

use FluxMedia\Utils\Logger;
use FluxMedia\Utils\StructuredLogger;
use FluxMedia\Interfaces\ImageProcessorInterface;
use FluxMedia\Processors\GDProcessor;
use FluxMedia\Processors\ImagickProcessor;

/**
 * Image conversion service that handles WebP and AVIF conversion.
 *
 * @since 1.0.0
 */
class ImageConverter {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Structured logger instance.
	 *
	 * @since 1.0.0
	 * @var StructuredLogger
	 */
	private $structured_logger;

	/**
	 * Image processor instance.
	 *
	 * @since 1.0.0
	 * @var ImageProcessorInterface
	 */
	private $processor;

	/**
	 * Supported image formats.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $supported_formats = [
		'image/jpeg',
		'image/png',
		'image/gif',
		'image/webp',
	];

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->structured_logger = new StructuredLogger( $logger );
		$this->processor = $this->get_available_processor();
	}

	/**
	 * Get the available image processor (Imagick or GD).
	 *
	 * @since 1.0.0
	 * @return ImageProcessorInterface|null The processor instance or null if none available.
	 */
	private function get_available_processor() {
		// Prefer Imagick for better quality and more features.
		if ( class_exists( 'Imagick' ) && extension_loaded( 'imagick' ) ) {
			$imagick = new \Imagick();
			$formats = $imagick->queryFormats();
			
			// Check if Imagick supports WebP and AVIF.
			if ( in_array( 'WEBP', $formats, true ) && in_array( 'AVIF', $formats, true ) ) {
				$this->structured_logger->log_operation_success( 'Image processor initialization', 'Imagick with WebP and AVIF support detected' );
				return new ImagickProcessor( $this->logger );
			} else {
				$missing_formats = [];
				if ( ! in_array( 'WEBP', $formats, true ) ) {
					$missing_formats[] = 'WebP';
				}
				if ( ! in_array( 'AVIF', $formats, true ) ) {
					$missing_formats[] = 'AVIF';
				}
				$this->structured_logger->log_image_format_unsupported( 'Imagick', implode( ', ', $missing_formats ), 'Format not compiled in Imagick' );
			}
		} else {
			if ( ! class_exists( 'Imagick' ) ) {
				$this->structured_logger->log_image_processor_unavailable( 'Imagick', 'Imagick class not found' );
			} elseif ( ! extension_loaded( 'imagick' ) ) {
				$this->structured_logger->log_image_processor_unavailable( 'Imagick', 'Imagick extension not loaded' );
			}
		}

		// Fallback to GD if available.
		if ( extension_loaded( 'gd' ) ) {
			// Check GD version and WebP support.
			$gd_info = gd_info();
			if ( isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'] ) {
				$this->structured_logger->log_operation_success( 'Image processor initialization', 'GD with WebP support detected' );
				return new GDProcessor( $this->logger );
			} else {
				$this->structured_logger->log_image_format_unsupported( 'GD', 'WebP', 'WebP support not compiled in GD' );
			}
		} else {
			$this->structured_logger->log_image_processor_unavailable( 'GD', 'GD extension not loaded' );
		}

		$this->structured_logger->log_image_processor_unavailable( 'All', 'No suitable image processor found. Imagick or GD with WebP support required.' );
		return null;
	}

	/**
	 * Check if image conversion is available.
	 *
	 * @since 1.0.0
	 * @return bool True if conversion is available, false otherwise.
	 */
	public function is_available() {
		return null !== $this->processor;
	}

	/**
	 * Get processor information.
	 *
	 * @since 1.0.0
	 * @return array Processor information.
	 */
	public function get_processor_info() {
		// Always check format support capabilities, regardless of processor availability
		$webp_support = $this->can_convert_to_webp();
		$avif_support = $this->can_convert_to_avif();
		
		// Processor is available if we can convert to at least one format
		$available = $webp_support || $avif_support;
		
		if ( $available && $this->processor ) {
			$processor_info = $this->processor->get_info();
			
			return [
				'available' => true,
				'type' => $processor_info['type'] ?? 'unknown',
				'version' => $processor_info['version'] ?? 'Unknown',
				'webp_support' => $webp_support,
				'avif_support' => $avif_support,
			];
		}
		
		return [
			'available' => false,
			'type' => 'none',
			'webp_support' => false,
			'avif_support' => false,
		];
	}

	/**
	 * Check if we can convert to WebP format.
	 *
	 * @since 1.0.0
	 * @return bool True if WebP conversion is possible, false otherwise.
	 */
	private function can_convert_to_webp() {
		// Check if processor is available
		if ( ! $this->processor ) {
			return false;
		}
		
		// Check processor-specific WebP support
		$processor_info = $this->processor->get_info();
		return $processor_info['webp_support'] ?? false;
	}

	/**
	 * Check if we can convert to AVIF format.
	 *
	 * @since 1.0.0
	 * @return bool True if AVIF conversion is possible, false otherwise.
	 */
	private function can_convert_to_avif() {
		// Check if processor is available
		if ( ! $this->processor ) {
			return false;
		}
		
		// Check processor-specific AVIF support
		$processor_info = $this->processor->get_info();
		return $processor_info['avif_support'] ?? false;
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
		if ( ! $this->processor ) {
			$this->structured_logger->log_image_conversion_failed( $source_path, 'WebP', 'No image processor available' );
			return false;
		}

		$default_options = [
			'quality' => 85,
			'lossless' => false,
		];

		$options = array_merge( $default_options, $options );

		try {
			$result = $this->processor->convert_to_webp( $source_path, $destination_path, $options );
			
			if ( $result ) {
				$this->structured_logger->log_operation_success( 'WebP conversion', "Successfully converted {$source_path}" );
			} else {
				$this->structured_logger->log_image_conversion_failed( $source_path, 'WebP', 'Processor returned false' );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->structured_logger->log_image_conversion_failed( $source_path, 'WebP', $e->getMessage() );
			return false;
		}
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
		if ( ! $this->processor ) {
			$this->structured_logger->log_image_conversion_failed( $source_path, 'AVIF', 'No image processor available' );
			return false;
		}

		$default_options = [
			'quality' => 80,
			'speed' => 6,
		];

		$options = array_merge( $default_options, $options );

		try {
			$result = $this->processor->convert_to_avif( $source_path, $destination_path, $options );
			
			if ( $result ) {
				$this->structured_logger->log_operation_success( 'AVIF conversion', "Successfully converted {$source_path}" );
			} else {
				$this->structured_logger->log_image_conversion_failed( $source_path, 'AVIF', 'Processor returned false' );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->structured_logger->log_image_conversion_failed( $source_path, 'AVIF', $e->getMessage() );
			return false;
		}
	}

	/**
	 * Check if file is a supported image format.
	 *
	 * @since 1.0.0
	 * @param string $file_path File path to check.
	 * @return bool True if supported, false otherwise.
	 */
	public function is_supported_image( $file_path ) {
		$mime_type = wp_check_filetype( $file_path )['type'];
		return in_array( $mime_type, $this->supported_formats, true );
	}

	/**
	 * Get file size reduction percentage.
	 *
	 * @since 1.0.0
	 * @param string $original_path Original file path.
	 * @param string $converted_path Converted file path.
	 * @return float Reduction percentage.
	 */
	public function get_size_reduction( $original_path, $converted_path ) {
		if ( ! file_exists( $original_path ) || ! file_exists( $converted_path ) ) {
			return 0.0;
		}

		$original_size = filesize( $original_path );
		$converted_size = filesize( $converted_path );

		if ( $original_size === 0 ) {
			return 0.0;
		}

		return ( ( $original_size - $converted_size ) / $original_size ) * 100;
	}

	/**
	 * Convert image using hybrid approach (both WebP and AVIF).
	 * Creates both formats for optimal performance and compatibility.
	 *
	 * @since 1.0.0
	 * @param string $source_path Source image path.
	 * @param string $webp_path Destination WebP path.
	 * @param string $avif_path Destination AVIF path.
	 * @param array  $webp_options WebP conversion options.
	 * @param array  $avif_options AVIF conversion options.
	 * @return array Results array with 'webp' and 'avif' keys.
	 */
	public function convert_hybrid( $source_path, $webp_path, $avif_path, $webp_options = [], $avif_options = [] ) {
		$results = [
			'webp' => false,
			'avif' => false,
		];

		// Convert to WebP
		$results['webp'] = $this->convert_to_webp( $source_path, $webp_path, $webp_options );
		
		// Convert to AVIF
		$results['avif'] = $this->convert_to_avif( $source_path, $avif_path, $avif_options );

		// Log hybrid conversion results
		if ( $results['webp'] && $results['avif'] ) {
			$this->logger->info( "Successfully converted image using hybrid approach: {$source_path}" );
		} elseif ( $results['webp'] || $results['avif'] ) {
			$this->logger->warning( "Partial hybrid conversion success: {$source_path} (WebP: " . ( $results['webp'] ? 'success' : 'failed' ) . ", AVIF: " . ( $results['avif'] ? 'success' : 'failed' ) . ")" );
		} else {
			$this->logger->error( "Hybrid conversion failed for both formats: {$source_path}" );
		}

		return $results;
	}

	/**
	 * Process image on upload - convert to WebP/AVIF while retaining original.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array Conversion results.
	 */
	public function process_uploaded_image( $attachment_id ) {
		$results = [
			'success' => false,
			'converted_formats' => [],
			'errors' => [],
		];

		// Get attachment file path
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$results['errors'][] = 'Attachment file not found';
			return $results;
		}

		// Check if image is supported
		if ( ! $this->is_supported_image( $file_path ) ) {
			$results['errors'][] = 'Unsupported image format';
			return $results;
		}

		// Get upload directory info
		$upload_dir = wp_upload_dir();
		$file_info = pathinfo( $file_path );
		$file_dir = $file_info['dirname'];
		$file_name = $file_info['filename'];

		// Get plugin options
		$options = get_option( 'flux_media_options', [] );
		$hybrid_approach = $options['hybrid_approach'] ?? true;
		$image_formats = $options['image_formats'] ?? ['webp', 'avif'];
		$webp_quality = $options['image_webp_quality'] ?? 85;

		// Check if auto-conversion is enabled
		if ( ! ( $options['image_auto_convert'] ?? true ) ) {
			$results['errors'][] = 'Auto-conversion is disabled';
			return $results;
		}

		// Process based on settings
		if ( $hybrid_approach && in_array( 'webp', $image_formats, true ) && in_array( 'avif', $image_formats, true ) ) {
			// Hybrid approach - create both WebP and AVIF
			$webp_path = $file_dir . '/' . $file_name . '.webp';
			$avif_path = $file_dir . '/' . $file_name . '.avif';

			$conversion_results = $this->convert_hybrid(
				$file_path,
				$webp_path,
				$avif_path,
				['quality' => $webp_quality],
				['quality' => max( 60, $webp_quality - 10 )] // AVIF typically needs lower quality for similar file size
			);

			if ( $conversion_results['webp'] ) {
				$results['converted_formats'][] = 'webp';
			}
			if ( $conversion_results['avif'] ) {
				$results['converted_formats'][] = 'avif';
			}

		} else {
			// Individual format conversion
			foreach ( $image_formats as $format ) {
				$destination_path = $file_dir . '/' . $file_name . '.' . $format;
				$conversion_options = ['quality' => $webp_quality];

				$success = false;
				if ( 'webp' === $format ) {
					$success = $this->convert_to_webp( $file_path, $destination_path, $conversion_options );
				} elseif ( 'avif' === $format ) {
					$conversion_options['quality'] = max( 60, $webp_quality - 10 );
					$success = $this->convert_to_avif( $file_path, $destination_path, $conversion_options );
				}

				if ( $success ) {
					$results['converted_formats'][] = $format;
				}
			}
		}

		// Update results
		$results['success'] = ! empty( $results['converted_formats'] );

		// Store conversion metadata
		if ( $results['success'] ) {
			update_post_meta( $attachment_id, '_flux_media_converted_formats', $results['converted_formats'] );
			update_post_meta( $attachment_id, '_flux_media_conversion_date', current_time( 'mysql' ) );
		}

		return $results;
	}
}
