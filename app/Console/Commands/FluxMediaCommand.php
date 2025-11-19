<?php
/**
 * WP-CLI command for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Console\Commands;

use WP_CLI;
use WP_CLI_Command;
use FluxMedia\App\Services\Logger;
use FluxMedia\App\Services\BulkConverter;
use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\AttachmentMetaHandler;

/**
 * WP-CLI command for managing Flux Media Optimizer conversions.
 *
 * @since 0.1.0
 */
class FluxMediaCommand extends WP_CLI_Command {

    /**
     * Logger instance.
     *
     * @since 0.1.0
     * @var Logger
     */
    private $logger;

    /**
     * Bulk converter instance.
     *
     * @since 0.1.0
     * @var BulkConverter
     */
    private $bulk_converter;

    /**
     * Settings instance.
     *
     * @since 0.1.0
     * @var Settings
     */
    private $settings;

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->logger = new Logger();
        $this->settings = new Settings();
        $this->bulk_converter = new BulkConverter( $this->logger, $this->settings );
    }

    /**
     * Convert all unconverted media files.
     *
     * ## OPTIONS
     *
     * [--batch-size=<size>]
     * : Number of files to process in each batch.
     * ---
     * default: 10
     * ---
     *
     * ## EXAMPLES
     *
     *     wp flux-media-optimizer convert-all
     *     wp flux-media-optimizer convert-all --batch-size=20
     *
     * @since 0.1.0
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function convert_all( $args, $assoc_args ) {
        $batch_size = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 10;
        
        WP_CLI::log( "Starting bulk conversion with batch size: {$batch_size}" );
        
        $results = $this->bulk_converter->process_bulk_conversion( $batch_size );
        
        WP_CLI::success( sprintf(
            'Bulk conversion completed. Processed: %d, Converted: %d, Errors: %d',
            $results['processed'],
            $results['converted'],
            $results['errors']
        ) );
    }

    /**
     * Clear all Flux Media Optimizer data and converted files.
     *
     * ## EXAMPLES
     *
     *     wp flux-media-optimizer clear-all
     *
     * @since 0.1.0
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function clear_all( $args, $assoc_args ) {
        WP_CLI::confirm( 'Are you sure you want to clear all Flux Media Optimizer data? This will delete all converted files and metadata.' );
        
        // Clear all post meta
        global $wpdb;
        $deleted_meta = $wpdb->query( "DELETE FROM `".esc_sql($wpdb->postmeta)."` WHERE meta_key LIKE '_flux_media_optimizer_%'" );
        
        // Clear all converted files
        $upload_dir = wp_upload_dir();
        $flux_media_optimizer_dir = $upload_dir['basedir'] . '/flux-media-optimizer/';
        
        if ( is_dir( $flux_media_optimizer_dir ) ) {
            $this->delete_directory( $flux_media_optimizer_dir );
        }
        
        WP_CLI::success( "Cleared all Flux Media Optimizer data. Deleted {$deleted_meta} meta entries and converted files." );
    }

    /**
     * Get conversion statistics.
     *
     * ## EXAMPLES
     *
     *     wp flux-media-optimizer stats
     *
     * @since 0.1.0
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function stats( $args, $assoc_args ) {
        // Check cache first
        $cache_key = 'flux_media_optimizer_cli_stats';
        $stats = wp_cache_get( $cache_key, 'flux_media_optimizer' );
        
        if ( false !== $stats ) {
            WP_CLI::log( "Conversion Statistics (cached):" );
            WP_CLI::log( "Total converted files: {$stats['total_converted']}" );
            WP_CLI::log( "Total file size savings: " . size_format( $stats['total_savings'] ?: 0 ) );
            return;
        }

        global $wpdb;
        
        // Get total converted files
        $meta_key = AttachmentMetaHandler::META_KEY_CONVERTED_FORMATS;
        $total_converted = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $meta_key ) );
        
        // Get total file size savings
        $total_savings = $wpdb->get_var( "SELECT SUM(meta_value) FROM `".esc_sql($wpdb->postmeta)."` WHERE meta_key = '_flux_media_optimizer_file_size_savings'" );
        
        // Cache the results for 5 minutes
        $stats = [
            'total_converted' => $total_converted,
            'total_savings' => $total_savings
        ];
        wp_cache_set( $cache_key, $stats, 'flux_media_optimizer', 300 );
        
        WP_CLI::log( "Conversion Statistics:" );
        WP_CLI::log( "Total converted files: {$total_converted}" );
        WP_CLI::log( "Total file size savings: " . size_format( $total_savings ?: 0 ) );
    }

    /**
     * Recursively delete a directory using WordPress filesystem.
     *
     * @since 0.1.0
     * @param string $dir Directory path.
     * @return void
     */
    private function delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        
        // Initialize WordPress filesystem.
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        if ( $wp_filesystem ) {
            // Use WordPress filesystem to remove directory and all contents.
            $wp_filesystem->rmdir( $dir, true );
        } else {
            // Fallback: Remove files individually using wp_delete_file().
            $files = array_diff( scandir( $dir ), [ '.', '..' ] );
            
            foreach ( $files as $file ) {
                $path = $dir . '/' . $file;
                if ( is_dir( $path ) ) {
                    $this->delete_directory( $path );
                } else {
                    wp_delete_file( $path );
                }
            }
        }
    }
}
