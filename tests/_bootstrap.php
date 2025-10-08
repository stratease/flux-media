<?php
/**
 * Codeception bootstrap file for Flux Media plugin tests.
 * Focuses on testing pure business logic components without WordPress dependencies.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Set up test environment for pure business logic testing
define( 'TEST_TEMP_DIR', sys_get_temp_dir() . '/flux-media-tests' );

// Create test temp directory if it doesn't exist
if ( ! is_dir( TEST_TEMP_DIR ) ) {
    mkdir( TEST_TEMP_DIR, 0755, true );
}

// Clean up test files after tests
register_shutdown_function( function() {
    if ( is_dir( TEST_TEMP_DIR ) ) {
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
} );
