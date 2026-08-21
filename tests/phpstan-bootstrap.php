<?php
/**
 * PHPStan bootstrap for WordPress constants and stubs.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_VERSION' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_VERSION', '4.3.0' );
}

if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_URL' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_URL', 'https://example.test/wp-content/plugins/flux-media-optimizer/' );
}

if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

require_once __DIR__ . '/Support/wordpress-stubs.php';
