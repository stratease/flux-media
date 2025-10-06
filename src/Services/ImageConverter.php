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
use FluxMedia\Interfaces\Converter;
use FluxMedia\Interfaces\ImageProcessorInterface;
use FluxMedia\Processors\GDProcessor;
use FluxMedia\Processors\ImagickProcessor;
use FluxMedia\Services\QuotaManager;
use Imagick;

/**
 * Image conversion service that handles WebP and AVIF conversion.
 *
 * @since 1.0.0
 */
class ImageConverter implements Converter {

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
     * Quota manager instance.
     *
     * @since 1.0.0
     * @var QuotaManager
     */
    private $quota_manager;

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
     * Source file path for fluent interface.
     *
     * @since 1.0.0
     * @var string|null
     */
    private $source_path;

    /**
     * Destination file path for fluent interface.
     *
     * @since 1.0.0
     * @var string|null
     */
    private $destination_path;

    /**
     * Conversion options for fluent interface.
     *
     * @since 1.0.0
     * @var array
     */
    private $options = [];

    /**
     * Error messages for fluent interface.
     *
     * @since 1.0.0
     * @var array
     */
    private $errors = [];

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param Logger $logger Logger instance.
     * @param QuotaManager $quota_manager Quota manager instance.
     */
    public function __construct( Logger $logger, QuotaManager $quota_manager ) {
        $this->logger = $logger;
        $this->structured_logger = new StructuredLogger( $logger );
        $this->quota_manager = $quota_manager;
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
			$imagick = new Imagick();
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

		// Create destination paths for all requested formats
		$destination_paths = [];
		foreach ( $image_formats as $format ) {
			$destination_paths[ $format ] = $file_dir . '/' . $file_name . '.' . $format;
		}

		// Process based on settings
		if ( $hybrid_approach && in_array( 'webp', $image_formats, true ) && in_array( 'avif', $image_formats, true ) ) {
			// Hybrid approach - create both WebP and AVIF
			$conversion_results = $this->convert_hybrid(
				$file_path,
				$destination_paths['webp'],
				$destination_paths['avif'],
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
				$conversion_options = ['quality' => $webp_quality];

				$success = false;
				if ( 'webp' === $format ) {
					$success = $this->convert_to_webp( $file_path, $destination_paths[ $format ], $conversion_options );
				} elseif ( 'avif' === $format ) {
					$conversion_options['quality'] = max( 60, $webp_quality - 10 );
					$success = $this->convert_to_avif( $file_path, $destination_paths[ $format ], $conversion_options );
				}

				if ( $success ) {
					$results['converted_formats'][] = $format;
				}
			}
		}

		// Update results
		$results['success'] = ! empty( $results['converted_formats'] );

		// Store conversion metadata and track quota usage
		if ( $results['success'] ) {
			// Record quota usage for each successful conversion
			$converted_count = count( $results['converted_formats'] );
			for ( $i = 0; $i < $converted_count; $i++ ) {
				$this->quota_manager->record_usage( 'image' );
			}
			
			update_post_meta( $attachment_id, '_flux_media_converted_formats', $results['converted_formats'] );
			update_post_meta( $attachment_id, '_flux_media_conversion_date', current_time( 'mysql' ) );
			
			// Store converted file paths for later reference (reuse the same paths)
			$converted_files = [];
			foreach ( $results['converted_formats'] as $format ) {
				$converted_files[ $format ] = $destination_paths[ $format ];
			}
			update_post_meta( $attachment_id, '_flux_media_converted_files', $converted_files );
			
			// Log quota usage
			$this->logger->info( "Image conversion quota: {$converted_count} images used for attachment {$attachment_id}" );
		}

		return $results;
	}

	/**
	 * Get converted file paths for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return array Array of format => file_path mappings.
	 */
	public function get_converted_files( $attachment_id ) {
		return get_post_meta( $attachment_id, '_flux_media_converted_files', true ) ?: [];
	}

	/**
	 * Get converted file path for a specific format.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id WordPress attachment ID.
	 * @param string $format Target format (webp, avif).
	 * @return string|null File path or null if not found.
	 */
	public function get_converted_file_path( $attachment_id, $format ) {
		$converted_files = $this->get_converted_files( $attachment_id );
		return $converted_files[ $format ] ?? null;
	}

	/**
	 * Check if attachment has converted files.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id WordPress attachment ID.
	 * @return bool True if converted files exist, false otherwise.
	 */
	public function has_converted_files( $attachment_id ) {
		$converted_files = $this->get_converted_files( $attachment_id );
		return ! empty( $converted_files );
	}

	// ===== Converter Interface Implementation =====

