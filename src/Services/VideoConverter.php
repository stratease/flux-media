<?php
/**
 * Video conversion service.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Services;

use FluxMedia\Interfaces\LoggerInterface;
use FluxMedia\Interfaces\Converter;
use FluxMedia\Interfaces\VideoProcessorInterface;
use FluxMedia\Processors\FFmpegProcessor;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Video conversion service that handles AV1 and WebM conversion.
 *
 * @since 1.0.0
 */
class VideoConverter implements Converter {

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var LoggerInterface
     */
    private $logger;


    /**
     * Video processor instance.
     *
     * @since 1.0.0
     * @var VideoProcessorInterface
     */
    private $processor;


    /**
     * Supported video formats.
     *
     * @since 1.0.0
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
     * Get the available video processor.
     *
     * @since 1.0.0
     * @return VideoProcessorInterface|null The processor instance or null if none available.
     */
    private function get_available_processor() {
        // Check if PHP-FFmpeg library is available
        if ( ! class_exists( 'FFMpeg\FFMpeg' ) ) {
            $this->logger->log_processor_unavailable( 'PHP-FFmpeg', 'PHP-FFmpeg library not found' );
            return null;
        }

        // Check if FFmpeg binary is available
        if ( ! $this->is_ffmpeg_available() ) {
            $this->logger->log_processor_unavailable( 'FFmpeg', 'FFmpeg binary not found or not executable' );
            return null;
        }

        // Create FFmpegProcessor instance
        $processor = new FFmpegProcessor( $this->logger );

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
                $this->logger->log_operation( 'info', 'video_processor_initialization', 'FFmpeg with ' . implode( ' and ', $supported_formats ) . ' support detected', ['component' => 'video_processor'] );
                return $processor;
            } else {
                $this->logger->log_format_unsupported( 'FFmpeg', 'All', 'No supported video formats available' );
            }
        } else {
            $this->logger->log_processor_unavailable( 'FFmpeg', 'FFmpeg processor initialization failed' );
        }

        return null;
    }

    /**
     * Check if FFmpeg is available on the system.
     *
     * @since 1.0.0
     * @return bool True if FFmpeg is available, false otherwise.
     */
    private function is_ffmpeg_available() {
        try {
            $process = new Process( [ 'ffmpeg', '-version' ] );
            $process->run();
            return $process->isSuccessful();
        } catch ( \Exception $e ) {
            $this->logger->debug( 'FFmpeg availability check failed: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if video conversion is available.
     *
     * @since 1.0.0
     * @return bool True if conversion is available, false otherwise.
     */
    public function is_available() {
        return null !== $this->processor;
    }

    /**
     * Check if AV1 conversion is supported.
     *
     * @since 1.0.0
     * @return bool True if AV1 conversion is supported, false otherwise.
     */
    private function can_convert_to_av1() {
        if ( ! $this->processor ) {
            return false;
        }

        $processor_info = $this->processor->get_info();
        return $processor_info['av1_support'] ?? false;
    }

    /**
     * Check if WebM conversion is supported.
     *
     * @since 1.0.0
     * @return bool True if WebM conversion is supported, false otherwise.
     */
    private function can_convert_to_webm() {
        if ( ! $this->processor ) {
            return false;
        }

        $processor_info = $this->processor->get_info();
        return $processor_info['webm_support'] ?? false;
    }

    /**
     * Get processor information.
     *
     * @since 1.0.0
     * @return array Processor information.
     */
    public function get_processor_info() {
        // Always check format capabilities regardless of processor availability
        $av1_support = $this->can_convert_to_av1();
        $webm_support = $this->can_convert_to_webm();

        // Processor is available if it can convert to at least one format
        $available = $av1_support || $webm_support;

        return [
            'available' => $available,
            'type' => $this->processor ? 'ffmpeg' : 'none',
            'av1_support' => $av1_support,
            'webm_support' => $webm_support,
        ];
    }

    /**
     * Convert video to AV1 format.
     *
     * @since 1.0.0
     * @param string $source_path Source video path.
     * @param string $destination_path Destination path.
     * @param array  $options Conversion options.
     * @return bool True on success, false on failure.
     */
    public function convert_to_av1( $source_path, $destination_path, $options = [] ) {
        if ( ! $this->processor ) {
            $this->logger->log_processor_unavailable( 'Video', 'No video processor available for AV1 conversion' );
            return false;
        }

        // Use options as provided by caller

        try {
            $result = $this->processor->convert_to_av1( $source_path, $destination_path, $options );

            if ( $result ) {
                $this->logger->log_conversion( 'info', $source_path, 'AV1', "Successfully converted: {$source_path}" );
            } else {
                $this->logger->log_conversion( 'error', $source_path, 'AV1', 'Conversion returned false' );
            }

            return $result;
        } catch ( \Exception $e ) {
            $this->logger->log_conversion( 'error', $source_path, 'AV1', $e->getMessage() );
            return false;
        }
    }

    /**
     * Convert video to WebM format.
     *
     * @since 1.0.0
     * @param string $source_path Source video path.
     * @param string $destination_path Destination path.
     * @param array  $options Conversion options.
     * @return bool True on success, false on failure.
     */
    public function convert_to_webm( $source_path, $destination_path, $options = [] ) {
        if ( ! $this->processor ) {
            $this->logger->log_processor_unavailable( 'Video', 'No video processor available for WebM conversion' );
            return false;
        }

        // Use options as provided by caller

        try {
            $result = $this->processor->convert_to_webm( $source_path, $destination_path, $options );

            if ( $result ) {
                $this->logger->log_conversion( 'info', $source_path, 'WebM', "Successfully converted: {$source_path}" );
            } else {
                $this->logger->log_conversion( 'error', $source_path, 'WebM', 'Conversion returned false' );
            }

            return $result;
        } catch ( \Exception $e ) {
            $this->logger->log_conversion( 'error', $source_path, 'WebM', $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if file is a supported video format.
     *
     * @since 1.0.0
     * @param string $file_path File path to check.
     * @return bool True if supported, false otherwise.
     */
    public function is_supported_video( $file_path ) {
        $extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
        $supported_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'ogg'];
        return in_array( $extension, $supported_extensions, true );
    }

    /**
     * Get supported video MIME types.
     *
     * @since 1.0.0
     * @return array Array of supported MIME types.
     */
    public function get_supported_mime_types() {
        return $this->supported_formats;
    }

    /**
     * Get conversion statistics.
     *
     * @since 1.0.0
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
     * @since 1.0.0
     * @param string $temp_dir Temporary directory path.
     * @return bool True on success, false on failure.
     */
    public function cleanup_temp_files( $temp_dir ) {
        if ( ! is_dir( $temp_dir ) ) {
            return true;
        }

        $files = glob( $temp_dir . '/*' );
        $success = true;

        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                if ( ! unlink( $file ) ) {
                    $this->logger->warning( "Failed to delete temporary file: {$file}" );
                    $success = false;
                }
            }
        }

        return $success;
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
        if ( ! file_exists( $source_path ) ) {
            $results['errors'][] = 'Source file not found';
            return $results;
        }

        // Check if video is supported
        if ( ! $this->is_supported_video( $source_path ) ) {
            $results['errors'][] = 'Unsupported video format';
            return $results;
        }

        // Use settings as provided by caller

        // Process each requested format
        foreach ( $destination_paths as $format => $destination_path ) {
            $conversion_options = [];

            $success = false;
            if ( Converter::FORMAT_AV1 === $format ) {
                $conversion_options = ['crf' => $settings['video_av1_crf']];
                $success = $this->convert_to_av1( $source_path, $destination_path, $conversion_options );
            } elseif ( Converter::FORMAT_WEBM === $format ) {
                $conversion_options = ['crf' => $settings['video_webm_crf']];
                $success = $this->convert_to_webm( $source_path, $destination_path, $conversion_options );
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
        return in_array( $format, [ Converter::FORMAT_AV1, Converter::FORMAT_WEBM ], true );
    }

    /**
     * Get supported formats for this converter.
     *
     * @since 1.0.0
     * @return array Array of supported formats.
     */
    public function get_supported_formats() {
        return [ Converter::FORMAT_AV1, Converter::FORMAT_WEBM ];
    }

    /**
     * Get converter type.
     *
     * @since 1.0.0
     * @return string Converter type constant.
     */
    public function get_type() {
        return Converter::TYPE_VIDEO;
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
            case Converter::FORMAT_AV1:
                return Converter::FORMAT_AV1;
            case Converter::FORMAT_WEBM:
                return Converter::FORMAT_WEBM;
            default:
                return null;
        }
    }
}