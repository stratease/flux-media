<?php
/**
 * Video conversion service.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Services;

use FluxMedia\Utils\Logger;
use FluxMedia\Utils\StructuredLogger;
use FluxMedia\Interfaces\Converter;
use FluxMedia\Interfaces\VideoProcessorInterface;
use FluxMedia\Processors\FFmpegProcessor;
use FluxMedia\Services\QuotaManager;
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
     * Video processor instance.
     *
     * @since 1.0.0
     * @var VideoProcessorInterface
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
     * @param Logger $logger Logger instance.
     */
    public function __construct( Logger $logger, QuotaManager $quota_manager ) {
        $this->logger = $logger;
        $this->structured_logger = new StructuredLogger( $logger );
        $this->quota_manager = $quota_manager;
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
            $this->structured_logger->log_video_processor_unavailable( 'PHP-FFmpeg library not found' );
            return null;
        }

        // Check if FFmpeg binary is available
        if ( ! $this->is_ffmpeg_available() ) {
            $this->structured_logger->log_video_processor_unavailable( 'FFmpeg binary not found or not executable' );
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
                $this->structured_logger->log_operation_success(
                    'Video processor initialization',
                    'FFmpeg with ' . implode( ' and ', $supported_formats ) . ' support detected'
                );
                return $processor;
            } else {
                $this->structured_logger->log_video_format_unsupported( 'All', 'No supported video formats available' );
            }
        } else {
            $this->structured_logger->log_video_processor_unavailable( 'FFmpeg processor initialization failed' );
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
            $this->structured_logger->log_video_processor_unavailable( 'No video processor available for AV1 conversion' );
            return false;
        }

        $default_options = [
            'crf' => 28,
            'preset' => 'medium',
            'cpu_used' => 4,
            'threads' => 0, // Auto-detect.
        ];

        $options = array_merge( $default_options, $options );

        try {
            $result = $this->processor->convert_to_av1( $source_path, $destination_path, $options );

            if ( $result ) {
                $this->structured_logger->log_operation_success( 'Video conversion to AV1', "Successfully converted: {$source_path}" );
            } else {
                $this->structured_logger->log_video_conversion_failed( $source_path, 'AV1', 'Conversion returned false' );
            }

            return $result;
        } catch ( \Exception $e ) {
            $this->structured_logger->log_video_conversion_failed( $source_path, 'AV1', $e->getMessage() );
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
            $this->structured_logger->log_video_processor_unavailable( 'No video processor available for WebM conversion' );
            return false;
        }

        $default_options = [
            'crf' => 30,
            'preset' => 'medium',
            'threads' => 0, // Auto-detect.
        ];

        $options = array_merge( $default_options, $options );

        try {
            $result = $this->processor->convert_to_webm( $source_path, $destination_path, $options );

            if ( $result ) {
                $this->structured_logger->log_operation_success( 'Video conversion to WebM', "Successfully converted: {$source_path}" );
            } else {
                $this->structured_logger->log_video_conversion_failed( $source_path, 'WebM', 'Conversion returned false' );
            }

            return $result;
        } catch ( \Exception $e ) {
            $this->structured_logger->log_video_conversion_failed( $source_path, 'WebM', $e->getMessage() );
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
        $mime_type = wp_check_filetype( $file_path )['type'];
        return in_array( $mime_type, $this->supported_formats, true );
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
     * Process video on upload - convert to AV1/WebM while retaining original.
     *
     * @since 1.0.0
     * @param int $attachment_id WordPress attachment ID.
     * @return array Conversion results.
     */
    public function process_uploaded_video( $attachment_id ) {
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

        // Check if video is supported
        if ( ! $this->is_supported_video( $file_path ) ) {
            $results['errors'][] = 'Unsupported video format';
            return $results;
        }

        // Get upload directory info
        $upload_dir = wp_upload_dir();
        $file_info = pathinfo( $file_path );
        $file_dir = $file_info['dirname'];
        $file_name = $file_info['filename'];

        // Get plugin options
        $options = get_option( 'flux_media_options', [] );
        $video_formats = $options['video_formats'] ?? ['av1', 'webm'];
        $av1_crf = $options['video_av1_crf'] ?? 28;
        $webm_crf = $options['video_webm_crf'] ?? 30;

        // Check if auto-conversion is enabled
        if ( ! ( $options['video_auto_convert'] ?? true ) ) {
            $results['errors'][] = 'Auto-conversion is disabled';
            return $results;
        }

        // Create destination paths for all requested formats
        $destination_paths = [];
        foreach ( $video_formats as $format ) {
            $destination_paths[ $format ] = $file_dir . '/' . $file_name . '.' . $format;
        }

        // Process each requested format
        foreach ( $video_formats as $format ) {
            $conversion_options = [];

            $success = false;
            if ( 'av1' === $format ) {
                $conversion_options = ['crf' => $av1_crf];
                $success = $this->convert_to_av1( $file_path, $destination_paths[ $format ], $conversion_options );
            } elseif ( 'webm' === $format ) {
                $conversion_options = ['crf' => $webm_crf];
                $success = $this->convert_to_webm( $file_path, $destination_paths[ $format ], $conversion_options );
            }

            if ( $success ) {
                $results['converted_formats'][] = $format;
            }
        }

		// Update results
		$results['success'] = ! empty( $results['converted_formats'] );

		// Store conversion metadata and track quota usage
		if ( $results['success'] ) {
			// Record quota usage for each successful conversion
			$converted_count = count( $results['converted_formats'] );
			for ( $i = 0; $i < $converted_count; $i++ ) {
				$this->quota_manager->record_usage( 'video' );
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
			$this->logger->info( "Video conversion quota: {$converted_count} videos used for attachment {$attachment_id}" );
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
     * @param string $format Target format (av1, webm).
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
            case 'av1':
                return Converter::FORMAT_AV1;
            case 'webm':
                return Converter::FORMAT_WEBM;
            default:
                return null;
        }
    }
}