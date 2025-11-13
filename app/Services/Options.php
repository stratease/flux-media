<?php
/**
 * Options management class.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

/**
 * Options management for Flux Media Optimizer plugin.
 *
 * @since 0.1.0
 */
class Options {

	/**
	 * Default options.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	private static $defaults = [
		// Image conversion settings.
		'image_webp_quality' => 75,
		'image_webp_lossless' => false,
		'image_avif_quality' => 70,
		'image_avif_speed' => 6,
		'image_auto_convert' => true,
		'image_formats' => [ 'webp', 'avif' ],
		'image_hybrid_approach' => false, // Hybrid WebP + AVIF approach

		// Video conversion settings.
		'video_av1_crf' => 28,
		'video_av1_cpu_used' => 4,
		'video_webm_crf' => 30,
		'video_webm_speed' => 4,
		'video_auto_convert' => true,
		'video_formats' => [ 'av1', 'webm' ],
		'video_hybrid_approach' => false, // Hybrid AV1 + WebM approach

		// General settings.
		'async_processing' => true,
		'cleanup_temp_files' => true,
		'log_level' => 'info',
		'max_file_size' => 100, // MB.
		'conversion_timeout' => 3600, // seconds.

		// License settings.
		'license_key' => '',
		'license_status' => 'free', // free, premium, expired, invalid.

		// CDN settings (TODO: Implement CDN integration).
		'cdn_enabled' => false,
		'cdn_provider' => '',
		'cdn_api_key' => '',
		'cdn_endpoint' => '',

		// External conversion settings (TODO: Implement external conversion).
		'external_conversion_enabled' => false,
		'external_conversion_provider' => '',
		'external_conversion_api_key' => '',
		'external_conversion_endpoint' => '',
	];

	/**
	 * Set default options.
	 *
	 * @since 0.1.0
	 */
	public static function set_defaults() {
		$current_options = get_option( 'flux_media_optimizer_options', [] );
		$merged_options = array_merge( self::$defaults, $current_options );
		update_option( 'flux_media_optimizer_options', $merged_options );
	}

	/**
	 * Get option value.
	 *
	 * @since 0.1.0
	 * @param string $key Option key.
	 * @param mixed  $default Default value if option doesn't exist.
	 * @return mixed Option value.
	 */
	public static function get( $key, $default = null ) {
		$options = get_option( 'flux_media_optimizer_options', [] );
		
		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		if ( isset( self::$defaults[ $key ] ) ) {
			return self::$defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Set option value.
	 *
	 * @since 0.1.0
	 * @param string $key Option key.
	 * @param mixed  $value Option value.
	 * @return bool True on success, false on failure.
	 */
	public static function set( $key, $value ) {
		$options = get_option( 'flux_media_optimizer_options', [] );
		$options[ $key ] = $value;
		return update_option( 'flux_media_optimizer_options', $options );
	}

	/**
	 * Get all options.
	 *
	 * @since 0.1.0
	 * @return array All options.
	 */
	public static function get_all() {
		$options = get_option( 'flux_media_optimizer_options', [] );
		return array_merge( self::$defaults, $options );
	}

	/**
	 * Update multiple options.
	 *
	 * @since 0.1.0
	 * @param array $options Options to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $options ) {
		$current_options = get_option( 'flux_media_optimizer_options', [] );
		$merged_options = array_merge( $current_options, $options );
		return update_option( 'flux_media_optimizer_options', $merged_options );
	}

	/**
	 * Delete option.
	 *
	 * @since 0.1.0
	 * @param string $key Option key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		$options = get_option( 'flux_media_optimizer_options', [] );
		unset( $options[ $key ] );
		return update_option( 'flux_media_optimizer_options', $options );
	}

	/**
	 * Reset options to defaults.
	 *
	 * @since 0.1.0
	 * @return bool True on success, false on failure.
	 */
	public static function reset() {
		return update_option( 'flux_media_optimizer_options', self::$defaults );
	}
}
