<?php
/**
 * Video conversion service.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\VideoProcessorInterface;
use FluxMedia\App\Services\FFmpegProcessor;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\Settings;

/**
 * Video conversion service that handles AV1 and WebM conversion.
 *
 * @since 0.1.0
 */
class VideoConverter implements Converter {

    /**
     * Logger instance.
     *
     * @since 0.1.0
     * @var Logger
     */
    private $logger;

    /**
     * Conversion tracker instance.
     *
     * @since 3.0.0
     * @var ConversionTracker|null
     */
    private $conversion_tracker;

    /**
     * Video processor instance.
     *
     * @since 0.1.0
     * @var VideoProcessorInterface
     */
    private $processor;


    /**
     * Supported video formats.
     *
     * @since 0.1.0
     * @var array
     */
    private $supported_formats = [
        'video/mp4',
        'video/avi',
        'video/mov',
        'video/wmv',
        'video/flv',
        'video/webm',
        'video/ogg',
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
     * @param Logger $logger Logger instance.
     */
    public function __construct( Logger $logger ) {
        $this->logger = $logger;
        // Processor is lazy-loaded when needed, not on every page load
    }

    /**
     * Get the processor instance (lazy-loaded).
     *
     * Initializes the processor only when needed, not on every page load.
     *
     * @since 3.0.0
     * @return VideoProcessorInterface|null The processor instance or null if none available.
     */
    private function get_processor() {
        // Lazy-load: only initialize if not already loaded
        if ( $this->processor === null ) {
            $this->processor = $this->get_available_processor();
        }
        return $this->processor;
    }

    /**
     * Get the available video processor.
     *
     * @since 0.1.0
     * @return VideoProcessorInterface|null The processor instance or null if none available.
     */
    private function get_available_processor() {
        // Check if PHP-FFmpeg library is available

        if ( ! class_exists( 'FluxMedia\FFMpeg\FFMpeg' ) ) {
            $this->logger->warning( 'PHP-FFmpeg library not found', [
                'operation' => 'processor_check',
                'component' => 'processor',
                'processor_type' => 'PHP-FFmpeg',
                'unavailability_reason' => 'PHP-FFmpeg library not found',
            ] );
            return null;
        }

        // Check if FFmpeg binary is available
        if ( ! $this->is_ffmpeg_available() ) {
            $this->logger->warning( 'FFmpeg binary not found or not executable', [
                'operation' => 'processor_check',
                'component' => 'processor',
                'processor_type' => 'FFmpeg',
                'unavailability_reason' => 'FFmpeg binary not found or not executable',
            ] );
            return null;
        }

        // Create FFmpegProcessor instance with ProcessorDetector
        $processor = new FFmpegProcessor( $this->logger, new ProcessorDetector() );

        // Check if processor can actually convert to supported formats
        $processor_info = $processor->get_info();

        if ( $processor_info['available'] ) {
            $supported_formats = [];
            if ( $processor_info['av1_support'] ) {
                $supported_formats[] = 'AV1';
            }
            if ( $processor_info['webm_support'] ) {
                $supported_formats[] = 'WebM';
            }

            if ( ! empty( $supported_formats ) ) {
                return $processor;
            } else {
                $this->logger->warning( 'FFmpeg does not support any video formats: No supported video formats available', [
                    'operation' => 'format_check',
                    'component' => 'format_support',
                    'processor_type' => 'FFmpeg',
                    'format' => 'All',
                    'unsupported_reason' => 'No supported video formats available',
                ] );
            }
        } else {
            $this->logger->warning( 'FFmpeg processor initialization failed', [
                'operation' => 'processor_check',
                'component' => 'processor',
                'processor_type' => 'FFmpeg',
                'unavailability_reason' => 'FFmpeg processor initialization failed',
            ] );
        }

        return null;
    }

    /**
     * Check if FFmpeg is available on the system.
     *
     * @since 0.1.0
     * @return bool True if FFmpeg is available, false otherwise.
     */
    private function is_ffmpeg_available() {
        try {
            // Try to create FFMpeg instance to detect availability
            if ( ! class_exists( 'FluxMedia\FFMpeg\FFMpeg' ) ) {
                return false;
            }
            $ffmpeg = \FluxMedia\FFMpeg\FFMpeg::create();
            return true;
        } catch ( \Exception $e ) {
            // FFmpeg not available or failed to initialize
            return false;
        }
    }

    /**
     * Check if video conversion is available.
     *
     * @since 0.1.0
     * @return bool True if conversion is available, false otherwise.
     */
    public function is_available() {
        $processor = $this->get_processor();
        return null !== $processor;
    }

    /**
     * Check if AV1 conversion is supported.
     *
     * @since 0.1.0
     * @return bool True if AV1 conversion is supported, false otherwise.
     */
    private function can_convert_to_av1() {
        $processor = $this->get_processor();
        if ( ! $processor ) {
            return false;
        }

        $processor_info = $processor->get_info();
        return $processor_info['av1_support'] ?? false;
    }

    /**
     * Check if WebM conversion is supported.
     *
     * @since 0.1.0
     * @return bool True if WebM conversion is supported, false otherwise.
     */
    private function can_convert_to_webm() {
        $processor = $this->get_processor();
        if ( ! $processor ) {
            return false;
        }

        $processor_info = $processor->get_info();
        return $processor_info['webm_support'] ?? false;
    }

    /**
     * Convert video to AV1 format.
     *
     * @since 0.1.0
     * @param string $source_path Source video path.
     * @param string $destination_path Destination path.
     * @param array  $options Conversion options.
     * @return bool True on success, false on failure.
     */
    public function convert_to_av1( $source_path, $destination_path, $options = [] ) {
        $processor = $this->get_processor();
        if ( ! $processor ) {
            $this->logger->warning( 'No video processor available for AV1 conversion', [
                'operation' => 'processor_check',
                'component' => 'processor',
                'processor_type' => 'Video',
                'unavailability_reason' => 'No video processor available for AV1 conversion',
            ] );
            return false;
        }

        // Use options as provided by caller

		try {
			$result = $processor->convert_to_av1( $source_path, $destination_path, $options );
			
			if ( ! $result ) {
				$this->logger->error( "AV1 conversion failed for: {$source_path}" );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( "AV1 conversion error for {$source_path}: {$e->getMessage()}" );
			return false;
		}
    }

    /**
     * Convert video to WebM format.
     *
     * @since 0.1.0
     * @param string $source_path Source video path.
     * @param string $destination_path Destination path.
     * @param array  $options Conversion options.
     * @return bool True on success, false on failure.
     */
    public function convert_to_webm( $source_path, $destination_path, $options = [] ) {
        $processor = $this->get_processor();
        if ( ! $processor ) {
            $this->logger->warning( 'No video processor available for WebM conversion', [
                'operation' => 'processor_check',
                'component' => 'processor',
                'processor_type' => 'Video',
                'unavailability_reason' => 'No video processor available for WebM conversion',
            ] );
            return false;
        }

        // Use options as provided by caller

		try {
			$result = $processor->convert_to_webm( $source_path, $destination_path, $options );
			
			if ( ! $result ) {
				$this->logger->error( "WebM conversion failed for: {$source_path}" );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( "WebM conversion error for {$source_path}: {$e->getMessage()}" );
			return false;
		}
    }

    /**
     * Check if file is a supported video format.
     *
     * @since 0.1.0
     * @param string $file_path File path to check.
     * @return bool True if supported, false otherwise.
     */
    public function is_supported_video( $file_path ) {
        $extension = strtolower( wp_check_filetype( $file_path )['ext'] ?? '' );
        $supported_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ogg'];
        return in_array( $extension, $supported_extensions, true );
    }

    /**
     * Get supported video MIME types.
     *
     * @since 0.1.0
     * @return array Array of supported MIME types.
     */
    public function get_supported_mime_types() {
        return $this->supported_formats;
    }

    /**
     * Get conversion statistics.
     *
     * @since 0.1.0
     * @return array Conversion statistics.
     */
    public function get_conversion_stats() {
        // TODO: Implement conversion statistics tracking.
        return [
            'total_conversions' => 0,
            'successful_conversions' => 0,
            'failed_conversions' => 0,
            'av1_conversions' => 0,
            'webm_conversions' => 0,
        ];
    }

    /**
     * Clean up temporary files.
     *
     * @since 0.1.0
     * @param string $temp_dir Temporary directory path.
     * @return bool True on success, false on failure.
     */
    public function cleanup_temp_files( $temp_dir ) {
        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            return false;
        }

        if ( ! $wp_filesystem->is_dir( $temp_dir ) ) {
            return true;
        }

        $files = $wp_filesystem->dirlist( $temp_dir );
        $success = true;

        if ( $files ) {
            foreach ( $files as $file ) {
                $file_path = $temp_dir . '/' . $file['name'];
                if ( $file['type'] === 'f' ) {
                    if ( ! $wp_filesystem->delete( $file_path ) ) {
                        $this->logger->warning( "Failed to delete temporary file: {$file_path}" );
                        $success = false;
                    }
                }
            }
        }

        return $success;
    }

    /**
     * Process video conversion with automatic destination path building and WordPress meta storage.
     *
     * Centralized method that handles the complete video conversion workflow:
     * - Gets settings and video formats from WordPress Settings
     * - Builds destination paths with proper AV1 filename handling
     * - Processes the video conversion
     * - Stores WordPress meta data (if attachment_id provided)
     * - Tracks conversions (if attachment_id provided)
     * - Returns structured results
     *
     * @since 3.0.0
     * @param int    $attachment_id WordPress attachment ID (optional, for meta storage).
     * @param string $file_path Source video file path.
     * @return array Conversion results with 'success', 'converted_formats', 'converted_files', and 'errors' keys.
     */
    public function process_video_conversion( $attachment_id, $file_path ) {
        // Get settings from WordPress
        // Defaults are already speed-optimized in Settings.php
        // Users can override these if they prefer smaller files over speed
        $settings = [
            'video_hybrid_approach' => Settings::is_video_hybrid_approach_enabled(),
            'video_av1_crf' => Settings::get_video_av1_crf(),
            'video_av1_cpu_used' => Settings::get_video_av1_cpu_used(),
            'video_webm_crf' => Settings::get_video_webm_crf(),
            'video_webm_speed' => Settings::get_video_webm_speed(),
        ];

        // Get video formats to convert
        $video_formats = Settings::get_video_formats();
        
        // Ensure video_formats is an array
        if ( ! is_array( $video_formats ) ) {
            $video_formats = [];
        }
        
        // Log formats being processed for debugging
        if ( empty( $video_formats ) && $attachment_id ) {
            $this->logger->warning( "No video formats configured for conversion. Attachment ID: {$attachment_id}" );
        }

        // Lazy-load conversion tracker if needed
        if ( $attachment_id && ! $this->conversion_tracker ) {
            // ConversionTracker now accepts Logger
            $this->conversion_tracker = new ConversionTracker( $this->logger );
        }

        // Get file path components
        $file_info = pathinfo( $file_path );
        $file_dir = $file_info['dirname'];
        $file_name = $file_info['filename'];

        // Build destination paths for requested formats
        $destination_paths = [];
        foreach ( $video_formats as $format ) {
            // Map format to correct file extension
            // AV1 uses MP4 container, WebM uses WebM container
            $extension = ( $format === Converter::FORMAT_AV1 ) ? 'mp4' : $format;
            
            // For AV1, append format to filename to make it unique (since it uses .mp4 extension)
            // Example: file.mp4 -> file.av1.mp4
            $destination_filename = $file_name;
            if ( $format === Converter::FORMAT_AV1 ) {
                $destination_filename = $file_name . '-av1';
            }
            
            $destination_paths[ $format ] = $file_dir . '/' . $destination_filename . '.' . $extension;
        }

        // Process the video using the existing process_video method
        $results = $this->process_video( $file_path, $destination_paths, $settings );

        // Handle results and store WordPress meta if attachment_id provided
        if ( $results['success'] && $attachment_id ) {
            // Initialize WordPress filesystem for file operations
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();
            
            global $wp_filesystem;
            
            // Get original file size
            $original_size = $wp_filesystem && $wp_filesystem->exists( $file_path ) ? $wp_filesystem->size( $file_path ) : 0;

            // Initialize converted files array
            $converted_files_by_size = [];

            // Store original file URL and size.
            $original_file_url = wp_get_attachment_url( $attachment_id );
            if ( $original_size > 0 ) {
                // Store original file details.
                // set_file_url_and_size() will generate URL automatically.
                AttachmentMetaHandler::set_file_url_and_size( $attachment_id, 'original', 'full', $original_file_url ?: $file_path, $original_size );
                
                // Also add to local array so it's included when we save the batch.
                // Get the URL that was stored by set_file_url_and_size().
                $stored_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, 'original', 'full' );
                if ( $stored_url ) {
                    $converted_files_by_size['full']['original'] = [
                        'url' => $stored_url, // Escaped above.
                        'filesize' => $original_size,
                    ];
                }
            }

            // Record conversion with file size data for each format
            // Videos don't have multiple sizes, so use 'full' as size_name
            foreach ( $results['converted_formats'] as $format ) {
                $converted_file_path = $results['converted_files'][ $format ] ?? '';
                $converted_size = $wp_filesystem && $wp_filesystem->exists( $converted_file_path ) ? $wp_filesystem->size( $converted_file_path ) : 0;
                
                // Track conversion if tracker available
                if ( $this->conversion_tracker ) {
                    $this->conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $converted_size, 'full' );
                }
                
                // Store URL and size together using unified structure.
                AttachmentMetaHandler::set_file_url_and_size( $attachment_id, $format, 'full', $converted_file_path, $converted_size );
            }

            // Update WordPress meta
            AttachmentMetaHandler::set_converted_formats( $attachment_id, $results['converted_formats'] );
            AttachmentMetaHandler::set_conversion_date_now( $attachment_id );
            
            // Store in size-specific format
            // Note: URLs and sizes are already stored via set_file_url_and_size() calls above.
            // Retrieve the stored data to ensure we have URLs (not paths) in the structure
            $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
            
            // Ensure 'full' key exists
            if ( ! isset( $converted_files_by_size['full'] ) ) {
                $converted_files_by_size['full'] = [];
            }
            
            // Update with any missing formats using URLs (set_file_url_and_size should have handled URL generation)
            foreach ( $results['converted_files'] as $format => $converted_file_path ) {
                // Get the URL that was already stored by set_file_url_and_size()
                $converted_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, 'full' );
                $converted_size = AttachmentMetaHandler::get_file_size( $attachment_id, $format, 'full' );
                
                // If URL wasn't stored yet, store it now
                if ( ! $converted_url && $converted_size > 0 ) {
                    AttachmentMetaHandler::set_file_url_and_size( $attachment_id, $format, 'full', $converted_file_path, $converted_size );
                    $converted_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, 'full' );
                }
                
                // Get file size if not already stored
                if ( ! $converted_size ) {
                    $converted_size = $wp_filesystem && $wp_filesystem->exists( $converted_file_path ) ? $wp_filesystem->size( $converted_file_path ) : 0;
                }
                
                // Store with URL (not file path)
                if ( $converted_url ) {
                    $converted_files_by_size['full'][ $format ] = [
                        'url' => $converted_url,
                        'filesize' => $converted_size,
                    ];
                }
            }
            
