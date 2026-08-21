<?php
/**
 * PHPUnit bootstrap for Flux Media Optimizer plugin tests.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/Support/wordpress-stubs.php';

if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR' ) ) {
	define( 'FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}

// Strauss leaves php-ffmpeg PSR-0 paths misaligned; register the same autoloader as production.
\FluxMedia\App\Services\FFmpegAutoloader::init();

define( 'TEST_TEMP_DIR', sys_get_temp_dir() . '/flux-media-optimizer-tests' );

if ( ! is_dir( TEST_TEMP_DIR ) ) {
	mkdir( TEST_TEMP_DIR, 0755, true );
}

register_shutdown_function(
	static function () {
		if ( ! is_dir( TEST_TEMP_DIR ) ) {
			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( TEST_TEMP_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $fileinfo ) {
			$todo = ( $fileinfo->isDir() ? 'rmdir' : 'unlink' );
			$todo( $fileinfo->getRealPath() );
		}

		rmdir( TEST_TEMP_DIR );
	}
);
