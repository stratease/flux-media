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
use FluxMedia\Interfaces\VideoProcessorInterface;
use FluxMedia\Processors\FFmpegProcessor;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Video conversion service that handles AV1 and WebM conversion.
 *
 * @since 1.0.0
 */
class VideoConverter {

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
	 * Get the available video processor.
	 *
	 * @since 1.0.0
	 * @return VideoProcessorInterface|null The processor instance or null if none available.
	 */
	private function get_available_processor() {
		// Always create FFmpegProcessor instance to get detailed status information
		// The processor itself will handle availability detection and reporting
		return new FFmpegProcessor( $this->logger );
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
	 * Get processor information.
	 *
	 * @since 1.0.0
	 * @return array Processor information.
	 */
	public function get_processor_info() {
		if ( ! $this->processor ) {
			return [
				'available' => false,
				'type' => 'none',
				'av1_support' => false,
				'webm_support' => false,
			];
		}

		// Always get detailed info from the processor, even if not fully available
		return $this->processor->get_info();
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
			$this->logger->error( 'No video processor available for AV1 conversion' );
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
				$this->logger->info( "Successfully converted video to AV1: {$source_path}" );
			} else {
				$this->logger->error( "Failed to convert video to AV1: {$source_path}" );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( "Exception during AV1 conversion: {$e->getMessage()}" );
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
			$this->logger->error( 'No video processor available for WebM conversion' );
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
				$this->logger->info( "Successfully converted video to WebM: {$source_path}" );
			} else {
				$this->logger->error( "Failed to convert video to WebM: {$source_path}" );
			}

			return $result;
		} catch ( \Exception $e ) {
			$this->logger->error( "Exception during WebM conversion: {$e->getMessage()}" );
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
	 * Get video metadata.
	 *
	 * @since 1.0.0
	 * @param string $file_path Video file path.
	 * @return array|false Video metadata or false on failure.
	 */
	public function get_video_metadata( $file_path ) {
		if ( ! $this->processor ) {
			return false;
		}

		return $this->processor->get_metadata( $file_path );
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
	 * Get estimated conversion time.
	 *
	 * @since 1.0.0
	 * @param string $file_path Video file path.
	 * @param string $target_format Target format (av1, webm).
	 * @return int Estimated time in seconds.
	 */
	public function get_estimated_conversion_time( $file_path, $target_format ) {
		$metadata = $this->get_video_metadata( $file_path );
		if ( ! $metadata ) {
			return 0;
		}

		$duration = $metadata['duration'] ?? 0;
		
		// Rough estimation based on format complexity.
		$multiplier = ( 'av1' === $target_format ) ? 3 : 1.5;
		
		return (int) ( $duration * $multiplier );
	}
}