	/**
	 * Set the source file path.
	 *
	 * @since 1.0.0
	 * @param string $source_path Source file path.
	 * @return Converter Fluent interface.
	 */
	public function from( $source_path ) {
		$this->source_path = $source_path;
		return $this;
	}

	/**
	 * Set the destination file path.
	 *
	 * @since 1.0.0
	 * @param string $destination_path Destination file path.
	 * @return Converter Fluent interface.
	 */
	public function to( $destination_path ) {
		$this->destination_path = $destination_path;
		return $this;
	}

	/**
	 * Set conversion options.
	 *
	 * @since 1.0.0
	 * @param array $options Conversion options.
	 * @return Converter Fluent interface.
	 */
	public function with_options( $options ) {
		$this->options = array_merge( $this->options, $options );
		return $this;
	}

	/**
	 * Set a specific option.
	 *
	 * @since 1.0.0
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 * @return Converter Fluent interface.
	 */
	public function set_option( $key, $value ) {
		$this->options[ $key ] = $value;
		return $this;
	}

	/**
	 * Perform the conversion using fluent interface.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public function convert() {
		// Reset errors
		$this->errors = [];

		// Validate inputs
		if ( ! $this->validate_inputs() ) {
			return false;
		}

		// Determine target format from destination path
		$target_format = $this->get_target_format();
		if ( ! $target_format ) {
			$this->add_error( 'Unable to determine target format from destination path' );
			return false;
		}

		// Perform conversion based on format
		if ( Converter::FORMAT_WEBP === $target_format ) {
			return $this->convert_to_webp( $this->source_path, $this->destination_path, $this->options );
		} elseif ( Converter::FORMAT_AVIF === $target_format ) {
			return $this->convert_to_avif( $this->source_path, $this->destination_path, $this->options );
		}

		$this->add_error( "Unsupported target format: {$target_format}" );
		return false;
	}

	/**
	 * Get the last error message.
	 *
	 * @since 1.0.0
	 * @return string|null Error message or null if no error.
	 */
	public function get_last_error() {
		return ! empty( $this->errors ) ? end( $this->errors ) : null;
	}

	/**
	 * Get all error messages.
	 *
	 * @since 1.0.0
	 * @return array Array of error messages.
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Check if conversion is supported.
	 *
	 * @since 1.0.0
	 * @param string $format Target format.
	 * @return bool True if supported, false otherwise.
	 */
	public function is_format_supported( $format ) {
		return in_array( $format, [ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ], true );
	}

	/**
	 * Get supported formats for this converter.
	 *
	 * @since 1.0.0
	 * @return array Array of supported formats.
	 */
	public function get_supported_formats() {
		return [ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ];
	}

	/**
	 * Get converter type.
	 *
	 * @since 1.0.0
	 * @return string Converter type constant.
	 */
	public function get_type() {
		return Converter::TYPE_IMAGE;
	}

	/**
	 * Reset the converter state.
	 *
	 * @since 1.0.0
	 * @return Converter Fluent interface.
	 */
	public function reset() {
		$this->source_path = null;
		$this->destination_path = null;
		$this->options = [];
		$this->errors = [];
		return $this;
	}

	/**
	 * Validate input parameters for fluent interface.
	 *
	 * @since 1.0.0
	 * @return bool True if valid, false otherwise.
	 */
	private function validate_inputs() {
		if ( empty( $this->source_path ) ) {
			$this->add_error( 'Source path is required' );
			return false;
		}

		if ( ! file_exists( $this->source_path ) ) {
			$this->add_error( "Source file does not exist: {$this->source_path}" );
			return false;
		}

		if ( empty( $this->destination_path ) ) {
			$this->add_error( 'Destination path is required' );
			return false;
		}

		// Check if destination directory exists and is writable
		$destination_dir = dirname( $this->destination_path );
		if ( ! is_dir( $destination_dir ) ) {
			$this->add_error( "Destination directory does not exist: {$destination_dir}" );
			return false;
		}

		if ( ! is_writable( $destination_dir ) ) {
			$this->add_error( "Destination directory is not writable: {$destination_dir}" );
			return false;
		}

		return true;
	}

	/**
	 * Add an error message.
	 *
	 * @since 1.0.0
	 * @param string $message Error message.
	 * @return void
	 */
	private function add_error( $message ) {
		$this->errors[] = $message;
	}

	/**
	 * Get target format from destination path.
	 *
	 * @since 1.0.0
	 * @return string|null Target format or null if unable to determine.
	 */
	private function get_target_format() {
		$extension = strtolower( pathinfo( $this->destination_path, PATHINFO_EXTENSION ) );
		
		switch ( $extension ) {
			case 'webp':
				return Converter::FORMAT_WEBP;
			case 'avif':
				return Converter::FORMAT_AVIF;
			default:
				return null;
		}
	}
}
