<?php
/**
 * Image conversion service with GD/Imagick wrapper.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Services;

use FluxMedia\Interfaces\LoggerInterface;
use FluxMedia\Interfaces\Converter;
use FluxMedia\Interfaces\ImageProcessorInterface;
use FluxMedia\Processors\GDProcessor;
use FluxMedia\Processors\ImagickProcessor;
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
     * @var LoggerInterface
     */
    private $logger;


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
     * @param LoggerInterface $logger Logger instance.
     */
    public function __construct( LoggerInterface $logger ) {
        $this->logger = $logger;
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
				$this->logger->log_operation( 'info', 'processor_initialization', 'Imagick with WebP and AVIF support detected', ['component' => 'image_processor'] );
				return new ImagickProcessor( $this->logger );
			} else {
				$missing_formats = [];
				if ( ! in_array( 'WEBP', $formats, true ) ) {
					$missing_formats[] = 'WebP';
				}
				if ( ! in_array( 'AVIF', $formats, true ) ) {
					$missing_formats[] = 'AVIF';
				}
				$this->logger->log_format_unsupported( 'Imagick', implode( ', ', $missing_formats ), 'Format not compiled in Imagick' );
			}
		} else {
			if ( ! class_exists( 'Imagick' ) ) {
				$this->logger->log_processor_unavailable( 'Imagick', 'Imagick class not found' );
			} elseif ( ! extension_loaded( 'imagick' ) ) {
				$this->logger->log_processor_unavailable( 'Imagick', 'Imagick extension not loaded' );
			}
		}

		// Fallback to GD if available.
		if ( extension_loaded( 'gd' ) ) {
			// Check GD version and WebP support.
			$gd_info = gd_info();
			if ( isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'] ) {
				$this->logger->log_operation( 'info', 'processor_initialization', 'GD with WebP support detected', ['component' => 'image_processor'] );
				return new GDProcessor( $this->logger );
			} else {
				$this->logger->log_format_unsupported( 'GD', 'WebP', 'WebP support not compiled in GD' );
			}
		} else {
			$this->logger->log_processor_unavailable( 'GD', 'GD extension not loaded' );
		}

		$this->logger->log_processor_unavailable( 'All', 'No suitable image processor found. Imagick or GD with WebP support required.' );
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
			$this->logger->log_conversion( 'error', $source_path, 'WebP', 'No image processor available' );
			return false;
		}

		// Use options as provided by caller

		try {
			$result = $this->processor->convert_to_webp( $source_path, $destination_path, $options );
			
			if ( $result ) {
				$this->logger->log_conversion( 'info', $source_path, 'WebP', "Successfully converted {$source_path}" );
			} else {
				$this->logger->log_conversion( 'error', $source_path, 'WebP', 'Processor returned false' );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->log_conversion( 'error', $source_path, 'WebP', $e->getMessage() );
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
			$this->logger->log_conversion( 'error', $source_path, 'AVIF', 'No image processor available' );
			return false;
		}

		// Use options as provided by caller

		try {
			$result = $this->processor->convert_to_avif( $source_path, $destination_path, $options );
			
			if ( $result ) {
				$this->logger->log_conversion( 'info', $source_path, 'AVIF', "Successfully converted {$source_path}" );
			} else {
				$this->logger->log_conversion( 'error', $source_path, 'AVIF', 'Processor returned false' );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->log_conversion( 'error', $source_path, 'AVIF', $e->getMessage() );
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
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$supported_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		return in_array( $extension, $supported_extensions, true );
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
			Converter::FORMAT_WEBP => false,
			Converter::FORMAT_AVIF => false,
		];

		// Convert to WebP
		$results[ Converter::FORMAT_WEBP ] = $this->convert_to_webp( $source_path, $webp_path, $webp_options );
		
		// Convert to AVIF
		$results[ Converter::FORMAT_AVIF ] = $this->convert_to_avif( $source_path, $avif_path, $avif_options );

		// Log hybrid conversion results
		if ( $results[ Converter::FORMAT_WEBP ] && $results[ Converter::FORMAT_AVIF ] ) {
			$this->logger->info( "Successfully converted image using hybrid approach: {$source_path}" );
		} elseif ( $results[ Converter::FORMAT_WEBP ] || $results[ Converter::FORMAT_AVIF ] ) {
			$this->logger->warning( "Partial hybrid conversion success: {$source_path} (WebP: " . ( $results[ Converter::FORMAT_WEBP ] ? 'success' : 'failed' ) . ", AVIF: " . ( $results[ Converter::FORMAT_AVIF ] ? 'success' : 'failed' ) . ")" );
		} else {
			$this->logger->error( "Hybrid conversion failed for both formats: {$source_path}" );
		}

		return $results;
	}

	/**
	 * Process image file - convert to multiple formats.
	 *
	 * @since 1.0.0
	 * @param string $source_path Source image file path.
	 * @param array  $destination_paths Array of format => destination_path mappings.
	 * @param array  $settings Conversion settings.
	 * @return array Conversion results.
	 */
	public function process_image( $source_path, $destination_paths, $settings = [] ) {
		$results = [
			'success' => false,
			'converted_formats' => [],
			'converted_files' => [],
			'errors' => [],
		];

		// Validate source file
		if ( ! file_exists( $source_path ) ) {
			$results['errors'][] = 'Source file not found';
			return $results;
		}

		// Check if image is supported
		if ( ! $this->is_supported_image( $source_path ) ) {
			$results['errors'][] = 'Unsupported image format';
			return $results;
		}

		// Use settings as provided by caller

		// Process based on settings
		if ( $settings['hybrid_approach'] && isset( $destination_paths[ Converter::FORMAT_WEBP ] ) && isset( $destination_paths[ Converter::FORMAT_AVIF ] ) ) {
			// Hybrid approach - create both WebP and AVIF
			$conversion_results = $this->convert_hybrid(
				$source_path,
				$destination_paths[ Converter::FORMAT_WEBP ],
				$destination_paths[ Converter::FORMAT_AVIF ],
				['quality' => $settings['webp_quality']],
				['quality' => $settings['avif_quality']]
			);

			if ( $conversion_results[ Converter::FORMAT_WEBP ] ) {
				$results['converted_formats'][] = Converter::FORMAT_WEBP;
				$results['converted_files'][ Converter::FORMAT_WEBP ] = $destination_paths[ Converter::FORMAT_WEBP ];
			}
			if ( $conversion_results[ Converter::FORMAT_AVIF ] ) {
				$results['converted_formats'][] = Converter::FORMAT_AVIF;
				$results['converted_files'][ Converter::FORMAT_AVIF ] = $destination_paths[ Converter::FORMAT_AVIF ];
			}

		} else {
			// Individual format conversion
			foreach ( $destination_paths as $format => $destination_path ) {
				$conversion_options = [];

				$success = false;
				if ( Converter::FORMAT_WEBP === $format ) {
					$conversion_options = ['quality' => $settings['webp_quality']];
					$success = $this->convert_to_webp( $source_path, $destination_path, $conversion_options );
				} elseif ( Converter::FORMAT_AVIF === $format ) {
					$conversion_options = ['quality' => $settings['avif_quality']];
					$success = $this->convert_to_avif( $source_path, $destination_path, $conversion_options );
				}

				if ( $success ) {
					$results['converted_formats'][] = $format;
					$results['converted_files'][ $format ] = $destination_path;
				}
			}
		}

		// Update results
		$results['success'] = ! empty( $results['converted_formats'] );

		return $results;
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
			case Converter::FORMAT_WEBP:
				return Converter::FORMAT_WEBP;
			case Converter::FORMAT_AVIF:
				return Converter::FORMAT_AVIF;
			default:
				return null;
		}
	}
}
