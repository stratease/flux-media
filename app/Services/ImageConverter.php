<?php
/**
 * Image conversion service with GD/Imagick wrapper.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\LoggerInterface;
use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\ImageProcessorInterface;
use FluxMedia\App\Services\GDProcessor;
use FluxMedia\App\Services\ImagickProcessor;
use FluxMedia\App\Services\FormatSupportDetector;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\App\Services\ProcessorTypes;

/**
 * Image conversion service that handles WebP and AVIF conversion.
 *
 * @since 0.1.0
 */
class ImageConverter implements Converter {

    /**
     * Logger instance.
     *
     * @since 0.1.0
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Format support detector instance.
     *
     * @since 0.1.0
     * @var FormatSupportDetector
     */
    private $format_detector;

    /**
     * Processor detector instance.
     *
     * @since 0.1.0
     * @var ProcessorDetector
     */
    private $processor_detector;

    /**
     * GIF animation detector instance.
     *
     * @since TBD
     * @var GifAnimationDetector
     */
    private $gif_detector;

    /**
     * Available image processors.
     *
     * @since 0.1.0
     * @var array
     */
    private $available_processors = [];


    /**
     * Supported image formats.
     *
     * @since 0.1.0
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
     * @since 0.1.0
     * @var string|null
     */
    private $source_path;

    /**
     * Destination file path for fluent interface.
     *
     * @since 0.1.0
     * @var string|null
     */
    private $destination_path;

    /**
     * Conversion options for fluent interface.
     *
     * @since 0.1.0
     * @var array
     */
    private $options = [];

    /**
     * Error messages for fluent interface.
     *
     * @since 0.1.0
     * @var array
     */
    private $errors = [];

    /**
     * Constructor.
     *
     * @since 0.1.0
     * @param LoggerInterface $logger Logger instance.
     */
    public function __construct( LoggerInterface $logger ) {
        $this->logger = $logger;
        $this->processor_detector = new ProcessorDetector();
        $this->format_detector = new FormatSupportDetector( $this->processor_detector );
        $this->gif_detector = new GifAnimationDetector( $logger );
        $this->available_processors = $this->initialize_processors();
    }

	/**
	 * Initialize available image processors.
	 *
	 * @since 0.1.0
	 * @return array Array of processor instances keyed by type.
	 */
	private function initialize_processors() {
		$processors = [];
		$available_processors = $this->processor_detector->get_available_image_processors();
		
		// Initialize Imagick processor if available
		if ( isset( $available_processors[ ProcessorTypes::IMAGE_IMAGICK ] ) && $available_processors[ ProcessorTypes::IMAGE_IMAGICK ]['available'] ) {
			$processors[ ProcessorTypes::IMAGE_IMAGICK ] = new ImagickProcessor( $this->logger );
		}
		
		// Initialize GD processor if available
		if ( isset( $available_processors[ ProcessorTypes::IMAGE_GD ] ) && $available_processors[ ProcessorTypes::IMAGE_GD ]['available'] ) {
			$processors[ ProcessorTypes::IMAGE_GD ] = new GDProcessor( $this->logger );
		}

		if ( empty( $processors ) ) {
			$this->logger->error( 'No suitable image processor found. Imagick or GD required.' );
		}

		return $processors;
	}

	/**
	 * Check if image conversion is available.
	 *
	 * @since 0.1.0
	 * @return bool True if conversion is available, false otherwise.
	 */
	public function is_available() {
		return ! empty( $this->available_processors );
	}

	/**
	 * Get processor information.
	 *
	 * @since 0.1.0
	 * @return array Processor information.
	 */
	public function get_processor_info() {
		// Check format support capabilities across all processors
		$webp_support = $this->can_convert_to_webp();
		$avif_support = $this->can_convert_to_avif();
		
		// Get the best processor for each format
		$webp_processor = $this->processor_for_format( Converter::FORMAT_WEBP );
		$avif_processor = $this->processor_for_format( Converter::FORMAT_AVIF );
		
		// Build processor information for each available processor
		$processors = [];
		foreach ( $this->available_processors as $type => $processor ) {
			$processor_info = $processor->get_info();
			$processors[ $type ] = [
				'available' => true,
				'type' => $processor_info['type'],
				'version' => $processor_info['version'],
				'webp_support' => $processor_info['webp_support'] ?? false,
				'avif_support' => $processor_info['avif_support'] ?? false,
				'animated_gif_support' => $processor_info['animated_gif_support'] ?? false,
			];
		}
		
		// Determine which processor handles each format
		$format_processors = [
			Converter::FORMAT_WEBP => $webp_processor ? $webp_processor->get_info()['type'] : null,
			Converter::FORMAT_AVIF => $avif_processor ? $avif_processor->get_info()['type'] : null,
		];
		
		return [
			'available' => ! empty( $this->available_processors ),
			'webp_support' => $webp_support,
			'avif_support' => $avif_support,
			'processors' => $processors,
			'format_processors' => $format_processors,
		];
	}

