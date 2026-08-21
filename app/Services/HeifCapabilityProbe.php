<?php
/**
 * Probes Imagick HEIF/HEIC decode and FFmpeg sequence capabilities.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * Detects static and animated HEIC support with cached probing.
 *
 * Static HEIC uses Imagick format listing (libheif-backed). Animated HEIF sequences
 * prefer FFmpeg (`libwebp_anim`) because Imagick on libheif &lt; 1.20 reports a single frame for msf1 files.
 *
 * @since 4.3.0
 */
class HeifCapabilityProbe {

	/**
	 * Transient key for cached animated HEIC capability (versioned to invalidate stale false negatives).
	 *
	 * @since 4.3.0
	 */
	private const ANIMATED_HEIC_TRANSIENT = 'flux_media_optimizer_animated_heic_v2';

	/**
	 * Probe cache duration in seconds.
	 *
	 * @since 4.3.0
	 */
	private const PROBE_TTL = 86400;

	/**
	 * Check whether Imagick can decode static HEIC/HEIF input.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function supports_static_heic() {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}

		try {
			$imagick = new \Imagick();
			$formats = $imagick->queryFormats();

			return in_array( 'HEIC', $formats, true ) || in_array( 'HEIF', $formats, true );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Whether Imagick can open a specific HEIC/HEIF file.
	 *
	 * @since 4.3.0
	 * @param string $file_path Absolute path.
	 * @return bool
	 */
	public function can_decode_file( $file_path ) {
		if ( ! file_exists( $file_path ) || ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}

		try {
			$imagick = new \Imagick( $file_path );
			$width   = $imagick->getImageWidth();
			$imagick->clear();
			$imagick->destroy();

			return $width > 0;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Check whether animated HEIC/HEIF sequences can be processed locally.
	 *
	 * Prefers FFmpeg sequence decode + animated WebP encode. Falls back to Imagick
	 * multi-frame reads when the bundled sequence fixture reports more than one frame.
	 *
	 * @since 4.3.0
	 * @param bool $webp_supported Whether WebP output is available via Imagick (unused for FFmpeg path).
	 * @return bool
	 */
	public function supports_animated_heic( $webp_supported ) {
		if ( function_exists( 'get_transient' ) ) {
			$cached = get_transient( self::ANIMATED_HEIC_TRANSIENT );
			if ( false !== $cached ) {
				return (bool) $cached;
			}
		}

		$result = $this->probe_animated_heic_support( (bool) $webp_supported );

		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::ANIMATED_HEIC_TRANSIENT, $result ? 1 : 0, self::PROBE_TTL );
		}

		return $result;
	}

	/**
	 * Probe animated HEIC support without reading the cache.
	 *
	 * @since 4.3.0
	 * @param bool $webp_supported Whether Imagick WebP is available.
	 * @return bool
	 */
	private function probe_animated_heic_support( $webp_supported ) {
		$logger    = Logger::get_instance();
		$converter = new HeifSequenceConverter( $logger );
		if ( $converter->is_available() ) {
			$fixture = $this->get_first_existing_sequence_fixture();
			if ( $fixture ) {
				$frames = $converter->probe_frame_count( $fixture );
				if ( null !== $frames && $frames > 1 ) {
					return true;
				}
			} else {
				// Encoder present; assume HEIF sequences work when FFmpeg ships with the site.
				return true;
			}
		}

		if ( ! $this->supports_static_heic() || ! $webp_supported ) {
			return false;
		}

		return true === $this->supports_heif_sequences_via_imagick();
	}

	/**
	 * Probe Imagick HEIF multi-frame support against bundled fixtures.
	 *
	 * @since 4.3.0
	 * @return bool|null True/false when probed; null when no fixture exists.
	 */
	public function supports_heif_sequences_via_imagick() {
		$fixture_paths = $this->get_sequence_probe_paths();

		foreach ( $fixture_paths as $fixture_path ) {
			if ( ! file_exists( $fixture_path ) ) {
				continue;
			}

			try {
				$imagick     = new \Imagick( $fixture_path );
				$frame_count = $imagick->getNumberImages();
				$imagick->clear();
				$imagick->destroy();

				return $frame_count > 1;
			} catch ( \Exception $e ) {
				return false;
			}
		}

		return null;
	}

	/**
	 * Back-compat alias used by older call sites.
	 *
	 * @since 4.3.0
	 * @return bool|null
	 */
	public function supports_heif_sequences() {
		$ffmpeg = new HeifSequenceConverter( Logger::get_instance() );
		if ( $ffmpeg->is_available() ) {
			return true;
		}

		return $this->supports_heif_sequences_via_imagick();
	}

	/**
	 * Get the first existing sequence probe fixture path.
	 *
	 * @since 4.3.0
	 * @return string|null
	 */
	private function get_first_existing_sequence_fixture() {
		foreach ( $this->get_sequence_probe_paths() as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Get candidate fixture paths for HEIF sequence probing.
	 *
	 * @since 4.3.0
	 * @return array<int, string>
	 */
	private function get_sequence_probe_paths() {
		$paths = [];

		if ( defined( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR' ) ) {
			$paths[] = FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'assets/fixtures/heif-sequence-probe.heif';
			$paths[] = FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'tests/_support/files/sample_animated.heif';
		}

		return $paths;
	}
}