            AttachmentMetaHandler::set_converted_files_grouped_by_size( $attachment_id, $converted_files_by_size );
            
            // Extract all URLs and store in dedicated meta field for efficient lookup
            // Store ALL URLs (local and external) in META_KEY_FILE_URLS
            $all_urls = [];
            foreach ( $converted_files_by_size as $size_data ) {
                if ( ! is_array( $size_data ) ) {
                    continue;
                }
                foreach ( $size_data as $format => $file_data ) {
                    if ( is_array( $file_data ) && isset( $file_data['url'] ) && is_string( $file_data['url'] ) && ! empty( $file_data['url'] ) ) {
                        // Store all URLs (local and external).
                        $all_urls[] = $file_data['url'];
                    }
                }
            }
            // Store all URLs in dedicated meta field for efficient lookup
            if ( ! empty( $all_urls ) ) {
                AttachmentMetaHandler::set_file_urls( $attachment_id, array_unique( $all_urls ) );
            }

            // Video conversion completed
        } elseif ( ! $results['success'] && $attachment_id ) {
            $message = "Video conversion failed for attachment {$attachment_id}: " . implode( ', ', $results['errors'] );
            $this->logger->error( $message );
            AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
        }

        return $results;
    }

    /**
     * Process video file - convert to multiple formats.
     *
     * @since 1.0.0
     * @param string $source_path Source video file path.
     * @param array  $destination_paths Array of format => destination_path mappings.
     * @param array  $settings Conversion settings.
     * @return array Conversion results.
     */
    public function process_video( $source_path, $destination_paths, $settings = [] ) {
        $results = [
            'success' => false,
            'converted_formats' => [],
            'converted_files' => [],
            'errors' => [],
        ];

        // Validate source file
        if ( ! wp_check_filetype( $source_path )['ext'] ) {
            $results['errors'][] = 'Source file not found or invalid';
            return $results;
        }

        // Check if video is supported
        if ( ! $this->is_supported_video( $source_path ) ) {
            $results['errors'][] = 'Unsupported video format';
            return $results;
        }

        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            $results['errors'][] = 'WordPress filesystem not available';
            return $results;
        }

        // Validate destination paths and write permissions
        foreach ( $destination_paths as $format => $destination_path ) {
            $destination_dir = dirname( $destination_path );
            
            // Check if destination directory exists and is writable
            if ( ! $wp_filesystem->is_dir( $destination_dir ) ) {
                $results['errors'][] = "Destination directory does not exist: {$destination_dir}";
                continue;
            }
            
            if ( ! $wp_filesystem->is_writable( $destination_dir ) ) {
                $results['errors'][] = "Destination directory is not writable: {$destination_dir}";
                continue;
            }
            
            // If file already exists, check if we can write to it
            if ( $wp_filesystem->exists( $destination_path ) ) {
                if ( ! $wp_filesystem->is_writable( $destination_path ) ) {
                    $results['errors'][] = "Destination file exists but is not writable: {$destination_path}";
                    continue;
                }
            }
        }
        
        // If any destination paths failed validation, return early
        if ( ! empty( $results['errors'] ) ) {
            $this->logger->error( 'Destination path validation failed: ' . implode( ', ', $results['errors'] ) );
            return $results;
        }

        // Use settings as provided by caller

        // Process each requested format
        foreach ( $destination_paths as $format => $destination_path ) {
            $conversion_options = [];

            $success = false;
            
            // Normalize format string for comparison (ensure lowercase)
            $format_normalized = strtolower( trim( $format ) );
            
            if ( Converter::FORMAT_AV1 === $format_normalized && $this->can_convert_to_av1() ) {
                $conversion_options = [
                    'crf' => $settings['video_av1_crf'] ?? 28,
                    'cpu_used' => $settings['video_av1_cpu_used'] ?? 4,
                ];
                $success = $this->convert_to_av1( $source_path, $destination_path, $conversion_options );
                if ( ! $success ) {
                    $results['errors'][] = "AV1 conversion failed for format: {$format}";
                    $this->logger->error( "AV1 conversion failed for: {$destination_path}" );
                }
            } elseif ( Converter::FORMAT_WEBM === $format_normalized && $this->can_convert_to_webm() ) {
                $conversion_options = [
                    'crf' => $settings['video_webm_crf'] ?? 30,
                    'speed' => $settings['video_webm_speed'] ?? 4,
                ];
                $success = $this->convert_to_webm( $source_path, $destination_path, $conversion_options );
                if ( ! $success ) {
                    $results['errors'][] = "WebM conversion failed for format: {$format}";
                    $this->logger->error( "WebM conversion failed for: {$destination_path}" );
                }
            } else {
                // Format not supported or processor not available
                $format_name = Converter::FORMAT_AV1 === $format_normalized ? 'AV1' : ( Converter::FORMAT_WEBM === $format_normalized ? 'WebM' : $format );
                $can_av1 = $this->can_convert_to_av1();
                $can_webm = $this->can_convert_to_webm();
                $error_msg = "Format {$format_name} is not supported or processor not available";
                if ( Converter::FORMAT_AV1 === $format_normalized && ! $can_av1 ) {
                    $error_msg .= " (AV1 support: " . ( $can_av1 ? 'yes' : 'no' ) . ")";
                } elseif ( Converter::FORMAT_WEBM === $format_normalized && ! $can_webm ) {
                    $error_msg .= " (WebM support: " . ( $can_webm ? 'yes' : 'no' ) . ")";
                }
                $results['errors'][] = $error_msg;
                $this->logger->warning( "Skipping unsupported format: {$format} (normalized: {$format_normalized}, AV1 support: " . ( $can_av1 ? 'yes' : 'no' ) . ", WebM support: " . ( $can_webm ? 'yes' : 'no' ) . ")" );
            }

            if ( $success ) {
                $results['converted_formats'][] = $format;
                $results['converted_files'][ $format ] = $destination_path;
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
        if ( Converter::FORMAT_AV1 === $target_format ) {
            return $this->convert_to_av1( $this->source_path, $this->destination_path, $this->options );
        } elseif ( Converter::FORMAT_WEBM === $target_format ) {
            return $this->convert_to_webm( $this->source_path, $this->destination_path, $this->options );
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
        return in_array( $format, [ Converter::FORMAT_AV1, Converter::FORMAT_WEBM ], true );
    }

    /**
     * Get supported formats for this converter.
     *
     * @since 0.1.0
     * @return array Array of supported formats.
     */
    public function get_supported_formats() {
        return [ Converter::FORMAT_AV1, Converter::FORMAT_WEBM ];
    }

    /**
     * Get converter type.
     *
     * @since 0.1.0
     * @return string Converter type constant.
     */
    public function get_type() {
        return Converter::TYPE_VIDEO;
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

        if ( ! wp_check_filetype( $this->source_path )['ext'] ) {
            $this->add_error( "Source file does not exist or is invalid: {$this->source_path}" );
            return false;
        }

        if ( empty( $this->destination_path ) ) {
            $this->add_error( 'Destination path is required' );
            return false;
        }

        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            $this->add_error( 'WordPress filesystem not available' );
            return false;
        }

        // Check if destination directory exists and is writable
        $destination_dir = dirname( $this->destination_path );
        if ( ! $wp_filesystem->is_dir( $destination_dir ) ) {
            $this->add_error( "Destination directory does not exist: {$destination_dir}" );
            return false;
        }

        if ( ! $wp_filesystem->is_writable( $destination_dir ) ) {
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
        $filetype = wp_check_filetype( $this->destination_path );
        $extension = strtolower( $filetype['ext'] ?? '' );
        
        switch ( $extension ) {
            case Converter::FORMAT_AV1:
                return Converter::FORMAT_AV1;
            case Converter::FORMAT_WEBM:
                return Converter::FORMAT_WEBM;
            default:
                return null;
        }
    }
}