	/**
	 * Check if we can convert to WebP format.
	 *
	 * @since 0.1.0
	 * @return bool True if WebP conversion is possible, false otherwise.
	 */
	private function can_convert_to_webp() {
		return $this->processor_for_format( Converter::FORMAT_WEBP ) !== null;
	}

	/**
	 * Check if we can convert to AVIF format.
	 *
	 * @since 0.1.0
	 * @return bool True if AVIF conversion is possible, false otherwise.
	 */
	private function can_convert_to_avif() {
		return $this->processor_for_format( Converter::FORMAT_AVIF ) !== null;
	}

	/**
	 * Get the most capable and efficient processor for a specific format.
	 *
	 * @since 0.1.0
	 * @param string $format Target format constant.
	 * @param string $source_path Optional source file path for animated GIF detection.
	 * @return ImageProcessorInterface|null Best processor or null if none available.
	 */
	private function processor_for_format( $format, $source_path = null ) {
		// Check if source is an animated GIF - if so, force Imagick usage.
		if ( $source_path && $this->gif_detector->is_animated( $source_path ) ) {
			if ( isset( $this->available_processors[ ProcessorTypes::IMAGE_IMAGICK ] ) ) {
				$imagick = $this->available_processors[ ProcessorTypes::IMAGE_IMAGICK ];
				$processor_info = $imagick->get_info();
				
				// Check if Imagick supports the target format and animated GIFs.
				if ( ( Converter::FORMAT_WEBP === $format && ( $processor_info['webp_support'] ?? false ) ) ||
					 ( Converter::FORMAT_AVIF === $format && ( $processor_info['avif_support'] ?? false ) ) ) {
					if ( $processor_info['animated_gif_support'] ?? false ) {
						$this->logger->debug( "Using Imagick for animated GIF conversion to {$format}" );
						return $imagick;
					}
				}
				
				// If animated GIF but Imagick doesn't support it, log warning.
				$this->logger->warning( "Animated GIF detected but Imagick does not support animated GIF conversion. Conversion may fail or lose animation." );
			} else {
				$this->logger->error( "Animated GIF detected but Imagick is not available. GD cannot preserve animation." );
				return null;
			}
		}
		
		// Prefer Imagick for better quality and more features
		if ( isset( $this->available_processors[ ProcessorTypes::IMAGE_IMAGICK ] ) ) {
			$imagick = $this->available_processors[ ProcessorTypes::IMAGE_IMAGICK ];
			$processor_info = $imagick->get_info();
			
			if ( Converter::FORMAT_WEBP === $format && ( $processor_info['webp_support'] ?? false ) ) {
				return $imagick;
			}
			if ( Converter::FORMAT_AVIF === $format && ( $processor_info['avif_support'] ?? false ) ) {
				return $imagick;
			}
		}
		
		// Fallback to GD
		if ( isset( $this->available_processors[ ProcessorTypes::IMAGE_GD ] ) ) {
			$gd = $this->available_processors[ ProcessorTypes::IMAGE_GD ];
			$processor_info = $gd->get_info();
			
			if ( Converter::FORMAT_WEBP === $format && ( $processor_info['webp_support'] ?? false ) ) {
				return $gd;
			}
			if ( Converter::FORMAT_AVIF === $format && ( $processor_info['avif_support'] ?? false ) ) {
				return $gd;
			}
		}

		return null;
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
		$processor = $this->processor_for_format( Converter::FORMAT_WEBP, $source_path );
		if ( ! $processor ) {
			$this->logger->error( 'No image processor available for WebP conversion' );
			return false;
		}

		try {
			$result = $processor->convert_to_webp( $source_path, $destination_path, $options );
			
			if ( ! $result ) {
				$this->logger->error( "WebP conversion failed for: {$source_path}" );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( "WebP conversion error for {$source_path}: {$e->getMessage()}" );
			return false;
		}
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
		$processor = $this->processor_for_format( Converter::FORMAT_AVIF, $source_path );
		if ( ! $processor ) {
			$this->logger->error( 'No image processor available for AVIF conversion' );
			return false;
		}

		try {
			$result = $processor->convert_to_avif( $source_path, $destination_path, $options );
			
			if ( ! $result ) {
				$this->logger->error( "AVIF conversion failed for: {$source_path}" );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( "AVIF conversion error for {$source_path}: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Check if file is a supported image format.
	 *
	 * @since 0.1.0
	 * @param string $file_path File path to check.
	 * @return bool True if supported, false otherwise.
	 */
	public function is_supported_image( $file_path ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$supported_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
		return in_array( $extension, $supported_extensions, true );
	}

	/**
	 * Check if an attachment is an animated GIF.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if animated GIF, false otherwise.
	 */
	public function is_animated_gif( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		// Check MIME type first.
		$mime_type = get_post_mime_type( $attachment_id );
		if ( $mime_type !== 'image/gif' ) {
			return false;
		}

		// Use GifAnimationDetector to check if animated.
		return $this->gif_detector->is_animated( $file_path );
	}

	/**
	 * Get file size reduction percentage.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 * @param string $source_path Source image path.
	 * @param string $webp_path Destination WebP path.
	 * @param string $avif_path Destination AVIF path.
	 * @param array  $options Conversion options.
	 * @return array Results array with 'webp' and 'avif' keys.
	 */
	public function convert_hybrid( $source_path, $webp_path, $avif_path, $options = [] ) {
		$results = [
			Converter::FORMAT_WEBP => false,
			Converter::FORMAT_AVIF => false,
		];

		// Convert to WebP
		$results[ Converter::FORMAT_WEBP ] = $this->convert_to_webp( $source_path, $webp_path, $options );
		
		// Convert to AVIF
		$results[ Converter::FORMAT_AVIF ] = $this->convert_to_avif( $source_path, $avif_path, $options );

		// Log hybrid conversion results only on failure
		if ( ! $results[ Converter::FORMAT_WEBP ] && ! $results[ Converter::FORMAT_AVIF ] ) {
			$this->logger->error( "Hybrid conversion failed for both formats: {$source_path}" );
		} elseif ( ! $results[ Converter::FORMAT_WEBP ] || ! $results[ Converter::FORMAT_AVIF ] ) {
			$this->logger->warning( "Partial hybrid conversion success: {$source_path}" );
		}

		return $results;
	}

	/**
	 * Process image file - convert to multiple formats.
	 *
	 * @since 0.1.0
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

		// Validate destination paths and write permissions
		foreach ( $destination_paths as $format => $destination_path ) {
			$destination_dir = dirname( $destination_path );
			
			// Check if destination directory exists and is writable
			if ( ! is_dir( $destination_dir ) ) {
				$results['errors'][] = "Destination directory does not exist: {$destination_dir}";
				continue;
			}
			
			// Initialize WordPress filesystem
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			
			global $wp_filesystem;
			if ( ! $wp_filesystem || ! $wp_filesystem->is_writable( $destination_dir ) ) {
				$results['errors'][] = "Destination directory is not writable: {$destination_dir}";
				continue;
			}
			
			// If file already exists, check if we can write to it
			if ( file_exists( $destination_path ) ) {
				if ( ! $wp_filesystem->is_writable( $destination_path ) ) {
					$results['errors'][] = "Destination file exists but is not writable: {$destination_path}";
					continue;
				}
			}
			
			// Log successful validation
			$this->logger->debug( "Destination path validated for {$format}: {$destination_path}" );
		}
		
		// If any destination paths failed validation, return early
		if ( ! empty( $results['errors'] ) ) {
			$this->logger->error( 'Destination path validation failed: ' . implode( ', ', $results['errors'] ) );
			return $results;
		}

		// Use settings as provided by caller

		// Process based on settings
		if ( $settings['image_hybrid_approach'] && isset( $destination_paths[ Converter::FORMAT_WEBP ] ) && isset( $destination_paths[ Converter::FORMAT_AVIF ] ) ) {
			// Hybrid approach - create both WebP and AVIF
			$conversion_results = $this->convert_hybrid(
				$source_path,
				$destination_paths[ Converter::FORMAT_WEBP ],
				$destination_paths[ Converter::FORMAT_AVIF ],
				$settings
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
				$success = false;
				if ( Converter::FORMAT_WEBP === $format ) {
					$success = $this->convert_to_webp( $source_path, $destination_path, $settings );
				} elseif ( Converter::FORMAT_AVIF === $format ) {
					$success = $this->convert_to_avif( $source_path, $destination_path, $settings );
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 * @return string|null Error message or null if no error.
	 */
	public function get_last_error() {
		return ! empty( $this->errors ) ? end( $this->errors ) : null;
	}

	/**
	 * Get all error messages.
	 *
	 * @since 0.1.0
	 * @return array Array of error messages.
	 */
	public function get_errors() {
		return $this->errors;
	}

	/**
	 * Check if conversion is supported.
	 *
	 * @since 0.1.0
	 * @param string $format Target format.
	 * @return bool True if supported, false otherwise.
	 */
	public function is_format_supported( $format ) {
		return in_array( $format, [ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ], true );
	}

	/**
	 * Get supported formats for this converter.
	 *
	 * @since 0.1.0
	 * @return array Array of supported formats.
	 */
	public function get_supported_formats() {
		return [ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ];
	}

	/**
	 * Get converter type.
	 *
	 * @since 0.1.0
	 * @return string Converter type constant.
	 */
	public function get_type() {
		return Converter::TYPE_IMAGE;
	}

	/**
	 * Reset the converter state.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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

		// Initialize WordPress filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		
		global $wp_filesystem;
		if ( ! $wp_filesystem || ! $wp_filesystem->is_writable( $destination_dir ) ) {
			$this->add_error( "Destination directory is not writable: {$destination_dir}" );
			return false;
		}

		return true;
	}

	/**
	 * Add an error message.
	 *
	 * @since 0.1.0
	 * @param string $message Error message.
	 * @return void
	 */
	private function add_error( $message ) {
		$this->errors[] = $message;
	}

	/**
	 * Get target format from destination path.
	 *
	 * @since 0.1.0
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
