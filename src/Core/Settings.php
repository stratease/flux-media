<?php
/**
 * Centralized settings management for Flux Media plugin.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Core;

/**
 * Settings management class with constants and centralized getter/setter methods.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Default quality constants.
	 *
	 * @since 1.0.0
	 */
	const DEFAULT_WEBP_QUALITY = 75;
	const DEFAULT_AVIF_QUALITY = 70;
	const DEFAULT_VIDEO_AV1_CRF = 28;
	const DEFAULT_VIDEO_WEBM_CRF = 30;

	/**
	 * Default speed constants.
	 *
	 * @since 1.0.0
	 */
	const DEFAULT_AVIF_SPEED = 6;
	const DEFAULT_VIDEO_AV1_PRESET = 'medium';
	const DEFAULT_VIDEO_WEBM_PRESET = 'medium';

	/**
	 * Default format arrays.
	 *
	 * @since 1.0.0
	 */
	const DEFAULT_IMAGE_FORMATS = [ 'webp', 'avif' ];
	const DEFAULT_VIDEO_FORMATS = [ 'av1', 'webm' ];

	/**
	 * Default boolean settings.
	 *
	 * @since 1.0.0
	 */
	const DEFAULT_IMAGE_AUTO_CONVERT = true;
	const DEFAULT_VIDEO_AUTO_CONVERT = true;
	const DEFAULT_HYBRID_APPROACH = true;
	const DEFAULT_ASYNC_PROCESSING = true;
	const DEFAULT_CLEANUP_TEMP_FILES = true;
	const DEFAULT_CDN_ENABLED = false;
	const DEFAULT_EXTERNAL_CONVERSION_ENABLED = false;

	/**
	 * Default other settings.
	 *
	 * @since 1.0.0
	 */
	const DEFAULT_LOG_LEVEL = 'info';
	const DEFAULT_ENABLE_LOGGING = false;
	const DEFAULT_MAX_FILE_SIZE = 100; // MB
	const DEFAULT_CONVERSION_TIMEOUT = 3600; // seconds
	const DEFAULT_LICENSE_STATUS = 'free';

	/**
	 * WordPress option name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private static $option_name = 'flux_media_options';

	/**
	 * Get all default settings.
	 *
	 * @since 1.0.0
	 * @return array Default settings array.
	 */
	public static function get_defaults() {
		return [
			// Image conversion settings.
			'image_webp_quality' => self::DEFAULT_WEBP_QUALITY,
			'image_webp_lossless' => false,
			'image_avif_quality' => self::DEFAULT_AVIF_QUALITY,
			'image_avif_speed' => self::DEFAULT_AVIF_SPEED,
			'image_auto_convert' => self::DEFAULT_IMAGE_AUTO_CONVERT,
			'image_formats' => self::DEFAULT_IMAGE_FORMATS,
			'hybrid_approach' => self::DEFAULT_HYBRID_APPROACH,

			// Video conversion settings.
			'video_av1_crf' => self::DEFAULT_VIDEO_AV1_CRF,
			'video_av1_preset' => self::DEFAULT_VIDEO_AV1_PRESET,
			'video_webm_crf' => self::DEFAULT_VIDEO_WEBM_CRF,
			'video_webm_preset' => self::DEFAULT_VIDEO_WEBM_PRESET,
			'video_auto_convert' => self::DEFAULT_VIDEO_AUTO_CONVERT,
			'video_formats' => self::DEFAULT_VIDEO_FORMATS,

			// General settings.
			'async_processing' => self::DEFAULT_ASYNC_PROCESSING,
			'cleanup_temp_files' => self::DEFAULT_CLEANUP_TEMP_FILES,
			'log_level' => self::DEFAULT_LOG_LEVEL,
			'enable_logging' => self::DEFAULT_ENABLE_LOGGING,
			'max_file_size' => self::DEFAULT_MAX_FILE_SIZE,
			'conversion_timeout' => self::DEFAULT_CONVERSION_TIMEOUT,

			// License settings.
			'license_key' => '',
			'license_status' => self::DEFAULT_LICENSE_STATUS,

			// CDN settings.
			'cdn_enabled' => self::DEFAULT_CDN_ENABLED,
			'cdn_provider' => '',
			'cdn_api_key' => '',
			'cdn_endpoint' => '',

			// External conversion settings.
			'external_conversion_enabled' => self::DEFAULT_EXTERNAL_CONVERSION_ENABLED,
			'external_conversion_provider' => '',
			'external_conversion_api_key' => '',
			'external_conversion_endpoint' => '',
		];
	}

	/**
	 * Get a setting value with fallback to default.
	 *
	 * @since 1.0.0
	 * @param string $key Setting key.
	 * @param mixed  $default Default value if setting doesn't exist.
	 * @return mixed Setting value.
	 */
	public static function get( $key, $default = null ) {
		$options = get_option( self::$option_name, [] );
		$defaults = self::get_defaults();

		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}

		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Set a setting value.
	 *
	 * @since 1.0.0
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public static function set( $key, $value ) {
		$options = get_option( self::$option_name, [] );
		$options[ $key ] = $value;
		return update_option( self::$option_name, $options );
	}

	/**
	 * Get all settings with defaults merged.
	 *
	 * @since 1.0.0
	 * @return array All settings.
	 */
	public static function get_all() {
		$options = get_option( self::$option_name, [] );
		return array_merge( self::get_defaults(), $options );
	}

	/**
	 * Update multiple settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings to update.
	 * @return bool True on success, false on failure.
	 */
	public static function update( $settings ) {
		$current_options = get_option( self::$option_name, [] );
		$merged_options = array_merge( $current_options, $settings );
		return update_option( self::$option_name, $merged_options );
	}

	/**
	 * Delete a setting.
	 *
	 * @since 1.0.0
	 * @param string $key Setting key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $key ) {
		$options = get_option( self::$option_name, [] );
		unset( $options[ $key ] );
		return update_option( self::$option_name, $options );
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public static function reset() {
		return update_option( self::$option_name, self::get_defaults() );
	}

	/**
	 * Initialize default settings on plugin activation.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function initialize_defaults() {
		$current_options = get_option( self::$option_name, [] );
		$defaults = self::get_defaults();
		$merged_options = array_merge( $defaults, $current_options );
		update_option( self::$option_name, $merged_options );
	}

	/**
	 * Get WebP quality setting.
	 *
	 * @since 1.0.0
	 * @return int WebP quality value.
	 */
	public static function get_webp_quality() {
		return (int) self::get( 'image_webp_quality', self::DEFAULT_WEBP_QUALITY );
	}

	/**
	 * Get AVIF quality setting.
	 *
	 * @since 1.0.0
	 * @return int AVIF quality value.
	 */
	public static function get_avif_quality() {
		return (int) self::get( 'image_avif_quality', self::DEFAULT_AVIF_QUALITY );
	}

	/**
	 * Get AVIF speed setting.
	 *
	 * @since 1.0.0
	 * @return int AVIF speed value.
	 */
	public static function get_avif_speed() {
		return (int) self::get( 'image_avif_speed', self::DEFAULT_AVIF_SPEED );
	}

	/**
	 * Get video AV1 CRF setting.
	 *
	 * @since 1.0.0
	 * @return int AV1 CRF value.
	 */
	public static function get_video_av1_crf() {
		return (int) self::get( 'video_av1_crf', self::DEFAULT_VIDEO_AV1_CRF );
	}

	/**
	 * Get video WebM CRF setting.
	 *
	 * @since 1.0.0
	 * @return int WebM CRF value.
	 */
	public static function get_video_webm_crf() {
		return (int) self::get( 'video_webm_crf', self::DEFAULT_VIDEO_WEBM_CRF );
	}

	/**
	 * Get image formats setting.
	 *
	 * @since 1.0.0
	 * @return array Image formats array.
	 */
	public static function get_image_formats() {
		return self::get( 'image_formats', self::DEFAULT_IMAGE_FORMATS );
	}

	/**
	 * Get video formats setting.
	 *
	 * @since 1.0.0
	 * @return array Video formats array.
	 */
	public static function get_video_formats() {
		return self::get( 'video_formats', self::DEFAULT_VIDEO_FORMATS );
	}

	/**
	 * Check if hybrid approach is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if hybrid approach is enabled.
	 */
	public static function is_hybrid_approach_enabled() {
		return (bool) self::get( 'hybrid_approach', self::DEFAULT_HYBRID_APPROACH );
	}

	/**
	 * Check if image auto-conversion is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if image auto-conversion is enabled.
	 */
	public static function is_image_auto_convert_enabled() {
		return (bool) self::get( 'image_auto_convert', self::DEFAULT_IMAGE_AUTO_CONVERT );
	}

	/**
	 * Check if video auto-conversion is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if video auto-conversion is enabled.
	 */
	public static function is_video_auto_convert_enabled() {
		return (bool) self::get( 'video_auto_convert', self::DEFAULT_VIDEO_AUTO_CONVERT );
	}

	/**
	 * Check if logging is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if logging is enabled.
	 */
	public static function is_logging_enabled() {
		return (bool) self::get( 'enable_logging', self::DEFAULT_ENABLE_LOGGING );
	}
}
