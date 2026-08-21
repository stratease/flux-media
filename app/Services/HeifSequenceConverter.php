<?php
/**
 * Converts HEIF image sequences to animated WebP via FFmpeg.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FFMpeg\FFMpeg;
use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * FFmpeg-backed converter for msf1 HEIF sequences (Imagick/libheif cannot preserve them on older stacks).
 *
 * @since 4.3.0
 */
class HeifSequenceConverter {

	/**
	 * Logger instance.
	 *
	 * @since 4.3.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Whether FFmpeg can decode HEIF sequences and encode animated WebP.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function is_available() {
		if ( ! class_exists( FFMpeg::class ) ) {
			return false;
		}

		try {
			$ffmpeg = FFMpeg::create();
			$driver = $ffmpeg->getFFMpegDriver();
			$encoders = $driver->command( [ '-hide_banner', '-encoders' ] );
			return is_string( $encoders ) && false !== strpos( $encoders, 'libwebp_anim' );
		} catch ( \Exception $e ) {
			$this->logger->debug( 'HEIF sequence converter unavailable: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Convert a HEIF sequence to animated WebP.
	 *
	 * @since 4.3.0
	 * @param string $source_path      Source HEIF path.
	 * @param string $destination_path Destination WebP path.
	 * @param array  $options          Conversion options (webp_quality, resize_width, resize_height).
	 * @return bool True on success.
	 */
	public function convert_to_animated_webp( $source_path, $destination_path, $options = [] ) {
		if ( ! file_exists( $source_path ) ) {
			$this->logger->error( "HEIF sequence source missing: {$source_path}" );
			return false;
		}

		if ( ! $this->is_available() ) {
			$this->logger->error( 'FFmpeg libwebp_anim is not available for HEIF sequence conversion.' );
			return false;
		}

		$quality = isset( $options['webp_quality'] ) ? (int) $options['webp_quality'] : 75;
		$quality = max( 0, min( 100, $quality ) );

		$command = [
			'-y',
			'-hide_banner',
			'-i',
			$source_path,
		];

		$resize_width  = isset( $options['resize_width'] ) ? (int) $options['resize_width'] : 0;
		$resize_height = isset( $options['resize_height'] ) ? (int) $options['resize_height'] : 0;
		if ( $resize_width > 0 && $resize_height > 0 ) {
			$command[] = '-vf';
			$command[] = sprintf(
				'scale=%d:%d:force_original_aspect_ratio=decrease',
				$resize_width,
				$resize_height
			);
		}

		$command[] = '-c:v';
		$command[] = 'libwebp_anim';
		$command[] = '-loop';
		$command[] = '0';
		$command[] = '-q:v';
		$command[] = (string) $quality;
		$command[] = $destination_path;

		try {
			$ffmpeg = FFMpeg::create();
			$ffmpeg->getFFMpegDriver()->command( $command );
		} catch ( \Exception $e ) {
			$this->logger->error( "FFmpeg HEIF sequence WebP conversion failed: {$e->getMessage()}" );
			return false;
		}

		if ( ! file_exists( $destination_path ) || filesize( $destination_path ) <= 0 ) {
			$this->logger->error( "Animated WebP was not created at: {$destination_path}" );
			return false;
		}

		$this->logger->debug( "Animated WebP from HEIF sequence written: {$destination_path}" );
		return true;
	}

	/**
	 * Probe whether FFmpeg can read a HEIF sequence file.
	 *
	 * @since 4.3.0
	 * @param string $file_path HEIF path.
	 * @return int|null Frame count when readable; null when indeterminate or unavailable.
	 */
	public function probe_frame_count( $file_path ) {
		if ( ! file_exists( $file_path ) || ! class_exists( FFMpeg::class ) ) {
			return null;
		}

		try {
			$ffprobe = \FluxMedia\FFMpeg\FFProbe::create();
			$streams = $ffprobe->streams( $file_path )->videos();
			if ( $streams->count() < 1 ) {
				return null;
			}

			$video = $streams->first();
			$nb_frames = $video->get( 'nb_frames' );
			if ( is_numeric( $nb_frames ) && (int) $nb_frames > 0 ) {
				return (int) $nb_frames;
			}

			$duration = (float) $video->get( 'duration' );
			$fps      = $this->parse_frame_rate( $video->get( 'avg_frame_rate' ) );
			if ( $duration > 0 && $fps > 0 ) {
				return max( 1, (int) round( $duration * $fps ) );
			}

			// Readable video stream without a reliable count — treat as multi-frame sequence.
			return 2;
		} catch ( \Exception $e ) {
			$this->logger->debug( "FFmpeg HEIF sequence probe failed for {$file_path}: {$e->getMessage()}" );
			return null;
		}
	}

	/**
	 * Parse an FFmpeg frame-rate expression such as "25/1".
	 *
	 * @since 4.3.0
	 * @param mixed $rate Frame rate value from FFProbe.
	 * @return float
	 */
	private function parse_frame_rate( $rate ) {
		if ( is_numeric( $rate ) ) {
			return (float) $rate;
		}

		if ( ! is_string( $rate ) || '' === $rate || false === strpos( $rate, '/' ) ) {
			return 0.0;
		}

		$parts = explode( '/', $rate, 2 );
		$num   = (float) $parts[0];
		$den   = (float) ( $parts[1] ?? 0 );

		if ( $den <= 0 ) {
			return 0.0;
		}

		return $num / $den;
	}
}
