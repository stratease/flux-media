<?php
/**
 * Policy for HEIF sequence animation vs static first-frame conversion.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Single source of truth for when animated HEIF sequences become animated WebP.
 *
 * Animation is preserved only as animated WebP when WebP output is enabled
 * (including hybrid approach) and FFmpeg libwebp_anim is available. Otherwise
 * sequences fall back to static first-frame WebP/AVIF per enabled formats.
 * GIF and video outputs are never used.
 *
 * @since 4.3.0
 */
class HeifAnimationPolicy {

	/**
	 * Whether settings allow preserving HEIF sequence animation (WebP path).
	 *
	 * Hybrid approach counts as WebP enabled.
	 *
	 * @since 4.3.0
	 * @param array $image_formats    Enabled formats from settings (e.g. webp, avif).
	 * @param bool  $hybrid_approach  Whether image hybrid approach is enabled.
	 * @return bool
	 */
	public function should_preserve_animation( array $image_formats, $hybrid_approach = false ) {
		return $this->is_webp_enabled( $image_formats, $hybrid_approach );
	}

	/**
	 * Whether WebP output is enabled via formats or hybrid approach.
	 *
	 * @since 4.3.0
	 * @param array $image_formats   Enabled formats from settings.
	 * @param bool  $hybrid_approach Whether hybrid approach is enabled.
	 * @return bool
	 */
	public function is_webp_enabled( array $image_formats, $hybrid_approach = false ) {
		if ( $hybrid_approach ) {
			return true;
		}

		return in_array( Converter::FORMAT_WEBP, $image_formats, true );
	}

	/**
	 * Whether to encode an HEIF sequence as animated WebP via FFmpeg.
	 *
	 * @since 4.3.0
	 * @param array $image_formats      Enabled formats from settings.
	 * @param bool  $ffmpeg_available   Whether FFmpeg libwebp_anim is available.
	 * @param bool  $hybrid_approach    Whether hybrid approach is enabled.
	 * @return bool
	 */
	public function should_use_animated_webp( array $image_formats, $ffmpeg_available, $hybrid_approach = false ) {
		return $this->should_preserve_animation( $image_formats, $hybrid_approach )
			&& (bool) $ffmpeg_available;
	}

	/**
	 * Resolve how a HEIF sequence should be converted given settings and capabilities.
	 *
	 * @since 4.3.0
	 * @param array $image_formats    Enabled formats from settings.
	 * @param bool  $ffmpeg_available Whether FFmpeg libwebp_anim is available.
	 * @param bool  $hybrid_approach  Whether hybrid approach is enabled.
	 * @return array{
	 *     use_animated_webp: bool,
	 *     preserve_animation: bool,
	 *     static_formats: string[]
	 * }
	 */
	public function resolve_sequence_outputs( array $image_formats, $ffmpeg_available, $hybrid_approach = false ) {
		$static_formats = $this->resolve_enabled_formats( $image_formats, $hybrid_approach );
		$preserve       = $this->should_preserve_animation( $image_formats, $hybrid_approach );
		$use_animated   = $this->should_use_animated_webp( $image_formats, $ffmpeg_available, $hybrid_approach );

		return [
			'use_animated_webp'  => $use_animated,
			'preserve_animation' => $preserve,
			'static_formats'     => $static_formats,
		];
	}

	/**
	 * Resolve effective output formats for image conversion settings.
	 *
	 * @since 4.3.0
	 * @param array $image_formats   Enabled formats from settings.
	 * @param bool  $hybrid_approach Whether hybrid approach is enabled.
	 * @return string[]
	 */
	public function resolve_enabled_formats( array $image_formats, $hybrid_approach = false ) {
		if ( $hybrid_approach ) {
			return [ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ];
		}

		$allowed = [ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ];
		$formats = [];

		foreach ( $image_formats as $format ) {
			if ( in_array( $format, $allowed, true ) ) {
				$formats[] = $format;
			}
		}

		return array_values( array_unique( $formats ) );
	}
}
