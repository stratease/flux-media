<?php
/**
 * Image conversion service with GD/Imagick wrapper.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\ImageProcessorInterface;
use FluxMedia\App\Services\GDProcessor;
use FluxMedia\App\Services\ImagickProcessor;
use FluxMedia\App\Services\FormatSupportDetector;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\App\Services\ProcessorTypes;
use FluxMedia\App\Services\SourceFormatRegistry;
use FluxMedia\App\Services\MultiFrameDetector;
use FluxMedia\App\Services\SourceImageContext;
use FluxMedia\App\Services\AttachmentSourcePathResolver;

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
     * @var Logger
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
     * Multi-frame detector instance.
     *
     * @since 4.3.0
     * @var MultiFrameDetector
     */
    private $multi_frame_detector;

    /**
     * HEIF sequence animation vs static first-frame policy.
     *
     * @since 4.3.0
     * @var HeifAnimationPolicy
     */
    private $heif_animation_policy;

    /**
     * Supported input format registry.
     *
     * @since 4.3.0
     * @var SourceFormatRegistry
     */
    private $source_format_registry;

    /**
     * Attachment source path resolver.
     *
     * @since 4.3.0
     * @var AttachmentSourcePathResolver
     */
    private $source_path_resolver;

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
        'image/heic',
        'image/heif',
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
     * Last conversion error from convert_to_webp / convert_to_avif.
     *
     * @since 4.3.0
     * @var string
     */
    private $last_conversion_error = '';

    /**
     * Constructor.
     *
     * @since 0.1.0
     * @param Logger $logger Logger instance.
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
        $this->processor_detector = new ProcessorDetector();
        $this->format_detector = new FormatSupportDetector( $this->processor_detector );
        $this->multi_frame_detector = new MultiFrameDetector( $logger );
        $this->heif_animation_policy = new HeifAnimationPolicy();
        $this->source_format_registry = new SourceFormatRegistry();
        $this->source_path_resolver = new AttachmentSourcePathResolver( $logger, $this->source_format_registry, $this->multi_frame_detector );
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
		$context = $source_path ? $this->build_source_context( $source_path ) : null;

		if ( $context && $context->is_multi_frame() ) {
			// HEIF sequences: Animated WebP is handled in convert_to_webp via FFmpeg when
			// HeifAnimationPolicy allows it. Here Imagick is used for static first-frame
			// WebP/AVIF fallback (and for GIF multi-frame sources).
			if ( isset( $this->available_processors[ ProcessorTypes::IMAGE_IMAGICK ] ) ) {
				$imagick = $this->available_processors[ ProcessorTypes::IMAGE_IMAGICK ];
				$processor_info = $imagick->get_info();

				if ( ( Converter::FORMAT_WEBP === $format && ( $processor_info['webp_support'] ?? false ) ) ||
					 ( Converter::FORMAT_AVIF === $format && ( $processor_info['avif_support'] ?? false ) ) ) {
					if ( $processor_info['multi_frame_support'] ?? $processor_info['animated_gif_support'] ?? false ) {
						$this->logger->debug( "Using Imagick for multi-frame conversion to {$format}" );
						return $imagick;
					}
				}

				$this->logger->warning( "Multi-frame source detected but Imagick does not support multi-frame conversion. Conversion may fail or lose animation." );
			} else {
				$this->logger->error( 'Multi-frame source detected but Imagick is not available. GD cannot preserve animation.' );
				return null;
			}
		}

		if ( $context && $context->requires_imagick() ) {
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

			$this->logger->error( "HEIC/HEIF source requires Imagick with libheif support for {$format} conversion." );
			return null;
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
		if ( $this->multi_frame_detector->is_heif_sequence( $source_path )
			&& $this->should_convert_heif_sequence_to_animated_webp( $options ) ) {
			$sequence_converter = new HeifSequenceConverter( $this->logger );
			$result             = $sequence_converter->convert_to_animated_webp( $source_path, $destination_path, $options );
			if ( ! $result ) {
				$this->last_conversion_error = 'FFmpeg animated WebP conversion failed for HEIF sequence.';
				$this->logger->error( $this->last_conversion_error . " Source: {$source_path}" );
				// Fall through to static first-frame WebP when animation encode fails.
			} else {
				return true;
			}
		} elseif ( $this->multi_frame_detector->is_heif_sequence( $source_path ) ) {
			$this->logger->info(
				"HEIF sequence detected; writing static WebP (first frame) because animated WebP is not available or WebP output is disabled: {$source_path}"
			);
		}

		$processor = $this->processor_for_format( Converter::FORMAT_WEBP, $source_path );
		if ( ! $processor ) {
			$this->last_conversion_error = 'No image processor available for WebP conversion';
			$this->logger->error( $this->last_conversion_error );
			return false;
		}

		try {
			$result = $processor->convert_to_webp( $source_path, $destination_path, $options );
			
			if ( ! $result ) {
				$this->last_conversion_error = "WebP conversion failed for: {$source_path}";
				$this->logger->error( $this->last_conversion_error );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->last_conversion_error = "WebP conversion error for {$source_path}: {$e->getMessage()}";
			$this->logger->error( $this->last_conversion_error );
			return false;
		}
	}

	/**
	 * Whether a HEIF sequence should be encoded as animated WebP.
	 *
	 * Requires WebP (or hybrid) enabled in settings and FFmpeg libwebp_anim.
	 *
	 * @since 4.3.0
	 * @param array $options Conversion options (may include image_hybrid_approach).
	 * @return bool
	 */
	private function should_convert_heif_sequence_to_animated_webp( array $options ) {
		$image_formats   = Settings::get_image_formats();
		$hybrid_approach = (bool) ( $options['image_hybrid_approach'] ?? Settings::is_image_hybrid_approach_enabled() );
		$sequence_converter = new HeifSequenceConverter( $this->logger );

		return $this->heif_animation_policy->should_use_animated_webp(
			is_array( $image_formats ) ? $image_formats : [],
			$sequence_converter->is_available(),
			$hybrid_approach
		);
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
		if ( $this->multi_frame_detector->is_heif_sequence( $source_path ) ) {
			$this->logger->warning(
				"HEIF sequence detected; writing static AVIF (first frame) because animated AVIF is unavailable for: {$source_path}"
			);
		}

		$processor = $this->processor_for_format( Converter::FORMAT_AVIF, $source_path );
		if ( ! $processor ) {
			$this->last_conversion_error = 'No image processor available for AVIF conversion';
			$this->logger->error( $this->last_conversion_error );
			return false;
		}

		try {
			$result = $processor->convert_to_avif( $source_path, $destination_path, $options );
			
			if ( ! $result ) {
				$this->last_conversion_error = "AVIF conversion failed for: {$source_path}";
				$this->logger->error( $this->last_conversion_error );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->last_conversion_error = "AVIF conversion error for {$source_path}: {$e->getMessage()}";
			$this->logger->error( $this->last_conversion_error );
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
		if ( ! $this->source_format_registry->is_supported_path( $file_path ) ) {
			return false;
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! $this->source_format_registry->requires_imagick( $extension ) ) {
			return true;
		}

		return $this->processor_detector->imagick_supports_heic();
	}

	/**
	 * Build a source image context for a file path.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source file path.
	 * @return SourceImageContext|null
	 */
	public function build_source_context( $file_path ) {
		return $this->multi_frame_detector->build_context( $file_path, $this->source_format_registry );
	}

	/**
	 * Check if an attachment contains a multi-frame image source.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function is_multi_frame_source( $attachment_id ) {
		$file_path = $this->source_path_resolver->get_optimization_source_path( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return false;
		}

		return $this->multi_frame_detector->is_multi_frame( $file_path );
	}

	/**
	 * Get the optimization input path for an attachment.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	public function get_optimization_source_path( $attachment_id ) {
		return $this->source_path_resolver->get_optimization_source_path( $attachment_id );
	}

	/**
	 * Check if an attachment is an animated GIF.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if animated GIF, false otherwise.
	 */
	public function is_animated_gif( $attachment_id ) {
		return $this->is_multi_frame_source( $attachment_id );
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
		$use_hybrid_approach = (bool) ( $settings['image_hybrid_approach'] ?? false );
		if ( $use_hybrid_approach && isset( $destination_paths[ Converter::FORMAT_WEBP ] ) && isset( $destination_paths[ Converter::FORMAT_AVIF ] ) ) {
			// Hybrid approach - create both WebP and AVIF
			$this->last_conversion_error = '';
			$conversion_results = $this->convert_hybrid(
				$source_path,
				$destination_paths[ Converter::FORMAT_WEBP ],
				$destination_paths[ Converter::FORMAT_AVIF ],
				$settings
			);

			if ( $conversion_results[ Converter::FORMAT_WEBP ] ) {
				$results['converted_formats'][] = Converter::FORMAT_WEBP;
				$results['converted_files'][ Converter::FORMAT_WEBP ] = $destination_paths[ Converter::FORMAT_WEBP ];
			} elseif ( '' !== $this->last_conversion_error ) {
				$results['errors'][] = $this->last_conversion_error;
			}

			if ( $conversion_results[ Converter::FORMAT_AVIF ] ) {
				$results['converted_formats'][] = Converter::FORMAT_AVIF;
				$results['converted_files'][ Converter::FORMAT_AVIF ] = $destination_paths[ Converter::FORMAT_AVIF ];
			} elseif ( '' !== $this->last_conversion_error ) {
				$results['errors'][] = $this->last_conversion_error;
			}

		} else {
			// Individual format conversion
			foreach ( $destination_paths as $format => $destination_path ) {
				$this->last_conversion_error = '';
				$success = false;
				if ( Converter::FORMAT_WEBP === $format ) {
					$success = $this->convert_to_webp( $source_path, $destination_path, $settings );
				} elseif ( Converter::FORMAT_AVIF === $format ) {
					$success = $this->convert_to_avif( $source_path, $destination_path, $settings );
				}

				if ( $success ) {
					$results['converted_formats'][] = $format;
					$results['converted_files'][ $format ] = $destination_path;
				} elseif ( '' !== $this->last_conversion_error ) {
					$results['errors'][] = $this->last_conversion_error;
				} else {
					$results['errors'][] = strtoupper( $format ) . ' conversion failed for: ' . $source_path;
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
