<?php
/**
 * Centralized settings management for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;


/**
 * Settings management class with constants and centralized getter/setter methods.
 *
 * @since 0.1.0
 */
class Settings {

	/**
	 * Default quality constants.
	 *
	 * @since 0.1.0
	 */
	const DEFAULT_WEBP_QUALITY = 75;
	const DEFAULT_AVIF_QUALITY = 55;
	const DEFAULT_VIDEO_AV1_CRF = 28;
	const DEFAULT_VIDEO_WEBM_CRF = 30;

	/**
	 * Default speed constants.
	 *
	 * @since 0.1.0
	 */
	const DEFAULT_AVIF_SPEED = 5;
	const DEFAULT_VIDEO_AV1_CPU_USED = 4; // 0-8, where lower = slower but better compression
	const DEFAULT_VIDEO_WEBM_SPEED = 4; // 0-9, where lower = slower but better compression

	/**
	 * Default format arrays.
	 *
	 * @since 0.1.0
	 */
	const DEFAULT_IMAGE_FORMATS = [ 'webp', 'avif' ];
	const DEFAULT_VIDEO_FORMATS = [ 'av1', 'webm' ];

	/**
	 * Default boolean settings.
	 *
	 * @since 0.1.0
	 */
	const DEFAULT_IMAGE_AUTO_CONVERT = true;
	const DEFAULT_VIDEO_AUTO_CONVERT = true;
	const DEFAULT_HYBRID_APPROACH = false;
	const DEFAULT_VIDEO_HYBRID_APPROACH = false;
	const DEFAULT_BULK_CONVERSION_ENABLED = false;

	/**
	 * Default other settings.
	 *
	 * @since 0.1.0
	 */
	const DEFAULT_LOG_LEVEL = 'info';
	const DEFAULT_ENABLE_LOGGING = false;
	const DEFAULT_EXTERNAL_SERVICE_ENABLED = false;
	const DEFAULT_LICENSE_LAST_VALID_DATE = null;

	/**
	 * WordPress option name.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private static $option_name = 'flux_media_optimizer_options';

	/**
	 * Get all default settings.
	 *
	 * @since 0.1.0
	 * @return array Default settings array.
	 */
	public static function get_defaults() {
		return [
			// Image conversion settings.
			'image_webp_quality' => self::DEFAULT_WEBP_QUALITY,
			'image_avif_quality' => self::DEFAULT_AVIF_QUALITY,
			'image_avif_speed' => self::DEFAULT_AVIF_SPEED,
			'image_auto_convert' => self::DEFAULT_IMAGE_AUTO_CONVERT,
			'image_formats' => self::DEFAULT_IMAGE_FORMATS,
			'image_hybrid_approach' => self::DEFAULT_HYBRID_APPROACH,

			// Video conversion settings.
			'video_av1_crf' => self::DEFAULT_VIDEO_AV1_CRF,
			'video_av1_cpu_used' => self::DEFAULT_VIDEO_AV1_CPU_USED,
			'video_webm_crf' => self::DEFAULT_VIDEO_WEBM_CRF,
			'video_webm_speed' => self::DEFAULT_VIDEO_WEBM_SPEED,
			'video_auto_convert' => self::DEFAULT_VIDEO_AUTO_CONVERT,
			'video_formats' => self::DEFAULT_VIDEO_FORMATS,
			'video_hybrid_approach' => self::DEFAULT_VIDEO_HYBRID_APPROACH,

			// General settings.
			'bulk_conversion_enabled' => self::DEFAULT_BULK_CONVERSION_ENABLED,
			'log_level' => self::DEFAULT_LOG_LEVEL,
			'enable_logging' => self::DEFAULT_ENABLE_LOGGING,
			
			// SaaS API settings.
			// Note: license_key is stored in flux-plugins_license_key site option for cross-plugin compatibility.
			// Use Settings::get_license_key() and Settings::set_license_key() methods instead.
			'external_service_enabled' => self::DEFAULT_EXTERNAL_SERVICE_ENABLED,
			'license_last_valid_date' => self::DEFAULT_LICENSE_LAST_VALID_DATE,
		];
	}

	/**
	 * Get a setting value with fallback to default.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 * @return array All settings.
	 */
	public static function get_all() {
		$options = get_option( self::$option_name, [] );
		return array_merge( self::get_defaults(), $options );
	}

	/**
	 * Update multiple settings.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
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
	 * @since 0.1.0
	 * @return bool True on success, false on failure.
	 */
	public static function reset() {
		return update_option( self::$option_name, self::get_defaults() );
	}

	/**
	 * Initialize default settings on plugin activation.
	 *
	 * @since 0.1.0
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
	 * @since 0.1.0
	 * @return int WebP quality value.
	 */
	public static function get_webp_quality() {
		return (int) self::get( 'image_webp_quality', self::DEFAULT_WEBP_QUALITY );
	}

