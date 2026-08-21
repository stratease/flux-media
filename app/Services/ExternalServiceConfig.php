<?php
/**
 * External service URL and timeout alignment for plugin and common library.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Resolves a single external API endpoint configuration for Flux Media Optimizer.
 *
 * @since 4.3.0
 */
class ExternalServiceConfig {

	/**
	 * Default Flux Plugins API base URL.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const DEFAULT_URL = 'https://api.fluxplugins.com';

	/**
	 * Default request timeout in seconds.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	public const DEFAULT_TIMEOUT = 15;

	/**
	 * Resolve aligned URL and timeout values.
	 *
	 * Precedence: explicitly configured common constant, then plugin constant, then defaults.
	 *
	 * @since 4.3.0
	 * @param string|null $plugin_url     Plugin URL override or null when undefined.
	 * @param string|null $common_url     Common URL override or null when undefined.
	 * @param int|null    $plugin_timeout Plugin timeout override or null when undefined.
	 * @param int|null    $common_timeout Common timeout override or null when undefined.
	 * @return array{url: string, timeout: int}
	 */
	public static function resolve( $plugin_url, $common_url, $plugin_timeout, $common_timeout ) {
		$url = self::DEFAULT_URL;
		if ( is_string( $common_url ) && '' !== trim( $common_url ) ) {
			$url = trim( $common_url );
		} elseif ( is_string( $plugin_url ) && '' !== trim( $plugin_url ) ) {
			$url = trim( $plugin_url );
		}

		$timeout = self::DEFAULT_TIMEOUT;
		if ( null !== $common_timeout ) {
			$timeout = (int) $common_timeout;
		} elseif ( null !== $plugin_timeout ) {
			$timeout = (int) $plugin_timeout;
		}

		return [
			'url'     => $url,
			'timeout' => $timeout,
		];
	}
}
