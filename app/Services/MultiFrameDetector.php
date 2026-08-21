<?php
/**
 * Detects multi-frame image sources across supported input formats.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * Format-agnostic multi-frame detection for GIF, HEIF sequences, and other Imagick sources.
 *
 * @since 4.3.0
 */
class MultiFrameDetector {

	/**
	 * HEIF ftyp brands that indicate an image sequence (motion picture track).
	 *
	 * @since 4.3.0
	 * @var array<int, string>
	 */
	private const HEIF_SEQUENCE_BRANDS = [ 'msf1' ];

	/**
	 * Logger instance.
	 *
	 * @since 4.3.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * GIF-specific binary fallback detector.
	 *
	 * @since 4.3.0
	 * @var GifAnimationDetector
	 */
	private $gif_detector;

	/**
	 * FFmpeg HEIF sequence helper.
	 *
	 * @since 4.3.0
	 * @var HeifSequenceConverter
	 */
	private $heif_sequence_converter;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger                  = $logger;
		$this->gif_detector            = new GifAnimationDetector( $logger );
		$this->heif_sequence_converter = new HeifSequenceConverter( $logger );
	}

	/**
	 * Detect whether a file contains multiple frames.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source file path.
	 * @return bool
	 */
	public function is_multi_frame( $file_path ) {
		return $this->detect( $file_path )->is_multi_frame();
	}

	/**
	 * Whether the file is a HEIF/HEIC image sequence (msf1), regardless of Imagick frame count.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source file path.
	 * @return bool
	 */
	public function is_heif_sequence( $file_path ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, [ 'heic', 'heif', 'heics', 'heifs' ], true ) ) {
			return false;
		}

		if ( $this->has_heif_sequence_brand( $file_path ) ) {
			return true;
		}

		$ffmpeg_frames = $this->heif_sequence_converter->probe_frame_count( $file_path );
		return null !== $ffmpeg_frames && $ffmpeg_frames > 1;
	}

	/**
	 * Get the frame count for a source file.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source file path.
	 * @return int
	 */
	public function get_frame_count( $file_path ) {
		return $this->detect( $file_path )->get_frame_count();
	}

	/**
	 * Build a source image context for a file path.
	 *
	 * @since 4.3.0
	 * @param string               $file_path File path.
	 * @param SourceFormatRegistry $registry  Format registry.
	 * @return SourceImageContext|null Context or null when unsupported.
	 */
	public function build_context( $file_path, SourceFormatRegistry $registry ) {
		if ( ! $registry->is_supported_path( $file_path ) ) {
			return null;
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$mime      = $registry->get_mime_for_extension( $extension ) ?? '';
		$detection = $this->detect( $file_path );

		return new SourceImageContext(
			$file_path,
			$extension,
			$mime,
			$detection->is_multi_frame(),
			$detection->get_frame_count(),
			$registry->requires_imagick( $extension ),
			$registry->get_source_format( $extension )
		);
	}

	/**
	 * Detect multi-frame metadata for a file.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source file path.
	 * @return MultiFrameDetectionResult
	 */
	public function detect( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new MultiFrameDetectionResult( false, 0 );
		}

		// HEIF sequences: Imagick on older libheif reports 1 frame — prefer brand/FFmpeg.
		if ( $this->is_heif_sequence( $file_path ) ) {
			$ffmpeg_frames = $this->heif_sequence_converter->probe_frame_count( $file_path );
			$frame_count   = ( null !== $ffmpeg_frames && $ffmpeg_frames > 1 ) ? $ffmpeg_frames : 2;
			return new MultiFrameDetectionResult( true, $frame_count );
		}

		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$imagick_result = $this->detect_with_imagick( $file_path );
			if ( null !== $imagick_result ) {
				return $imagick_result;
			}
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( 'gif' === $extension && $this->gif_detector->is_animated_by_file_read( $file_path ) ) {
			return new MultiFrameDetectionResult( true, 2 );
		}

		return new MultiFrameDetectionResult( false, 1 );
	}

	/**
	 * Detect frame count using Imagick when available.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source file path.
	 * @return MultiFrameDetectionResult|null Result or null when Imagick cannot read the file.
	 */
	private function detect_with_imagick( $file_path ) {
		try {
			$imagick     = new \Imagick( $file_path );
			$frame_count = $imagick->getNumberImages();
			$imagick->clear();
			$imagick->destroy();

			return new MultiFrameDetectionResult( $frame_count > 1, max( 1, $frame_count ) );
		} catch ( \Exception $e ) {
			$this->logger->warning( "Multi-frame detection failed for {$file_path}: {$e->getMessage()}" );
			return null;
		}
	}

	/**
	 * Read HEIF ftyp brands and look for sequence markers.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source path.
	 * @return bool
	 */
	private function has_heif_sequence_brand( $file_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local binary header read.
		$header = @file_get_contents( $file_path, false, null, 0, 64 );
		if ( ! is_string( $header ) || strlen( $header ) < 12 ) {
			return false;
		}

		if ( 'ftyp' !== substr( $header, 4, 4 ) ) {
			return false;
		}

		$brand_blob = substr( $header, 8 );
		foreach ( self::HEIF_SEQUENCE_BRANDS as $brand ) {
			if ( false !== strpos( $brand_blob, $brand ) ) {
				return true;
			}
		}

		return false;
	}
}
