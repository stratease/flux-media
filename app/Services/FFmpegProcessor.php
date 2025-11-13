<?php
/**
 * FFmpeg video processor implementation using PHP-FFmpeg library.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\VideoProcessorInterface;
use FluxMedia\App\Services\LoggerInterface;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\App\Services\ProcessorTypes;
use FluxMedia\FFMpeg\FFMpeg;
use FluxMedia\FFMpeg\FFProbe;
use FluxMedia\FFMpeg\Format\Video\WebM;
use FluxMedia\FFMpeg\Format\Video\X264;
use FluxMedia\FFMpeg\Exception\RuntimeException;

/**
 * FFmpeg-based video processor with high-quality conversion settings using PHP-FFmpeg.
 *
 * @since 0.1.0
 */
class FFmpegProcessor implements VideoProcessorInterface {

	/**
	 * Logger instance.
	 *
	 * @since 0.1.0
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Processor detector instance.
	 *
	 * @since 1.0.0
	 * @var ProcessorDetector
	 */
	private $detector;

	/**
	 * FFMpeg instance.
	 *
	 * @since 0.1.0
	 * @var FFMpeg
	 */
	private $ffmpeg;

	/**
	 * FFProbe instance.
	 *
	 * @since 0.1.0
	 * @var FFProbe
	 */
	private $ffprobe;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param LoggerInterface   $logger Logger instance.
	 * @param ProcessorDetector $detector Optional processor detector instance.
	 */
	public function __construct( LoggerInterface $logger, ProcessorDetector $detector ) {
		$this->logger = $logger;
		$this->detector = $detector ? $detector : new ProcessorDetector();
		$this->initialize_ffmpeg();
	}

	/**
	 * Initialize FFMpeg and FFProbe instances.
	 *
	 * Uses ProcessorDetector to check availability before initializing.
	 * The PHP-FFmpeg library handles binary detection automatically.
	 *
	 * @since 1.0.0
	 */
	private function initialize_ffmpeg() {
		// Check if FFmpeg is available using ProcessorDetector
		if ( ! $this->detector->is_ffmpeg_available() ) {
			// Don't log as error - this is expected in many environments
			$this->ffmpeg = null;
			$this->ffprobe = null;
			return;
		}

		try {
			// Let PHP-FFmpeg library auto-detect binaries (same approach as ProcessorDetector)
			$this->ffmpeg = FFMpeg::create([
				'timeout'        => 3600, // 1 hour timeout
				'ffmpeg.threads' => 0, // Use all available threads
			]);

			$this->ffprobe = FFProbe::create();

		} catch ( RuntimeException $e ) {
			$this->logger->error( "Failed to initialize FFmpeg: {$e->getMessage()}" );
			$this->ffmpeg = null;
			$this->ffprobe = null;
		} catch ( \Exception $e ) {
			$this->logger->error( "Failed to initialize FFmpeg: {$e->getMessage()}" );
			$this->ffmpeg = null;
			$this->ffprobe = null;
		}
	}


	/**
	 * Get processor information.
	 *
	 * Uses ProcessorDetector to get availability and format support information.
	 *
	 * @since 1.0.0
	 * @return array Processor information.
	 */
	public function get_info() {
		// Get video processors from ProcessorDetector
		$processors = $this->detector->get_available_video_processors();
		
		// Check if FFmpeg processor is available
		if ( isset( $processors[ ProcessorTypes::VIDEO_FFMPEG ] ) && $processors[ ProcessorTypes::VIDEO_FFMPEG ]['available'] ) {
			$version_info = $this->get_version_info();
			
			return [
				'available' => true,
				'type' => 'ffmpeg',
				'version' => $version_info,
				'av1_support' => $processors[ ProcessorTypes::VIDEO_FFMPEG ]['av1_support'] ?? false,
				'webm_support' => $processors[ ProcessorTypes::VIDEO_FFMPEG ]['webm_support'] ?? false,
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
	 * Get FFmpeg version information.
	 *
	 * @since 0.1.0
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
			$cpu_used = $options['cpu_used'] ?? 4;
			
			// cpu-used: 0-8, where lower = slower but better compression
			// Validation is handled in Settings::get_video_av1_cpu_used()
			
			$format->setAdditionalParameters([
				'-crf', (string) $crf,
				'-cpu-used', (string) $cpu_used,
				'-movflags', '+faststart',
			]);

			$video->save( $format, $destination_path );
			
			// AV1 conversion completed successfully
			return true;
			
		} catch ( RuntimeException $e ) {
			$this->logger->error( "AV1 conversion failed: {$e->getMessage()}" );
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
			$format->setAudioCodec( 'libvorbis' );
			$format->setAudioKiloBitrate( 128 );
			
			// Set WebM-specific parameters
			$crf = $options['crf'] ?? 30;
			$speed = $options['speed'] ?? 4;
			
			// speed: 0-9, where lower = slower but better compression
			// Validation is handled in Settings::get_video_webm_speed()
			
			$format->setAdditionalParameters([
				'-crf', (string) $crf,
				'-speed', (string) $speed,
				'-movflags', '+faststart',
			]);

			$video->save( $format, $destination_path );
			
			// WebM conversion completed successfully
			return true;
			
		} catch ( RuntimeException $e ) {
			$this->logger->error( "WebM conversion failed: {$e->getMessage()}" );
			return false;
		}
	}

	/**
	 * Get video metadata.
	 *
	 * @since 0.1.0
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
	 * Uses ProcessorDetector to check AV1 support.
	 *
	 * @since 1.0.0
	 * @return bool True if AV1 is supported, false otherwise.
	 */
	public function supports_av1() {
		// Use ProcessorDetector to check AV1 support
		$processors = $this->detector->get_available_video_processors();
		if ( isset( $processors[ ProcessorTypes::VIDEO_FFMPEG ] ) ) {
			return $processors[ ProcessorTypes::VIDEO_FFMPEG ]['av1_support'] ?? false;
		}
		return false;
	}

	/**
	 * Check if processor supports WebM.
	 *
	 * Uses ProcessorDetector to check WebM support.
	 *
	 * @since 1.0.0
	 * @return bool True if WebM is supported, false otherwise.
	 */
	public function supports_webm() {
		// Use ProcessorDetector to check WebM support
		$processors = $this->detector->get_available_video_processors();
		if ( isset( $processors[ ProcessorTypes::VIDEO_FFMPEG ] ) ) {
			return $processors[ ProcessorTypes::VIDEO_FFMPEG ]['webm_support'] ?? false;
		}
		return false;
	}
}
