<?php
/**
 * Shared admin bundle URL resolution for production and local webpack dev.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Resolves Flux Media Optimizer admin JS bundle URLs.
 *
 * Production ships `assets/js/dist/*.bundle.js`. Optional local HMR uses
 * `FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE` when `WP_DEBUG` and `SCRIPT_DEBUG` are on.
 *
 * @since 4.3.0
 */
final class AdminScriptUrl {

	/**
	 * Resolve a bundle URL under assets/js/dist/ (or the configured dev base).
	 *
	 * @since 4.3.0
	 * @param string $filename Bundle file name (e.g. `attachment.bundle.js`).
	 * @return string Absolute URL.
	 */
	public static function for_bundle( string $filename ): string {
		$dev = self::dev_script_url( $filename );
		if ( null !== $dev ) {
			return $dev;
		}

		return FLUX_MEDIA_OPTIMIZER_PLUGIN_URL . 'assets/js/dist/' . ltrim( $filename, '/' );
	}

	/**
	 * Optional webpack-dev-server URL from wp-config only.
	 *
	 * @since 4.3.0
	 * @param string $filename Bundle file name.
	 * @return string|null Full URL, or null to use shipped dist files.
	 */
	public static function dev_script_url( string $filename ): ?string {
		if ( ! self::is_dev_script_base_configured() ) {
			return null;
		}

		$base = rtrim( (string) constant( 'FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE' ), '/' );
		$file = ltrim( $filename, '/' );

		return $base . '/' . $file;
	}

	/**
	 * Whether a non-empty FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE is active for HMR.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public static function is_dev_script_base_configured(): bool {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ) {
			return false;
		}

		if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE' ) || ! is_string( constant( 'FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE' ) ) ) {
			return false;
		}

		return '' !== trim( (string) constant( 'FLUX_MEDIA_OPTIMIZER_DEV_SCRIPT_BASE' ) );
	}
}