	/**
	 * Get AVIF quality setting.
	 *
	 * @since 0.1.0
	 * @return int AVIF quality value.
	 */
	public static function get_avif_quality() {
		return (int) self::get( 'image_avif_quality', self::DEFAULT_AVIF_QUALITY );
	}

	/**
	 * Get AVIF speed setting.
	 *
	 * @since 0.1.0
	 * @return int AVIF speed value.
	 */
	public static function get_avif_speed() {
		return (int) self::get( 'image_avif_speed', self::DEFAULT_AVIF_SPEED );
	}

	/**
	 * Get version-specific AVIF recommendations.
	 *
	 * @since 0.1.0
	 * @return array Recommended settings by ImageMagick version.
	 */
	public static function get_avif_version_recommendations() {
		return [
			'6.x' => [
				'description' => 'ImageMagick 6.x (Imagick 3.7.x) - Limited AVIF support',
				'quality_method' => 'setImageCompressionQuality',
				'speed_support' => false,
				'recommended_speed' => 6,
				'recommended_quality' => 70,
				'notes' => 'AVIF support is patchy. Speed settings may be ignored, leading to slow processing (5-10 seconds per image).',
			],
			'7.0.x' => [
				'description' => 'ImageMagick 7.0.x (Early AVIF support)',
				'quality_method' => 'setImageCompressionQuality',
				'speed_support' => false,
				'recommended_speed' => 6,
				'recommended_quality' => 70,
				'notes' => 'Early AVIF implementations may ignore quality/speed settings, leading to default encoding (often CRF ~10, large files).',
			],
			'7.1.0+' => [
				'description' => 'ImageMagick 7.1.0+ (Reliable AVIF support)',
				'quality_method' => 'avif:crf',
				'speed_support' => true,
				'recommended_speed' => 5,
				'recommended_quality' => 70,
				'notes' => 'Full AVIF support with precise CRF control. Speeds 4-6 recommended for web use (balancing size and performance).',
			],
		];
	}

	/**
	 * Get video AV1 CRF setting.
	 *
	 * @since 0.1.0
	 * @return int AV1 CRF value.
	 */
	public static function get_video_av1_crf() {
		return (int) self::get( 'video_av1_crf', self::DEFAULT_VIDEO_AV1_CRF );
	}

	/**
	 * Get video WebM CRF setting.
	 *
	 * @since 0.1.0
	 * @return int WebM CRF value.
	 */
	public static function get_video_webm_crf() {
		return (int) self::get( 'video_webm_crf', self::DEFAULT_VIDEO_WEBM_CRF );
	}

	/**
	 * Get video AV1 CPU used setting.
	 *
	 * Validates and clamps the value to valid range (0-8).
	 *
	 * @since 1.0.0
	 * @return int AV1 CPU used (0-8).
	 */
	public static function get_video_av1_cpu_used() {
		$cpu_used = (int) self::get( 'video_av1_cpu_used', self::DEFAULT_VIDEO_AV1_CPU_USED );
		// Clamp to valid range: 0-8, where lower = slower but better compression
		return max( 0, min( 8, $cpu_used ) );
	}

	/**
	 * Get video WebM speed setting.
	 *
	 * Validates and clamps the value to valid range (0-9).
	 *
	 * @since 1.0.0
	 * @return int WebM speed (0-9).
	 */
	public static function get_video_webm_speed() {
		$speed = (int) self::get( 'video_webm_speed', self::DEFAULT_VIDEO_WEBM_SPEED );
		// Clamp to valid range: 0-9, where lower = slower but better compression
		return max( 0, min( 9, $speed ) );
	}

	/**
	 * Get image formats setting.
	 *
	 * @since 0.1.0
	 * @return array Image formats array.
	 */
	public static function get_image_formats() {
		return self::get( 'image_formats', self::DEFAULT_IMAGE_FORMATS );
	}

	/**
	 * Get video formats setting.
	 *
	 * @since 0.1.0
	 * @return array Video formats array.
	 */
	public static function get_video_formats() {
		return self::get( 'video_formats', self::DEFAULT_VIDEO_FORMATS );
	}

	/**
	 * Check if image hybrid approach is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if image hybrid approach is enabled.
	 */
	public static function is_image_hybrid_approach_enabled() {
		return (bool) self::get( 'image_hybrid_approach', self::DEFAULT_HYBRID_APPROACH );
	}

	/**
	 * Check if video hybrid approach is enabled.
	 *
	 * @since 1.0.0
	 * @return bool True if video hybrid approach is enabled.
	 */
	public static function is_video_hybrid_approach_enabled() {
		return (bool) self::get( 'video_hybrid_approach', self::DEFAULT_VIDEO_HYBRID_APPROACH );
	}

	/**
	 * Check if image auto-conversion is enabled.
	 *
	 * @since 0.1.0
	 * @return bool True if image auto-conversion is enabled.
	 */
	public static function is_image_auto_convert_enabled() {
		return (bool) self::get( 'image_auto_convert', self::DEFAULT_IMAGE_AUTO_CONVERT );
	}

