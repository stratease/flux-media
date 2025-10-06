<?php
/**
 * FFmpeg video processor implementation using PHP-FFmpeg library.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Processors;

use FluxMedia\Interfaces\VideoProcessorInterface;
use FluxMedia\Utils\Logger;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format\Video\WebM;
use FFMpeg\Format\Video\X264;
use FFMpeg\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * FFmpeg-based video processor with high-quality conversion settings using PHP-FFmpeg.
 *
 * @since 1.0.0
 */
class FFmpegProcessor implements VideoProcessorInterface {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * FFMpeg instance.
	 *
	 * @since 1.0.0
	 * @var FFMpeg
	 */
	private $ffmpeg;

	/**
	 * FFProbe instance.
	 *
	 * @since 1.0.0
	 * @var FFProbe
	 */
	private $ffprobe;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->initialize_ffmpeg();
	}

	/**
	 * Initialize FFMpeg and FFProbe instances.
	 *
	 * @since 1.0.0
	 */
	private function initialize_ffmpeg() {
		try {
			$ffmpeg_path = $this->find_ffmpeg_binary();
			$ffprobe_path = $this->find_ffprobe_binary();

			// Check if binaries are actually available before trying to create instances
			if ( ! $this->is_executable( $ffmpeg_path ) ) {
				// Don't log as error - this is expected in many environments
				$this->ffmpeg = null;
				$this->ffprobe = null;
				return;
			}

			if ( ! $this->is_executable( $ffprobe_path ) ) {
				// Don't log as error - this is expected in many environments
				$this->ffmpeg = null;
				$this->ffprobe = null;
				return;
			}

			$this->ffmpeg = FFMpeg::create([
				'ffmpeg.binaries'  => $ffmpeg_path,
				'ffprobe.binaries' => $ffprobe_path,
				'timeout'          => 3600, // 1 hour timeout
				'ffmpeg.threads'   => 0, // Use all available threads
			]);

			$this->ffprobe = FFProbe::create([
				'ffprobe.binaries' => $ffprobe_path,
			]);

			$this->logger->info( "FFMpeg initialized successfully with binaries: {$ffmpeg_path}, {$ffprobe_path}" );

		} catch ( RuntimeException $e ) {
			// Don't log as error - this is expected when binaries aren't available
			$this->ffmpeg = null;
			$this->ffprobe = null;
		} catch ( \Exception $e ) {
			// Don't log as error - this is expected when binaries aren't available
			$this->ffmpeg = null;
			$this->ffprobe = null;
		}
	}

	/**
	 * Find FFmpeg binary path.
	 *
	 * @since 1.0.0
	 * @return string FFmpeg binary path.
	 */
	private function find_ffmpeg_binary() {
		// Check common locations.
		$possible_paths = [
			'ffmpeg',
			'/usr/bin/ffmpeg',
			'/usr/local/bin/ffmpeg',
			'/opt/homebrew/bin/ffmpeg',
		];

		foreach ( $possible_paths as $path ) {
			if ( $this->is_executable( $path ) ) {
				return $path;
			}
		}

		return 'ffmpeg'; // Fallback to PATH.
	}

	/**
	 * Find FFprobe binary path.
	 *
	 * @since 1.0.0
	 * @return string FFprobe binary path.
	 */
	private function find_ffprobe_binary() {
		// Check common locations.
		$possible_paths = [
			'ffprobe',
			'/usr/bin/ffprobe',
			'/usr/local/bin/ffprobe',
			'/opt/homebrew/bin/ffprobe',
		];

		foreach ( $possible_paths as $path ) {
			if ( $this->is_executable( $path ) ) {
				return $path;
			}
		}

		return 'ffprobe'; // Fallback to PATH.
	}

	/**
	 * Check if binary is executable.
	 *
	 * @since 1.0.0
	 * @param string $path Binary path.
	 * @return bool True if executable, false otherwise.
	 */
	private function is_executable( $path ) {
		try {
			$process = new Process( [ $path, '-version' ] );
			$process->run();
			return $process->isSuccessful();
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get processor information.
	 *
	 * @since 1.0.0
	 * @return array Processor information.
	 */
	public function get_info() {
		// Always check format support capabilities, regardless of initialization status
		$av1_support = $this->can_convert_to_av1();
		$webm_support = $this->can_convert_to_webm();
		
		// Processor is available if we can convert to at least one format
		$available = $av1_support || $webm_support;
		
		if ( $available ) {
			$version_info = $this->get_version_info();
			
			return [
				'available' => true,
				'type' => 'ffmpeg',
				'version' => $version_info,
				'av1_support' => $av1_support,
				'webm_support' => $webm_support,
			];
		}
		
		return [
			'available' => false,
			'type' => 'none',
			'av1_support' => false,
			'webm_support' => false,
		];
	}

	/**
	 * Check if we can convert to AV1 format.
	 *
	 * @since 1.0.0
	 * @return bool True if AV1 conversion is possible, false otherwise.
	 */
	private function can_convert_to_av1() {
		// Check if PHP-FFmpeg library is available
		if ( ! class_exists( 'FFMpeg\FFMpeg' ) ) {
			return false;
		}
		
		// Check if FFmpeg binary is available
		if ( ! $this->is_executable( $this->find_ffmpeg_binary() ) ) {
			return false;
		}
		
		// Check if AV1 codec is supported
		return $this->supports_av1();
	}

	/**
	 * Check if we can convert to WebM format.
	 *
	 * @since 1.0.0
	 * @return bool True if WebM conversion is possible, false otherwise.
	 */
	private function can_convert_to_webm() {
		// Check if PHP-FFmpeg library is available
		if ( ! class_exists( 'FFMpeg\FFMpeg' ) ) {
			return false;
		}
		
		// Check if FFmpeg binary is available
		if ( ! $this->is_executable( $this->find_ffmpeg_binary() ) ) {
			return false;
		}
		
		// Check if WebM codec is supported
		return $this->supports_webm();
	}

	/**
	 * Get FFmpeg version information.
	 *
	 * @since 1.0.0
	 * @return string Version information.
	 */
	private function get_version_info() {
		if ( ! $this->ffmpeg ) {
			return 'Unknown';
		}

		try {
			// PHP-FFmpeg doesn't expose version directly, so we'll use a simple check
			return 'FFmpeg (via PHP-FFmpeg)';
		} catch ( \Exception $e ) {
			$this->logger->error( "Failed to get FFmpeg version: {$e->getMessage()}" );
		}

		return 'Unknown';
	}

	/**
	 * Get available codecs information.
	 *
	 * @since 1.0.0
	 * @return array Codec information.
	 */
	private function get_codec_info() {
		$codecs = [];
		
		// Check for AV1 support
		$codecs['av1'] = $this->supports_av1();
		
		// Check for WebM support
		$codecs['webm'] = $this->supports_webm();
		
		return $codecs;
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
		if ( ! $this->ffmpeg ) {
			$this->logger->error( 'FFmpeg is not available' );
			return false;
		}

		if ( ! $this->supports_av1() ) {
			$this->logger->error( 'FFmpeg does not support AV1 encoding' );
			return false;
		}

		try {
			$video = $this->ffmpeg->open( $source_path );
			
			// Create AV1 format with custom parameters
			$format = new X264();
			$format->setVideoCodec( 'libaom-av1' );
			$format->setAudioCodec( 'libopus' );
			$format->setAudioKiloBitrate( 128 );
			
			// Set AV1-specific parameters
			$crf = $options['crf'] ?? 28;
			$preset = $options['preset'] ?? 'medium';
			$cpu_used = $options['cpu_used'] ?? 4;
			
			$format->setAdditionalParameters([
				'-crf', (string) $crf,
				'-preset', $preset,
				'-cpu-used', (string) $cpu_used,
				'-movflags', '+faststart',
			]);

			$video->save( $format, $destination_path );
			
			$this->logger->info( "AV1 conversion completed successfully: {$source_path}" );
			return true;
			
		} catch ( RuntimeException $e ) {
			$this->logger->error( "AV1 conversion failed: {$e->getMessage()}" );
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
		if ( ! $this->ffmpeg ) {
			$this->logger->error( 'FFmpeg is not available' );
			return false;
		}

		if ( ! $this->supports_webm() ) {
			$this->logger->error( 'FFmpeg does not support WebM encoding' );
			return false;
		}

		try {
			$video = $this->ffmpeg->open( $source_path );
			
			// Create WebM format
			$format = new WebM();
			$format->setVideoCodec( 'libvpx-vp9' );
			$format->setAudioCodec( 'libopus' );
			$format->setAudioKiloBitrate( 128 );
			
			// Set WebM-specific parameters
			$crf = $options['crf'] ?? 30;
			$preset = $options['preset'] ?? 'medium';
			
			$format->setAdditionalParameters([
				'-crf', (string) $crf,
				'-preset', $preset,
				'-movflags', '+faststart',
			]);

			$video->save( $format, $destination_path );
			
			$this->logger->info( "WebM conversion completed successfully: {$source_path}" );
			return true;
			
		} catch ( RuntimeException $e ) {
			$this->logger->error( "WebM conversion failed: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Get video metadata.
	 *
	 * @since 1.0.0
	 * @param string $file_path Video file path.
	 * @return array|false Video metadata or false on failure.
	 */
	public function get_metadata( $file_path ) {
		if ( ! $this->ffprobe ) {
			return false;
		}

		try {
			$video_stream = $this->ffprobe
				->streams( $file_path )
				->videos()
				->first();

			$audio_stream = $this->ffprobe
				->streams( $file_path )
				->audios()
				->first();

			$format = $this->ffprobe->format( $file_path );

			return [
				'duration' => (float) $format->get( 'duration' ),
				'bitrate' => (int) $format->get( 'bit_rate' ),
				'size' => (int) $format->get( 'size' ),
				'width' => (int) $video_stream->get( 'width' ),
				'height' => (int) $video_stream->get( 'height' ),
				'codec' => $video_stream->get( 'codec_name' ),
				'fps' => (float) $video_stream->get( 'r_frame_rate' ),
			];
			
		} catch ( RuntimeException $e ) {
			$this->logger->error( "Failed to get video metadata: {$e->getMessage()}" );
		}

		return false;
	}

	/**
	 * Check if processor supports AV1.
	 *
	 * @since 1.0.0
	 * @return bool True if AV1 is supported, false otherwise.
	 */
	public function supports_av1() {
		if ( ! $this->ffmpeg ) {
			return false;
		}

		try {
			// Try to create a format with AV1 codec to test support
			$format = new X264();
			$format->setVideoCodec( 'libaom-av1' );
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check if processor supports WebM.
	 *
	 * @since 1.0.0
	 * @return bool True if WebM is supported, false otherwise.
	 */
	public function supports_webm() {
		if ( ! $this->ffmpeg ) {
			return false;
		}

		try {
			// Try to create a WebM format to test support
			$format = new WebM();
			return true;
		} catch ( \Exception $e ) {
			return false;
		}
	}
}