	/**
	 * Check if video auto-conversion is enabled.
	 *
	 * @since 0.1.0
	 * @return bool True if video auto-conversion is enabled.
	 */
	public static function is_video_auto_convert_enabled() {
		return (bool) self::get( 'video_auto_convert', self::DEFAULT_VIDEO_AUTO_CONVERT );
	}

	/**
	 * Check if logging is enabled.
	 *
	 * @since 0.1.0
	 * @return bool True if logging is enabled.
	 */
	public static function is_logging_enabled() {
		return (bool) self::get( 'enable_logging', self::DEFAULT_ENABLE_LOGGING );
	}

	/**
	 * Check if bulk conversion is enabled.
	 *
	 * @since 0.1.0
	 * @return bool True if bulk conversion is enabled.
	 */
	public static function is_bulk_conversion_enabled() {
		return (bool) self::get( 'bulk_conversion_enabled', self::DEFAULT_BULK_CONVERSION_ENABLED );
	}

	/**
	 * Get the license key for optional external service processing.
	 *
	 * License keys are optional and only used for external processing service access.
	 * All plugin functionality works without a license key.
	 *
	 * Stored in flux-plugins_license_key site option for cross-plugin compatibility.
	 *
	 * @since 0.1.0
	 * @since 3.0.0 Modified to use site option (flux-plugins_license_key) instead of plugin options for cross-plugin compatibility.
	 * @return string License key.
	 */
	public static function get_license_key() {
		return (string) get_site_option( 'flux-plugins_license_key', '' );
	}

	/**
	 * Set the license key for optional external service processing.
	 *
	 * License keys are optional and only used for external processing service access.
	 * All plugin functionality works without a license key.
	 *
	 * Stores in flux-plugins_license_key site option for cross-plugin compatibility.
	 * Note: License activation with external service is handled by OptionsController,
	 * not in this method, to provide better error reporting and separation of concerns.
	 *
	 * @since 0.1.0
	 * @since 3.0.0 Modified to use site option (flux-plugins_license_key) instead of plugin options. License activation moved to OptionsController.
	 * @param string $license_key License key.
	 * @return bool True on success, false on failure.
	 */
	public static function set_license_key( $license_key ) {
		$license_key = sanitize_text_field( $license_key );
		return update_site_option( 'flux-plugins_license_key', $license_key );
	}

	/**
	 * Get the account ID (UUID) for this site.
	 *
	 * UUID is generated on plugin initialization and never changes.
	 *
	 * @since 3.0.0
	 * @return string Account ID (UUID) or empty string if not set.
	 */
	public static function get_account_id() {
		return (string) get_site_option( 'flux-plugins_account_id', '' );
	}

	/**
	 * Check if external service is enabled.
	 *
	 * External service cannot be enabled without a license key.
	 *
	 * @since 3.0.0
	 * @return bool True if external service is enabled and license key exists.
	 */
	public static function is_external_service_enabled() {
		$enabled = (bool) self::get( 'external_service_enabled', self::DEFAULT_EXTERNAL_SERVICE_ENABLED );
		$has_license = ! empty( self::get_license_key() );
		
		// External service cannot be enabled without a license key.
		return $enabled && $has_license;
	}

	/**
	 * Set external service enabled state.
	 *
	 * @since 3.0.0
	 * @param bool $enabled Whether external service is enabled.
	 * @return bool True on success, false on failure.
	 */
	public static function set_external_service_enabled( $enabled ) {
		return self::set( 'external_service_enabled', (bool) $enabled );
	}

	/**
	 * Get the last valid date for the license (GMT).
	 *
	 * Returns null if license has never been validated or is invalid.
	 *
	 * @since 3.0.0
	 * @return string|null Last valid date in GMT format (Y-m-d H:i:s) or null.
	 */
	public static function get_license_last_valid_date() {
		$date = self::get( 'license_last_valid_date', self::DEFAULT_LICENSE_LAST_VALID_DATE );
		return $date ? (string) $date : null;
	}

	/**
	 * Set the last valid date for the license (GMT).
	 *
	 * @since 3.0.0
	 * @param string|null $date Last valid date in GMT format (Y-m-d H:i:s) or null to mark as invalid.
	 * @return bool True on success, false on failure.
	 */
	public static function set_license_last_valid_date( $date ) {
		if ( $date === null ) {
			return self::set( 'license_last_valid_date', null );
		}
		return self::set( 'license_last_valid_date', sanitize_text_field( $date ) );
	}

	/**
	 * Check if license is currently valid.
	 *
	 * License is considered valid if last_valid_date is set (not null).
	 *
	 * @since 3.0.0
	 * @return bool True if license is valid, false otherwise.
	 */
	public static function is_license_valid() {
		return self::get_license_last_valid_date() !== null;
	}
}
