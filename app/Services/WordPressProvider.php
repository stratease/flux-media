<?php
/**
 * WordPress provider for Flux Media Optimizer plugin.
 *
 * @package FluxMedia\Providers
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\ImageConverter;
use FluxMedia\App\Services\VideoConverter;

use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\BulkConverter;
use FluxMedia\App\Services\WordPressImageRenderer;
use FluxMedia\App\Services\WordPressVideoRenderer;
use FluxMedia\App\Services\Logger;
use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\GifAnimationDetector;
use FluxMedia\App\Services\MediaProcessingServiceLocator;

/**
 * WordPress provider that handles all WordPress integration.
 *
 * @since 0.1.0
 */
class WordPressProvider {

    /**
     * Logger instance.
     *
     * @since 0.1.0
     * @var Logger
     */
    private $logger;

    /**
     * Image converter instance.
     *
     * @since 0.1.0
     * @var ImageConverter
     */
    private $image_converter;

    /**
     * Video converter instance.
     *
     * @since 0.1.0
     * @var VideoConverter
     */
    private $video_converter;

    /**
     * Conversion tracker instance.
     *
     * @since 0.1.0
     * @var ConversionTracker
     */
    private $conversion_tracker;

    /**
     * Bulk converter instance.
     *
     * @since 0.1.0
     * @var BulkConverter
     */
    private $bulk_converter;

    /**
     * WordPress image renderer instance.
     *
     * @since 0.1.0
     * @var WordPressImageRenderer
     */
    private $image_renderer;

    /**
     * WordPress video renderer instance.
     *
     * @since 1.0.0
     * @var WordPressVideoRenderer
     */
    private $video_renderer;

    /**
     * Media processing service locator instance.
     *
     * @since 3.0.0
     * @var MediaProcessingServiceLocator
     */
    private $service_locator;

    /**
     * Track processed attachments per request to prevent duplicate processing.
     *
     * @since 3.0.0
     * @var array Array of attachment IDs that have been processed in this request.
     */
    private static $processed_attachments = [];

    /**
     * Track attachments pending processing after metadata stabilizes.
     *
     * @since 3.0.0
     * @var array Array of attachment IDs pending processing.
     */
    private static $pending_attachments = [];

    /**
     * Constructor.
     *
     * @since 0.1.0
     * @param ImageConverter $image_converter Image converter service.
     * @param VideoConverter $video_converter Video converter service.
     */
    public function __construct( ImageConverter $image_converter, VideoConverter $video_converter ) {
        $this->logger = new Logger();
        $this->image_converter = $image_converter;
        $this->video_converter = $video_converter;
        $this->image_renderer = new WordPressImageRenderer( $video_converter );
        $this->video_renderer = new WordPressVideoRenderer();
        $this->conversion_tracker = new ConversionTracker( $this->logger );
        $this->bulk_converter = new BulkConverter( $this->logger, $image_converter, $video_converter, $this->conversion_tracker );
    }

    /**
     * Set the service locator instance.
     *
     * @since 3.0.0
     * @param MediaProcessingServiceLocator $service_locator Service locator instance.
     * @return void
     */
    public function set_service_locator( MediaProcessingServiceLocator $service_locator ) {
        $this->service_locator = $service_locator;
    }

    /**
     * Initialize the provider and register WordPress hooks.
     *
     * @since 0.1.0
     * @return void
     */
    public function init() {
        // Service locator initialization is handled by Plugin class.
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @since 0.1.0
     * @since 3.0.0 Optimized hook registration: removed redundant hooks, consolidated to single pipeline via handle_update_attachment_metadata.
     * @return void
     */
    public function register_hooks() {
        // ===== CONVERT MEDIA =====
        // Early hook to detect metadata updates and schedule processing for later.
        // wp_update_attachment_metadata can be called multiple times during upload,
        // so we defer actual processing to a later hook when metadata is stable.
        add_filter( 'wp_update_attachment_metadata', [ $this, 'schedule_metadata_processing' ], 10, 2 );
        
        // Handles file replacements (all file types).
        // External processing: Submits new job for all file types.
        // Local processing: Processes images/videos only.
        add_filter( 'update_attached_file', [ $this, 'handle_update_attached_file' ], 10, 2 );
        
        // Handles image editor saves (images only).
        // External processing: Submits new job for edited image.
        // Local processing: Processes edited image.
        add_filter( 'wp_save_image_editor_file', [ $this, 'handle_wp_save_image_editor_file' ], 10, 5 );
        
        // AJAX handlers for attachment actions
        add_action( 'wp_ajax_flux_media_optimizer_convert_attachment', [ $this, 'handle_ajax_convert_attachment' ] );
        add_action( 'wp_ajax_flux_media_optimizer_disable_conversion', [ $this, 'handle_ajax_disable_conversion' ] );
        add_action( 'wp_ajax_flux_media_optimizer_enable_conversion', [ $this, 'handle_ajax_enable_conversion' ] );
        // Cron job for individual video processing
        // Detection of local vs external processing happens inside callback.
        add_action( 'flux_media_optimizer_process_video', [ $this, 'handle_process_video_cron' ], 10, 2 );
        // Cron job for bulk conversion
        // Detection of local vs external processing happens inside callback.
        if ( Settings::is_bulk_conversion_enabled() ) {
            add_action( 'flux_media_optimizer_bulk_conversion', [ $this, 'handle_bulk_conversion_cron' ] );
            
            // Schedule cron job if not already scheduled
            if ( ! wp_next_scheduled( 'flux_media_optimizer_bulk_conversion' ) ) {
                wp_schedule_event( time(), 'hourly', 'flux_media_optimizer_bulk_conversion' );
            }
        }

        // ===== RENDER IMAGE =====
        // Image rendering hooks - all hooks are registered, hybrid approach is checked inside each callback
        // Enable filters for frontend, REST API requests, and admin (except media library pages)
        // This ensures editor gets converted URLs when saving blocks, while avoiding confusion in media library
        if( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! $this->is_media_library_page() ) {
            // CRITICAL: image_downsize intercepts ALL WordPress image lookups (thumbnails, medium, large, WooCommerce sizes, etc.)
            add_filter( 'image_downsize', [ $this, 'handle_image_downsize_filter' ], 10, 3 );
            // Filter all WordPress functions that return attachment URLs
            // These are the primary hooks that blocks/plugins use to get image URLs
            // When blocks are saved in the editor, REST API requests these URLs, and we convert them here
            // The converted URL is then stored in block attributes, so CSS generation gets the converted URL
            add_filter( 'wp_get_attachment_url', [ $this, 'handle_attachment_url_filter' ], 10, 2 );
            // Note: wp_get_attachment_image_url() doesn't have its own filter, but wp_get_attachment_image_src() does
            // wp_get_attachment_image_url() calls wp_get_attachment_image_src() internally, so our filter on that hook will catch it
            add_filter( 'wp_get_attachment_image_src', [ $this, 'handle_attachment_image_src_filter' ], 10, 4 );
        }
        
        // Hook into REST API to modify attachment responses directly
        // This ensures block editor gets converted URLs in REST API responses
        add_filter( 'rest_prepare_attachment', [ $this, 'handle_rest_prepare_attachment' ], 10, 3 );
        // Filter srcset to use converted formats (prefer AVIF, fallback to WebP)
        add_filter( 'wp_calculate_image_srcset', [ $this, 'handle_image_srcset_filter' ], 10, 5 );
        add_filter( 'wp_content_img_tag', [ $this, 'handle_content_images_filter' ], 25, 3 );
        add_filter( 'the_content', [ $this, 'handle_post_content_images_filter' ], 20 );
        add_filter( 'render_block', [ $this, 'handle_render_block_filter' ], 10, 2 );
        // Featured image filters
        add_filter( 'post_thumbnail_html', [ $this, 'handle_post_thumbnail_html' ], 10, 5 );
        add_filter( 'wp_get_attachment_image', [ $this, 'handle_wp_get_attachment_image' ], 10, 5 );
        
        // Always add attachment fields for admin display
        add_filter( 'attachment_fields_to_edit', [ $this, 'handle_attachment_fields_filter' ], 10, 2 );

        // ===== CLEANUP =====
        // Cleanup hooks
        add_action( 'delete_attachment', [ $this, 'handle_attachment_deletion' ] );
        if ( ! Settings::is_bulk_conversion_enabled() ) {
            // Unschedule cron job if bulk conversion is disabled
            $timestamp = wp_next_scheduled( 'flux_media_optimizer_bulk_conversion' );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, 'flux_media_optimizer_bulk_conversion' );
            }
        }
    }

    /**
     * Check if an attachment has already been processed in this request.
     *
     * @since 3.0.0
     * @param int $attachment_id Attachment ID.
     * @return bool True if already processed, false otherwise.
     */
    private function is_attachment_processed( $attachment_id ) {
        return isset( self::$processed_attachments[ $attachment_id ] );
    }

    /**
     * Mark an attachment as processed in this request.
     *
     * @since 3.0.0
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    private function mark_attachment_processed( $attachment_id ) {
        self::$processed_attachments[ $attachment_id ] = true;
    }

    /**
     * Check if attachment can be processed.
     *
     * Prevents processing if conversion is disabled, already processed in this request, or external job is in progress.
     * Allows reprocessing if job is 'completed' or 'failed'.
     *
     * @since 3.0.0
     * @param int $attachment_id Attachment ID.
     * @return bool True if processing should be skipped, false if processing can proceed.
     */
    private function should_skip_processing( $attachment_id ) {
        // Check if conversion is disabled for this attachment
        if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
            return true;
        }

        // Check if already processed in this request
        if ( $this->is_attachment_processed( $attachment_id ) ) {
            return true;
        }

        // Check external job state - prevent processing if job is 'queued' or 'processing'
        $job_state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
        if ( in_array( $job_state, [ 'queued', 'processing' ], true ) ) {
            return true;
        }

        // Allow processing if state is 'completed', 'failed', or null (no state set)
        return false;
    }




    /**
     * Handle attachment deletion.
     *
     * @since 0.1.0
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    public function handle_attachment_deletion( $attachment_id ) {
        $this->cleanup_converted_files( $attachment_id );
    }


    /**
     * Process image conversion.
     *
     * Converts all WordPress image sizes to WebP/AVIF formats. Supports incremental conversion
     * (skips sizes already fully converted). Does not check disabled flag as explicit conversions should override.
     *
     * @since 2.0.1
     * @since 3.0.0 Added incremental conversion support and animated GIF handling via ImageConverter.
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    public function process_image_conversion( $attachment_id, $file_path ) {
        // Verify file exists before processing
        if ( ! file_exists( $file_path ) ) {
            $this->logger->warning( "Source file does not exist for attachment {$attachment_id}: {$file_path}" );
            return;
        }

        // Check if this is an animated GIF using ImageConverter.
        $is_animated_gif = $this->image_converter->is_animated_gif( $attachment_id );

        // Ensure metadata exists and all sizes are generated.
        // Note: When called from process_metadata_update, metadata already exists.
        // Only generate if called from manual conversion or other contexts.
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $metadata ) || empty( $metadata['file'] ) ) {
            // Generate metadata if it doesn't exist (this will create all sizes).
            // This can trigger wp_update_attachment_metadata hook again, but should_skip_processing will prevent duplicate processing.
            $metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
            if ( empty( $metadata ) ) {
                $this->logger->error( "Failed to generate metadata for attachment {$attachment_id}" );
                return;
            }
            wp_update_attachment_metadata( $attachment_id, $metadata );
            // Re-fetch metadata after generation to ensure we have the latest
            $metadata = wp_get_attachment_metadata( $attachment_id );
        }

        // Get all image sizes for this attachment (includes full + all registered sizes).
        $image_sizes = $this->get_all_image_paths_by_size( $attachment_id );
        
        // For animated GIFs, get the full-size source file path to use for all conversions.
        $full_size_source_path = null;
        if ( $is_animated_gif && isset( $image_sizes['full'] ) ) {
            $full_size_source_path = $image_sizes['full']['file_path'];
            $this->logger->info( "Using full-size animated GIF as source for all size conversions: {$full_size_source_path}" );
        }
        
        if ( empty( $image_sizes ) ) {
            $this->logger->warning( "No image sizes found for attachment {$attachment_id}" );
            return;
        }

        // Get settings and formats
        $settings = [
            'webp_quality' => Settings::get_webp_quality(),
            'avif_quality' => Settings::get_avif_quality(),
            'avif_speed' => Settings::get_avif_speed(),
        ];
        $image_formats = Settings::get_image_formats();
        
        if ( empty( $image_formats ) ) {
            $this->logger->warning( "No image formats configured for conversion. Attachment ID: {$attachment_id}" );
            return;
        }

        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        
        if ( ! $wp_filesystem ) {
            $this->logger->error( "WordPress filesystem not available for attachment {$attachment_id}" );
            return;
        }

        // Get existing converted files to preserve them during reconversion
        // This prevents losing existing formats (AVIF/WebP) when reconverting
        $all_converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        if ( ! is_array( $all_converted_files_by_size ) ) {
            $all_converted_files_by_size = [];
        }
        
        // Clean up any invalid size names that don't match WordPress registered sizes
        // Get valid WordPress size names (includes 'thumbnail', 'medium', 'large', and custom sizes)
        $valid_sizes = get_intermediate_image_sizes();
        // Add 'full' to the list of valid sizes
        $valid_sizes[] = 'full';
        
        foreach ( array_keys( $all_converted_files_by_size ) as $size_name ) {
            // Only keep sizes that are valid WordPress registered sizes
            if ( ! in_array( $size_name, $valid_sizes, true ) ) {
                unset( $all_converted_files_by_size[ $size_name ] );
                $this->logger->info( "Removed invalid size entry (not a WordPress registered size): {$size_name}" );
            }
        }
        
        // Clean up formats that are no longer enabled in settings
        // Remove files, metadata, and tracking records for disabled formats
        $disabled_formats_removed = false;
        $disabled_formats_to_clean = [];
        
        foreach ( $all_converted_files_by_size as $size_name => $size_formats ) {
            if ( ! is_array( $size_formats ) ) {
                continue;
            }
            
            foreach ( $size_formats as $format => $file_path ) {
                // If this format is not in the enabled formats list, remove it
                if ( ! in_array( $format, $image_formats, true ) ) {
                    // Track this format for conversion tracking cleanup
                    if ( ! in_array( $format, $disabled_formats_to_clean, true ) ) {
                        $disabled_formats_to_clean[] = $format;
                    }
                    
                    // Delete the file if it exists
                    if ( is_string( $file_path ) && ! empty( $file_path ) && $wp_filesystem->exists( $file_path ) ) {
                        if ( $wp_filesystem->delete( $file_path ) ) {
                            $this->logger->info( "Removed disabled format file: {$file_path} (format: {$format}, size: {$size_name})" );
                        } else {
                            $this->logger->warning( "Failed to remove disabled format file: {$file_path} (format: {$format}, size: {$size_name})" );
                        }
                    }
                    
                    // Remove from metadata structure
                    unset( $all_converted_files_by_size[ $size_name ][ $format ] );
                    $disabled_formats_removed = true;
                }
            }
            
            // Clean up empty size arrays
            if ( empty( $all_converted_files_by_size[ $size_name ] ) ) {
                unset( $all_converted_files_by_size[ $size_name ] );
            }
        }
        
        // Clean up conversion tracking records for disabled formats
        if ( ! empty( $disabled_formats_to_clean ) ) {
            $deleted_count = $this->conversion_tracker->delete_attachment_conversions_by_formats( $attachment_id, $disabled_formats_to_clean );
            if ( $deleted_count > 0 ) {
                $this->logger->info( "Removed {$deleted_count} conversion tracking record(s) for disabled formats: " . implode( ', ', $disabled_formats_to_clean ) );
            }
        }
        
        // Track formats - will be built from actual converted files after processing
        // This ensures we only track formats that actually exist

        // Convert each image size (full, thumbnail, medium, large, and any custom sizes)
        foreach ( $image_sizes as $size_name => $size_data ) {
            $size_file_path = $size_data['file_path'];
            $size_width = $size_data['width'] ?? null;
            $size_height = $size_data['height'] ?? null;
            
            // For animated GIFs, use the full-size source file instead of the static thumbnail.
            $source_file_path = $size_file_path;
            if ( $is_animated_gif && $size_name !== 'full' && $full_size_source_path ) {
                $source_file_path = $full_size_source_path;
                $this->logger->info( "Using full-size animated GIF source for size '{$size_name}' conversion to preserve animation" );
            }
            
            // Skip if source file doesn't exist
            if ( ! $wp_filesystem->exists( $source_file_path ) ) {
                $this->logger->warning( "Source file not found for attachment {$attachment_id}, size {$size_name}: {$source_file_path}" );
                continue;
            }
            
            // Get file path components for destination (use the size file path structure)
            $size_file_path_normalized = wp_normalize_path( $size_file_path );
            $size_file_dir = dirname( $size_file_path_normalized );
            $size_file_info = pathinfo( $size_file_path_normalized );
            $size_file_name = $size_file_info['filename'];
            
            // Create destination paths for all requested formats
            $destination_paths = [];
            foreach ( $image_formats as $format ) {
                $destination_paths[ $format ] = trailingslashit( $size_file_dir ) . $size_file_name . '.' . $format;
            }
            
            // Add resize dimensions to settings for animated GIFs if this is not the full size.
            $conversion_settings = $settings;
            if ( $is_animated_gif && $size_name !== 'full' && $size_width && $size_height ) {
                $conversion_settings['resize_width'] = $size_width;
                $conversion_settings['resize_height'] = $size_height;
                $this->logger->debug( "Adding resize dimensions for animated GIF: {$size_width}x{$size_height}" );
            }
            
            // Process this size
            $results = $this->image_converter->process_image( $source_file_path, $destination_paths, $conversion_settings );
            
            if ( ! $results['success'] ) {
                $this->logger->warning( "Image conversion failed for attachment {$attachment_id}, size {$size_name}: " . implode( ', ', $results['errors'] ?? [] ) );
                continue;
            }

            // Get file sizes for statistics tracking - use source file size for animated GIFs.
            $size_original_size = $wp_filesystem->size( $source_file_path );
            
            // Initialize size array if needed
            if ( ! isset( $all_converted_files_by_size[ $size_name ] ) ) {
                $all_converted_files_by_size[ $size_name ] = [];
            }
            
            // Store original file URL and size.
            // Get the original file URL for this size.
            $original_file_url = '';
            if ( 'full' === $size_name ) {
                $original_file_url = wp_get_attachment_url( $attachment_id );
            } else {
                // For other sizes, get the URL from metadata.
                $metadata = wp_get_attachment_metadata( $attachment_id );
                if ( ! empty( $metadata['sizes'][ $size_name ]['file'] ) ) {
                    $upload_dir = wp_upload_dir();
                    $file_dir = dirname( $metadata['file'] );
                    $original_file_url = $upload_dir['baseurl'] . '/' . $file_dir . '/' . $metadata['sizes'][ $size_name ]['file'];
                }
            }
            
            if ( $size_original_size > 0 ) {
                // Store original file details.
                AttachmentMetaHandler::set_file_url_and_size( $attachment_id, 'original', $size_name, $original_file_url ?: $source_file_path, $size_original_size );
                
                // Also add to local array so it's included when we save the batch.
                // Convert path to URL if needed (same logic as set_file_url_and_size).
                $url_to_store = $original_file_url;
                if ( empty( $url_to_store ) ) {
                    // Convert file path to URL.
                    $upload_dir = wp_upload_dir();
                    $upload_path = wp_normalize_path( $upload_dir['basedir'] );
                    $source_file_path_normalized = wp_normalize_path( $source_file_path );
                    if ( strpos( $source_file_path_normalized, $upload_path ) === 0 ) {
                        $relative_path = str_replace( $upload_path, '', $source_file_path_normalized );
                        $relative_path = ltrim( $relative_path, '/' );
                        $url_to_store = $upload_dir['baseurl'] . '/' . $relative_path;
                    } else {
                        $url_to_store = wp_get_attachment_url( $attachment_id );
                    }
                }
                
                if ( $url_to_store ) {
                    $all_converted_files_by_size[ $size_name ]['original'] = [
                        'url' => esc_url_raw( $url_to_store ),
                        'filesize' => $size_original_size,
                    ];
                }
            }
            
            // Store converted files for each format
            foreach ( $results['converted_formats'] as $format ) {
                $converted_file_path = $results['converted_files'][ $format ] ?? '';
                if ( empty( $converted_file_path ) ) {
                    continue;
                }
                
                // Get file size - ensure file exists and is readable
                $converted_size = 0;
                if ( $wp_filesystem->exists( $converted_file_path ) ) {
                    $converted_size = $wp_filesystem->size( $converted_file_path );
                    // Validate that we got a valid size (greater than 0)
                    if ( $converted_size <= 0 ) {
                        // Fallback to PHP filesize if wp_filesystem returns invalid size
                        if ( file_exists( $converted_file_path ) ) {
                            $converted_size = filesize( $converted_file_path );
                        }
                    }
                } elseif ( file_exists( $converted_file_path ) ) {
                    // Fallback to PHP filesize if wp_filesystem doesn't exist but file does
                    $converted_size = filesize( $converted_file_path );
                }
                
                // Record conversion for statistics tracking (track all sizes for accurate savings calculation)
                $this->conversion_tracker->record_conversion( $attachment_id, $format, $size_original_size, $converted_size, $size_name );
                
                // Store URL and size together using unified structure.
                // Only store if we have a valid file size (greater than 0)
                if ( $converted_size > 0 ) {
                    AttachmentMetaHandler::set_file_url_and_size( $attachment_id, $format, $size_name, $converted_file_path, $converted_size );
                }
                
                // Also store in local array for batch update.
                $all_converted_files_by_size[ $size_name ][ $format ] = [
                    'url' => $converted_file_path,
                    'filesize' => $converted_size,
                ];
            }
        }
        
        // Build final formats list - only include formats that actually exist in converted files
        $final_formats = [];
        foreach ( $all_converted_files_by_size as $size_formats ) {
            if ( ! is_array( $size_formats ) ) {
                continue;
            }
            foreach ( array_keys( $size_formats ) as $format ) {
                // Only include formats that are enabled AND exist in converted files
                if ( in_array( $format, $image_formats, true ) && ! in_array( $format, $final_formats, true ) ) {
                    $final_formats[] = $format;
                }
            }
        }

        // Update WordPress meta with all converted files (organized by size)
        // Update even if we only removed disabled formats (not just when new conversions happened)
        if ( ! empty( $all_converted_files_by_size ) || $disabled_formats_removed ) {
            AttachmentMetaHandler::set_converted_files_grouped_by_size( $attachment_id, $all_converted_files_by_size );
            
            
            // Update formats list - only include formats that actually exist
            AttachmentMetaHandler::set_converted_formats( $attachment_id, $final_formats );
            
            // Only update conversion date if we actually converted something (not just cleaned up)
            if ( ! $disabled_formats_removed || ! empty( $all_converted_files_by_size ) ) {
                AttachmentMetaHandler::set_conversion_date_now( $attachment_id );
            }
        } else {
            $this->logger->error( "Image conversion failed for attachment {$attachment_id}: No sizes were successfully converted" );
        }
    }

    /**
     * Get all image paths by size for an attachment.
     *
     * Retrieves file paths for all WordPress image sizes including 'full' and all intermediate sizes.
     * Uses WordPress filesystem API for file operations.
     *
     * @since 1.0.0
     * @param int $attachment_id Attachment ID.
     * @return array Array of size_name => ['file_path' => path, 'width' => int, 'height' => int].
     */
    private function get_all_image_paths_by_size( $attachment_id ) {
        $sizes = [];
        
        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        
        if ( ! $wp_filesystem ) {
            return $sizes;
        }
        
        // Add full size
        $full_file_path = get_attached_file( $attachment_id );
        if ( $full_file_path && $wp_filesystem->exists( $full_file_path ) ) {
            $metadata = wp_get_attachment_metadata( $attachment_id );
            $sizes['full'] = [
                'file_path' => wp_normalize_path( $full_file_path ),
                'width' => $metadata['width'] ?? 0,
                'height' => $metadata['height'] ?? 0,
            ];
        }
        
        // Get all intermediate sizes
        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $metadata['sizes'] ) && ! empty( $full_file_path ) ) {
            // Get valid WordPress size names (includes 'thumbnail', 'medium', 'large', and custom sizes)
            $valid_sizes = get_intermediate_image_sizes();
            // Add 'full' to the list of valid sizes
            $valid_sizes[] = 'full';
            
            // Build directory path using PHP dirname function
            $file_dir = dirname( wp_normalize_path( $full_file_path ) );
            
            foreach ( $metadata['sizes'] as $size_name => $size_data ) {
                // Only process sizes that are valid WordPress registered sizes
                if ( ! in_array( $size_name, $valid_sizes, true ) ) {
                    continue;
                }
                
                // Build full path to size file using WordPress path functions
                $size_file_path = trailingslashit( $file_dir ) . $size_data['file'];
                $size_file_path = wp_normalize_path( $size_file_path );
                
                if ( $wp_filesystem->exists( $size_file_path ) ) {
                    $sizes[ $size_name ] = [
                        'file_path' => $size_file_path,
                        'width' => $size_data['width'] ?? 0,
                        'height' => $size_data['height'] ?? 0,
                    ];
                }
            }
        }
        
        return $sizes;
    }

    /**
     * Process video conversion.
     *
     * @since 1.0.0
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    public function process_video_conversion( $attachment_id, $file_path ) {
        // Get upload directory info
        $file_info = pathinfo( $file_path );
        $file_dir = $file_info['dirname'];
        $file_name = $file_info['filename'];

        // Get settings from WordPress
        $settings = [
            'video_hybrid_approach' => Settings::is_video_hybrid_approach_enabled(),
            'video_av1_crf' => Settings::get_video_av1_crf(),
            'video_av1_cpu_used' => Settings::get_video_av1_cpu_used(),
            'video_webm_crf' => Settings::get_video_webm_crf(),
            'video_webm_speed' => Settings::get_video_webm_speed(),
        ];

        // Create destination paths for requested formats
        $destination_paths = [];
        $video_formats = Settings::get_video_formats();
        
        // Ensure video_formats is an array
        if ( ! is_array( $video_formats ) ) {
            $video_formats = [];
        }
        
        // Log formats being processed for debugging
        if ( empty( $video_formats ) ) {
            $this->logger->warning( "No video formats configured for conversion. Attachment ID: {$attachment_id}" );
        }

        foreach ( $video_formats as $format ) {
            $destination_paths[ $format ] = $file_dir . '/' . $file_name . '.' . $format;
        }

        // Process the video
        $results = $this->video_converter->process_video( $file_path, $destination_paths, $settings );

        // Handle results
        if ( $results['success'] ) {
            // Initialize WordPress filesystem for file operations
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();
            
            global $wp_filesystem;
            
            // Get original file size
            $original_size = $wp_filesystem && $wp_filesystem->exists( $file_path ) ? $wp_filesystem->size( $file_path ) : 0;

            // Store original file URL and size.
            $original_file_url = wp_get_attachment_url( $attachment_id );
            if ( $original_size > 0 ) {
                // Store original file details.
                AttachmentMetaHandler::set_file_url_and_size( $attachment_id, 'original', 'full', $original_file_url ?: $file_path, $original_size );
                
                // Also add to local array so it's included when we save the batch.
                // Convert path to URL if needed (same logic as set_file_url_and_size).
                $url_to_store = $original_file_url;
                if ( empty( $url_to_store ) ) {
                    // Convert file path to URL.
                    $upload_dir = wp_upload_dir();
                    $upload_path = wp_normalize_path( $upload_dir['basedir'] );
                    $file_path_normalized = wp_normalize_path( $file_path );
                    if ( strpos( $file_path_normalized, $upload_path ) === 0 ) {
                        $relative_path = str_replace( $upload_path, '', $file_path_normalized );
                        $relative_path = ltrim( $relative_path, '/' );
                        $url_to_store = $upload_dir['baseurl'] . '/' . $relative_path;
                    } else {
                        $url_to_store = wp_get_attachment_url( $attachment_id );
                    }
                }
                
                if ( $url_to_store ) {
                    $converted_files_by_size['full']['original'] = [
                        'url' => esc_url_raw( $url_to_store ),
                        'filesize' => $original_size,
                    ];
                }
            }

            // Record conversion with file size data for each format
            // Videos don't have multiple sizes, so use 'full' as size_name
            foreach ( $results['converted_formats'] as $format ) {
                $converted_file_path = $results['converted_files'][ $format ] ?? '';
                $converted_size = $wp_filesystem && $wp_filesystem->exists( $converted_file_path ) ? $wp_filesystem->size( $converted_file_path ) : 0;
                
                $this->conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $converted_size, 'full' );
                
                // Store URL and size together using unified structure.
                AttachmentMetaHandler::set_file_url_and_size( $attachment_id, $format, 'full', $converted_file_path, $converted_size );
            }

            // Update WordPress meta
            AttachmentMetaHandler::set_converted_formats( $attachment_id, $results['converted_formats'] );
            AttachmentMetaHandler::set_conversion_date_now( $attachment_id );
            
            // Store in size-specific format
            // Note: URLs and sizes are already stored via set_file_url_and_size() calls above.
            $converted_files_by_size = [
                'full' => [],
            ];
            foreach ( $results['converted_files'] as $format => $file_path ) {
                $converted_size = $wp_filesystem && $wp_filesystem->exists( $file_path ) ? $wp_filesystem->size( $file_path ) : 0;
                $converted_files_by_size['full'][ $format ] = [
                    'url' => $file_path,
                    'filesize' => $converted_size,
                ];
            }
            AttachmentMetaHandler::set_converted_files_grouped_by_size( $attachment_id, $converted_files_by_size );

            // Video conversion completed
        } else {
            $this->logger->error( "Video conversion failed for attachment {$attachment_id}: " . implode( ', ', $results['errors'] ) );
        }
    }

    /**
     * Clean up converted files when attachment is deleted.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use size-specific structure from AttachmentMetaHandler.
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    private function cleanup_converted_files( $attachment_id ) {
        // Get converted files by size (new structure)
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        
        if ( empty( $converted_files_by_size ) ) {
            return;
        }

        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        
        $deleted_count = 0;
        $total_count = 0;

        // Delete files from size-specific structure
        if ( ! empty( $converted_files_by_size ) ) {
            foreach ( $converted_files_by_size as $size_name => $size_formats ) {
                if ( ! is_array( $size_formats ) ) {
                    continue;
                }
                foreach ( $size_formats as $format => $data ) {
                    // Extract URL/path from unified structure
                    $url_or_path = null;
                    if ( is_array( $data ) && isset( $data['url'] ) ) {
                        $url_or_path = $data['url'];
                    } elseif ( is_string( $data ) ) {
                        $url_or_path = $data;
                    }
                    
                    // Skip if invalid or CDN URL
                    if ( ! is_string( $url_or_path ) || empty( $url_or_path ) ) {
                        continue;
                    }
                    
                    // Skip CDN URLs (only remove from meta, don't delete)
                    if ( AttachmentMetaHandler::is_file_url( $url_or_path ) ) {
                        continue;
                    }
                    
                    $total_count++;
                    if ( $wp_filesystem && $wp_filesystem->exists( $url_or_path ) && $wp_filesystem->delete( $url_or_path ) ) {
                        $deleted_count++;
                        $this->logger->info( "Deleted converted file: {$url_or_path} (size: {$size_name}, format: {$format})" );
                    } else {
                        $this->logger->warning( "Failed to delete converted file: {$url_or_path} (size: {$size_name}, format: {$format})" );
                    }
                }
            }
        }
        

        // Clear post meta data
        AttachmentMetaHandler::delete_all( $attachment_id );

        $this->logger->info( "Deleted {$deleted_count}/{$total_count} converted files for attachment {$attachment_id}" );
    }

    /**
     * Get converted file path for a specific format.
     *
     * @since 0.1.0
     * @param int    $attachment_id WordPress attachment ID.
     * @param string $format Target format (webp, avif, av1, webm).
     * @return string|null File path or null if not found.
     */
    public function get_converted_file_path( $attachment_id, $format ) {
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] )
            ? $converted_files_by_size['full']
            : [];
        
        // Extract URL from unified structure.
        if ( isset( $converted_files[ $format ] ) && is_array( $converted_files[ $format ] ) && isset( $converted_files[ $format ]['url'] ) ) {
            return $converted_files[ $format ]['url'];
        }
        return null;
    }

    /**
     * Check if attachment has converted files.
     *
     * @since 0.1.0
     * @param int $attachment_id WordPress attachment ID.
     * @return bool True if converted files exist, false otherwise.
     */
    public function has_converted_files( $attachment_id ) {
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        return ! empty( $converted_files_by_size );
    }

    /**
     * Delete all converted files for an attachment.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use size-specific structure from AttachmentMetaHandler.
     * @param int $attachment_id WordPress attachment ID.
     * @return bool True if files were deleted successfully, false otherwise.
     */
    public function delete_converted_files( $attachment_id ) {
        // Get converted files by size
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        
        // Validate data structures to prevent type errors
        if ( ! empty( $converted_files_by_size ) && ! is_array( $converted_files_by_size ) ) {
            $this->logger->warning( "Invalid converted_files_by_size structure for attachment {$attachment_id}: expected array, got " . gettype( $converted_files_by_size ) );
            $converted_files_by_size = [];
        }
        
        if ( empty( $converted_files_by_size ) ) {
            return true; // Nothing to delete
        }

        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        
        $deleted_count = 0;
        $total_count = 0;

        // Delete files from size-specific structure
        if ( ! empty( $converted_files_by_size ) ) {
            foreach ( $converted_files_by_size as $size_name => $size_formats ) {
                if ( ! is_array( $size_formats ) ) {
                    continue;
                }
                foreach ( $size_formats as $format => $data ) {
                    // Extract URL/path from unified structure.
                    if ( ! is_array( $data ) || ! isset( $data['url'] ) ) {
                        continue;
                    }
                    
                    $url_or_path = $data['url'];
                    
                    // Ensure url_or_path is a string (skip if invalid)
                    if ( ! is_string( $url_or_path ) || empty( $url_or_path ) ) {
                        continue;
                    }
                    $total_count++;
                    
                    // Check if it's a URL (CDN) - skip deletion for URLs, only remove from meta.
                    if ( AttachmentMetaHandler::is_file_url( $url_or_path ) ) {
                        $this->logger->info( "Skipped deletion of CDN URL: {$url_or_path} (size: {$size_name}, format: {$format})" );
                        continue;
                    }
                    
                    // It's a file path, proceed with deletion.
                    if ( $wp_filesystem && $wp_filesystem->exists( $url_or_path ) && $wp_filesystem->delete( $url_or_path ) ) {
                        $deleted_count++;
                        $this->logger->debug( "Deleted converted file: {$url_or_path} (size: {$size_name}, format: {$format})" );
                    } else {
                        $this->logger->warning( "Failed to delete converted file: {$url_or_path} (size: {$size_name}, format: {$format})" );
                    }
                }
            }
        }
        

        // Clear post meta data
        AttachmentMetaHandler::delete_all( $attachment_id );

        $this->logger->debug( "Deleted {$deleted_count}/{$total_count} converted files for attachment {$attachment_id}" );

        return $deleted_count === $total_count;
    }

    /**
     * Check if converted files contain image formats.
     *
     * @since 1.0.0
     * @param array $converted_files Array of converted file paths.
     * @return bool True if image formats are present.
     */
    private function has_image_formats( $converted_files ) {
        return isset( $converted_files[ Converter::FORMAT_AVIF ] ) || isset( $converted_files[ Converter::FORMAT_WEBP ] );
    }

    /**
     * Check if converted files contain video formats.
     *
     * @since 1.0.0
     * @param array $converted_files Array of converted file paths.
     * @return bool True if video formats are present.
     */
    private function has_video_formats( $converted_files ) {
        return isset( $converted_files[ Converter::FORMAT_AV1 ] ) || isset( $converted_files[ Converter::FORMAT_WEBM ] );
    }

    /**
     * Check if we're on a media library page in the admin.
     *
     * We exclude media library pages from URL conversion filters to avoid
     * showing converted URLs in the admin interface, which could be confusing.
     *
     * @since 1.0.2
     * @return bool True if on a media library page, false otherwise.
     */
    private function is_media_library_page() {
        if ( ! is_admin() ) {
            return false;
        }

        // Check using get_current_screen() if available (most reliable)
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            if ( $screen && ( 'upload' === $screen->id || 'attachment' === $screen->post_type ) ) {
                return true;
            }
        }

        // Fallback: check global $pagenow and query vars
        global $pagenow;
        if ( 'upload.php' === $pagenow ) {
            return true;
        }

        if ( in_array( $pagenow, [ 'post.php', 'post-new.php' ], true ) ) {
            $post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : '';
            if ( 'attachment' === $post_type ) {
                return true;
            }

            // Also check if editing an attachment post
            if ( isset( $_GET['post'] ) ) {
                $post_id = absint( $_GET['post'] );
                if ( $post_id && 'attachment' === get_post_type( $post_id ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handle image downsize filter.
     *
     * Intercepts ALL WordPress image lookups (thumbnails, medium, large, WooCommerce sizes, custom sizes, etc.).
     * This is the most critical hook as it catches all image size requests before WordPress processes them.
     *
     * @since 3.0.0
     * @param bool|array $default      Default return value (false or array with [url, width, height]).
     * @param int        $attachment_id Attachment ID.
     * @param string|int[] $size      Requested image size (string name or array of dimensions).
     * @return bool|array False if no CDN data (allows WordPress fallback), or array [url, width, height].
     */
    public function handle_image_downsize_filter( $default, $attachment_id, $size ) {
        if ( ! $attachment_id ) {
            return $default;
        }

        // Bypass only for specific AJAX actions that need original file (image editor previews)
        if ( is_admin() && wp_doing_ajax() ) {
            $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
            if ( in_array( $action, [ 'image-editor', 'imgedit-preview' ], true ) ) {
                return $default;
            }
        }

        // Get CDN meta
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        if ( empty( $converted_files_by_size ) ) {
            return $default; // Return false to allow WordPress fallback
        }

        // Resolve size name from WordPress size parameter
        $size_name = $this->resolve_size_name( $size, $attachment_id );

        // Check if size exists in meta, fallback to 'full' if not
        if ( ! isset( $converted_files_by_size[ $size_name ] ) ) {
            $size_name = 'full';
        }

        // Get converted files for the resolved size
        $converted_files = $converted_files_by_size[ $size_name ] ?? [];
        if ( empty( $converted_files ) ) {
            return $default; // Return false to allow WordPress fallback
        }

        // Format priority: AVIF > WebP > original
        $cdn_url = null;
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $cdn_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_AVIF, $size_name );
        }
        if ( ! $cdn_url && isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $cdn_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_WEBP, $size_name );
        }
        if ( ! $cdn_url && isset( $converted_files['original'] ) ) {
            $cdn_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, 'original', $size_name );
        }

        if ( empty( $cdn_url ) ) {
            return $default; // Return false to allow WordPress fallback
        }

        // Try to get dimensions from attachment metadata
        $width = null;
        $height = null;
        $image_meta = wp_get_attachment_metadata( $attachment_id );
        if ( ! empty( $image_meta ) ) {
            if ( $size_name === 'full' ) {
                $width = isset( $image_meta['width'] ) ? (int) $image_meta['width'] : null;
                $height = isset( $image_meta['height'] ) ? (int) $image_meta['height'] : null;
            } elseif ( isset( $image_meta['sizes'][ $size_name ] ) ) {
                $size_data = $image_meta['sizes'][ $size_name ];
                $width = isset( $size_data['width'] ) ? (int) $size_data['width'] : null;
                $height = isset( $size_data['height'] ) ? (int) $size_data['height'] : null;
            }
        }

        // Return array format: [url, width, height] or [url, null, null] if dimensions unknown
        return [ $cdn_url, $width, $height ];
    }

    /**
     * Handle attachment URL filter.
     *
     * Returns CDN URL for 'full' size if available. Maps to size-specific CDN URLs from meta.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use AttachmentMetaHandler for size-specific CDN URL lookup.
     * @param string $url The attachment URL.
     * @param int    $attachment_id The attachment ID.
     * @return string Modified URL.
     */
    public function handle_attachment_url_filter( $url, $attachment_id ) {
        // Always ensure we have a valid URL to return (never return null or empty)
        if ( ! $attachment_id ) {
            return $url;
        }

        // Bypass only for specific AJAX actions that need original file (image editor previews)
        if ( is_admin() && wp_doing_ajax() ) {
            $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
            if ( in_array( $action, [ 'image-editor', 'imgedit-preview' ], true ) ) {
                return $url;
            }
        }

        // Get converted files for 'full' size (wp_get_attachment_url always returns full size)
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
            ? $converted_files_by_size['full'] 
            : [];
        
        if ( empty( $converted_files ) ) {
            return $url;
        }

        // Determine media type and use appropriate renderer
        if ( $this->has_video_formats( $converted_files ) ) {
            return $this->video_renderer->modify_attachment_url( $url, $attachment_id, $converted_files );
        } elseif ( $this->has_image_formats( $converted_files ) ) {
            return $this->image_renderer->modify_attachment_url( $url, $attachment_id, $converted_files );
        }

        // Always return original URL as fallback if modified URL is empty/null
        return $url;
    }

    /**
     * Handle attachment image src filter.
     *
     * Filters wp_get_attachment_image_src() and maps requested size to CDN URL from meta.
     * Returns size-specific CDN URLs for attachment detail pages.
     *
     * @since 1.0.2
     * @since 3.0.0 Updated to use AttachmentMetaHandler for size-specific CDN URL lookup and removed bypass for upload.php pages.
     * @param array|false  $image         Array of image data (url, width, height) or false if no image.
     * @param int          $attachment_id Image attachment ID.
     * @param string|int[] $size          Requested image size.
     * @param bool         $icon          Whether the image should be treated as an icon.
     * @return array|false Modified image array or false.
     */
    public function handle_attachment_image_src_filter( $image, $attachment_id, $size, $icon ) {
        if ( ! $image || ! is_array( $image ) || ! $attachment_id || $icon ) {
            return $image;
        }

        // Bypass only for specific AJAX actions that need original file (image editor previews)
        if ( is_admin() && wp_doing_ajax() ) {
            $action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : '';
            if ( in_array( $action, [ 'image-editor', 'imgedit-preview' ], true ) ) {
                return $image;
            }
        }

        // Get the URL from the image array
        $url = $image[0] ?? '';
        if ( empty( $url ) ) {
            return $image;
        }

        // Resolve size name from WordPress size parameter (handles both strings and arrays)
        $size_name = $this->resolve_size_name( $size, $attachment_id );

        // Get converted files for the requested size
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size[ $size_name ] ) 
            ? $converted_files_by_size[ $size_name ] 
            : ( ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
                ? $converted_files_by_size['full'] 
                : [] );
        
        if ( empty( $converted_files ) ) {
            return $image;
        }

        // Use AttachmentMetaHandler to get CDN URL for the requested size and format
        // Priority: AVIF > WebP > original
        $cdn_url = null;
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $cdn_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_AVIF, $size_name );
        }
        if ( ! $cdn_url && isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $cdn_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_WEBP, $size_name );
        }
        if ( ! $cdn_url && isset( $converted_files['original'] ) ) {
            $cdn_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, 'original', $size_name );
        }

        // Update the URL in the image array if CDN URL is available
        if ( ! empty( $cdn_url ) && $cdn_url !== $url ) {
            $image[0] = $cdn_url;
        }

        return $image;
    }

    /**
     * Handle image srcset filter to generate srcset from CDN meta.
     *
     * Generates srcset directly from CDN meta data instead of modifying existing sources.
     * Iterates through all sizes in meta and builds complete srcset array.
     *
     * @since 1.0.0
     * @since 3.0.0 Refactored to generate srcset directly from CDN meta instead of modifying existing sources.
     * @param array  $sources       Array of image sources (ignored, we generate from CDN meta).
     * @param array  $size_array    Array of width and height values.
     * @param string $image_src     The 'src' of the image.
     * @param array  $image_meta    The image metadata.
     * @param int    $attachment_id Image attachment ID.
     * @return array|false Srcset array with CDN URLs, or false if no CDN data.
     */
    public function handle_image_srcset_filter( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        if ( ! $attachment_id ) {
            return $sources;
        }

        // Get CDN meta
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        if ( empty( $converted_files_by_size ) ) {
            return $sources;
        }

        // Format priority: AVIF > WebP > original
        $format_priority = [ Converter::FORMAT_AVIF, Converter::FORMAT_WEBP, 'original' ];

        // Build srcset array from CDN meta
        $srcset = [];
        foreach ( $converted_files_by_size as $size_name => $formats ) {
            // Extract width from size name
            $width = $this->get_width_from_size_name( $size_name, $attachment_id );
            if ( ! $width ) {
                continue; // Skip if we can't determine width
            }

            // Get URL with format priority
            $cdn_url = null;
            foreach ( $format_priority as $format ) {
                if ( isset( $formats[ $format ] ) ) {
                    $cdn_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size_name );
                    if ( $cdn_url ) {
                        break;
                    }
                }
            }

            if ( $cdn_url ) {
                $srcset[ $width ] = [
                    'url' => $cdn_url,
                    'descriptor' => 'w',
                    'value' => $width,
                ];
            }
        }

        // Return srcset array if we have entries, otherwise return false for WordPress fallback
        return ! empty( $srcset ) ? $srcset : $sources;
    }

    /**
     * Get size name from width by matching against metadata.
     *
     * @since 1.0.0
     * @param int   $width      Image width.
     * @param array $image_meta Image metadata.
     * @return string Size name or 'full' if not found.
     */
    private function get_size_name_from_width( $width, $image_meta ) {
        if ( empty( $image_meta['sizes'] ) ) {
            return 'full';
        }

        // Check if width matches full size
        if ( isset( $image_meta['width'] ) && (int) $image_meta['width'] === (int) $width ) {
            return 'full';
        }

        // Find matching size by width
        foreach ( $image_meta['sizes'] as $size_name => $size_data ) {
            if ( isset( $size_data['width'] ) && (int) $size_data['width'] === (int) $width ) {
                return $size_name;
            }
        }

        return 'full';
    }

    /**
     * Resolve size name from WordPress size parameter.
     *
     * WordPress can pass either a string (e.g., 'thumbnail') or an array (e.g., [150, 150]).
     * This method resolves arrays to actual size names by checking attachment metadata.
     *
     * @since 3.0.0
     * @param string|int[] $size          WordPress size parameter (string name or array of dimensions).
     * @param int          $attachment_id  Attachment ID to check metadata.
     * @return string Resolved size name ('thumbnail', 'medium', 'large', 'full', etc.).
     */
    private function resolve_size_name( $size, $attachment_id ) {
        // If it's already a string, use it directly
        if ( is_string( $size ) && ! empty( $size ) ) {
            return $size;
        }

        // If it's an array, try to resolve it using WordPress function or metadata
        if ( is_array( $size ) && count( $size ) >= 2 ) {
            $width = (int) $size[0];
            $height = isset( $size[1] ) ? (int) $size[1] : 0;

            // Try WordPress function first (for registered sizes)
            $image_meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! empty( $image_meta ) ) {
                $resolved_name = $this->get_size_name_from_width( $width, $image_meta );
                if ( $resolved_name !== 'full' || ( isset( $image_meta['width'] ) && (int) $image_meta['width'] === $width ) ) {
                    return $resolved_name;
                }
            }

            // Fallback: try to match against common WordPress sizes
            $common_sizes = [
                'thumbnail' => [ 150, 150 ],
                'medium'    => [ 300, 300 ],
                'medium_large' => [ 768, 0 ],
                'large'     => [ 1024, 1024 ],
            ];

            foreach ( $common_sizes as $size_name => $dims ) {
                if ( $dims[0] === $width && ( $dims[1] === 0 || $dims[1] === $height ) ) {
                    return $size_name;
                }
            }
        }

        // Default to 'full' if we can't resolve
        return 'full';
    }

    /**
     * Get width from size name.
     *
     * Extracts width from size keys for srcset generation.
     * Handles both dimension-based sizes (e.g., '1536x1536') and named sizes (e.g., 'thumbnail').
     *
     * @since 3.0.0
     * @param string $size_name    Size name (e.g., 'thumbnail', 'medium', '1536x1536').
     * @param int    $attachment_id Attachment ID to check metadata if needed.
     * @return int|null Width in pixels, or null if not found.
     */
    private function get_width_from_size_name( $size_name, $attachment_id = null ) {
        // Handle dimension-based sizes: '1536x1536' → extract width
        if ( preg_match( '/^(\d+)x\d+$/', $size_name, $matches ) ) {
            return (int) $matches[1];
        }

        // Fallback: Try to get from attachment metadata if attachment_id provided
        if ( $attachment_id ) {
            $image_meta = wp_get_attachment_metadata( $attachment_id );
            if ( ! empty( $image_meta ) ) {
                if ( $size_name === 'full' && isset( $image_meta['width'] ) ) {
                    return (int) $image_meta['width'];
                } elseif ( isset( $image_meta['sizes'][ $size_name ]['width'] ) ) {
                    return (int) $image_meta['sizes'][ $size_name ]['width'];
                }
            }
        }

        return null;
    }

    /**
     * Handle content media filter (images and videos).
     *
     * @since 1.0.0
     * @param string $filtered_media The filtered media HTML.
     * @param string $context The context of the media.
     * @param int    $attachment_id The attachment ID.
     * @return string Modified media HTML.
     */
    public function handle_content_images_filter( $filtered_media, $context, $attachment_id ) {
        // Get converted files from size-specific structure
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
            ? $converted_files_by_size['full'] 
            : [];
        
        if ( empty( $converted_files ) ) {
            return $filtered_media;
        }

        // Determine media type and use appropriate renderer
        if ( $this->has_video_formats( $converted_files ) ) {
            return $this->video_renderer->modify_content_videos( $filtered_media, $context, $attachment_id, $converted_files );
        } elseif ( $this->has_image_formats( $converted_files ) ) {
            return $this->image_renderer->modify_content_images( $filtered_media, $context, $attachment_id, $converted_files );
        }

        return $filtered_media;
    }

    /**
     * Handle post content media filter (images and videos).
     *
     * @since 1.0.0
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function handle_post_content_images_filter( $content ) {
        // Process images first
        $content = $this->image_renderer->modify_post_content_images( $content );
        
        // Process videos
        $content = $this->video_renderer->modify_post_content_videos( $content );
        
        return $content;
    }

    /**
     * Handle render block filter for block editor media (images and videos).
     *
     * @since 1.0.0
     * @param string $block_content The block content.
     * @param array  $block The block data.
     * @return string Modified block content.
     */
    public function handle_render_block_filter( $block_content, $block ) {
        $block_name = $block['blockName'] ?? '';
        
        // Route to appropriate renderer based on block type
        if ( 'core/video' === $block_name ) {
            return $this->video_renderer->modify_block_content( $block_content, $block );
        } elseif ( 'core/image' === $block_name || 'core/post-featured-image' === $block_name ) {
            return $this->image_renderer->modify_block_content( $block_content, $block );
        }

        return $block_content;
    }

    /**
     * Handle post thumbnail HTML filter.
     *
     * Filters the HTML output of the_post_thumbnail() to use converted formats.
     *
     * @since 1.0.0
     * @param string       $html              The post thumbnail HTML.
     * @param int          $post_id           The post ID.
     * @param int          $post_thumbnail_id The post thumbnail ID.
     * @param string|int[] $size               Requested image size.
     * @param string       $attr              Query string of attributes.
     * @return string Modified HTML.
     */
    public function handle_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
        if ( empty( $html ) || ! $post_thumbnail_id ) {
            return $html;
        }

        // Get converted files (check size-specific structure first, fallback to legacy)
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $post_thumbnail_id );
        $size_name = is_array( $size ) ? 'full' : ( $size ?: 'post-thumbnail' );
        
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size[ $size_name ] ) 
            ? $converted_files_by_size[ $size_name ] 
            : ( ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['post-thumbnail'] ) 
                ? $converted_files_by_size['post-thumbnail'] 
                : ( ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
                    ? $converted_files_by_size['full'] 
                    : [] ) );
        
        if ( empty( $converted_files ) ) {
            return $html;
        }

        // Use image renderer to modify the HTML
        return $this->image_renderer->modify_content_images( $html, 'post-thumbnail', $post_thumbnail_id, $converted_files );
    }

    /**
     * Handle wp_get_attachment_image filter.
     *
     * Filters the HTML output of wp_get_attachment_image() to use converted formats.
     *
     * @since 1.0.0
     * @param string       $html          The attachment image HTML.
     * @param int          $attachment_id Attachment ID.
     * @param string|int[] $size          Requested image size.
     * @param bool         $icon          Whether the image should be treated as an icon.
     * @param string[]     $attr          Array of attribute values for the image markup.
     * @return string Modified HTML.
     */
    public function handle_wp_get_attachment_image( $html, $attachment_id, $size, $icon, $attr ) {
        if ( empty( $html ) || ! $attachment_id || $icon ) {
            return $html;
        }

        // Only process images
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! $this->image_converter->is_supported_image( $file_path ) ) {
            return $html;
        }

        // Resolve size name from WordPress size parameter (handles both strings and arrays)
        $size_name = $this->resolve_size_name( $size, $attachment_id );

        // Get converted files (check size-specific structure first, fallback to legacy)
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size[ $size_name ] ) 
            ? $converted_files_by_size[ $size_name ] 
            : ( ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
                ? $converted_files_by_size['full'] 
                : [] );
        
        if ( empty( $converted_files ) ) {
            return $html;
        }

        // Use image renderer to modify the HTML
        return $this->image_renderer->modify_content_images( $html, 'attachment', $attachment_id, $converted_files );
    }

    /**
     * Handle attachment fields filter.
     *
     * @since 0.1.0
     * @param array   $form_fields Attachment form fields.
     * @param \WP_Post $post The attachment post object.
     * @return array Modified form fields.
     */
    public function handle_attachment_fields_filter( $form_fields, $post ) {
        return $this->image_renderer->modify_attachment_fields( $form_fields, $post );
    }

    /**
     * Manually convert an attachment.
     *
     * Uses service locator to route to appropriate processor (local or external).
     * Images are processed synchronously (local) or submitted as jobs (external).
     * Videos are enqueued for async processing.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use service locator pattern for consistent processing routing.
     * @param int $attachment_id WordPress attachment ID.
     * @return array Conversion results.
     */
    public function convert_attachment( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! wp_check_filetype( $file_path )['ext'] ) {
            return [
                'success' => false,
                'errors' => ['Attachment file not found or invalid'],
            ];
        }

        // Check if processing should be skipped
        if ( $this->should_skip_processing( $attachment_id ) ) {
            return [
                'success' => false,
                'errors' => ['Processing skipped: conversion disabled, already processed, or job in progress'],
            ];
        }

        if ( ! $this->service_locator ) {
            return [
                'success' => false,
                'errors' => ['Service locator not available'],
            ];
        }

        // Determine file type for response building
        $is_image = $this->image_converter->is_supported_image( $file_path );
        $is_video = $this->video_converter->is_supported_video( $file_path );

        // Process via service locator
        $processor = $this->service_locator->get_processor();
        $success = $processor->process_manual_conversion( $attachment_id );
        
        if ( ! $success ) {
            return [
                'success' => false,
                'errors' => ['Unsupported file format or processing failed'],
            ];
        }

        // Mark as processed to prevent duplicate processing
        $this->mark_attachment_processed( $attachment_id );

        // Build response array based on file type
        if ( $is_image ) {
            return [
                'success' => true,
                'type' => 'image',
                'converted_files' => AttachmentMetaHandler::get_converted_files_for_size( $attachment_id, 'full' ),
            ];
        } elseif ( $is_video ) {
            return [
                'success' => true,
                'type' => 'video',
                'queued' => true,
                'message' => 'Video conversion has been queued for processing',
                'converted_files' => AttachmentMetaHandler::get_converted_files_for_size( $attachment_id, 'full' ),
            ];
        } else {
            // For external service, other file types are also processed
            return [
                'success' => true,
                'type' => 'other',
                'queued' => true,
                'message' => 'File processing job submitted',
            ];
        }
    }

    /**
     * Handle AJAX request to convert attachment.
     *
     * @since 0.1.0
     * @return void
     */
    public function handle_ajax_convert_attachment() {
        // Verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'flux_media_optimizer_convert_attachment' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $attachment_id = (int) sanitize_text_field( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $attachment_id ) {
            wp_send_json_error( 'Invalid attachment ID' );
        }

        $result = $this->convert_attachment( $attachment_id );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( implode( ', ', $result['errors'] ?? ['Unknown error'] ) );
        }
    }

    /**
     * Handle AJAX request to disable conversion for attachment.
     *
     * @since 0.1.0
     * @return void
     */
    public function handle_ajax_disable_conversion() {
        // Verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'flux_media_optimizer_disable_conversion' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $attachment_id = (int) sanitize_text_field( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $attachment_id ) {
            wp_send_json_error( 'Invalid attachment ID' );
        }

        // Delete all converted files
        $this->delete_converted_files( $attachment_id );

        // Mark as conversion disabled
        AttachmentMetaHandler::disable_conversion( $attachment_id );

        // Remove from conversion tracking
        $this->conversion_tracker->delete_attachment_conversions( $attachment_id );

        wp_send_json_success( 'Conversion disabled successfully' );
    }

    /**
     * Handle AJAX request to enable conversion for attachment.
     *
     * @since 0.1.0
     * @return void
     */
    public function handle_ajax_enable_conversion() {
        // Verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'flux_media_optimizer_enable_conversion' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $attachment_id = (int) sanitize_text_field( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $attachment_id ) {
            wp_send_json_error( 'Invalid attachment ID' );
        }

        // Remove conversion disabled flag
        AttachmentMetaHandler::enable_conversion( $attachment_id );

        wp_send_json_success( 'Conversion enabled successfully' );
    }

    /**
     * Enqueue video processing via WordPress cron.
     *
     * Schedules a single-event cron job to process video conversion asynchronously.
     *
     * @since 1.0.0
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    public function enqueue_video_processing( $attachment_id, $file_path ) {
        // Check if a cron job is already scheduled for this attachment
        $cron_hook = 'flux_media_optimizer_process_video';
        $cron_args = [ $attachment_id, $file_path ];
        
        // Check if this exact cron job is already scheduled
        $scheduled = wp_next_scheduled( $cron_hook, $cron_args );
        
        if ( ! $scheduled ) {
            // Schedule immediate processing (next cron run)
            wp_schedule_single_event( time(), $cron_hook, $cron_args );
        }
    }

    /**
     * Handle video processing cron job.
     *
     * Processes video conversion asynchronously via WordPress cron.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use service locator pattern for consistent processing routing.
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    public function handle_process_video_cron( $attachment_id, $file_path ) {
        // Check if processing should be skipped
        if ( $this->should_skip_processing( $attachment_id ) ) {
            return;
        }

        if ( ! $this->service_locator ) {
            return;
        }

        $processor = $this->service_locator->get_processor();
        $processor->process_video_cron( $attachment_id, $file_path );
        
        // Mark as processed
        $this->mark_attachment_processed( $attachment_id );
    }

    /**
     * Handle bulk conversion cron job.
     *
     * @since 0.1.0
     * @since 3.0.0 Updated to use service locator pattern for consistent processing routing.
     * @return void
     */
    public function handle_bulk_conversion_cron() {
        if ( ! $this->service_locator ) {
            return;
        }

        $processor = $this->service_locator->get_processor();
        $processor->process_bulk_conversion_cron();
    }

    /**
     * Handle image editor file save to reconvert edited images.
     *
     * Runs when the WP image editor saves a file (e.g., crop/rotate/scale).
     * Routes to service locator for processing without overriding core behavior.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use service locator pattern for consistent processing routing.
     * @param mixed       $override   Override value from other filters (usually null).
     * @param string      $filename   Saved filename for the edited image.
     * @param object      $image      Image editor instance.
     * @param string      $mime_type  MIME type of the saved image.
     * @param int|false   $post_id    Attachment ID if available, otherwise false.
     * @return mixed Original $override value.
     */
    public function handle_wp_save_image_editor_file( $override, $filename, $image, $mime_type, $post_id ) {
        if ( empty( $post_id ) ) {
            return $override;
        }

        // Check if processing should be skipped
        if ( $this->should_skip_processing( $post_id ) ) {
            return $override;
        }

        if ( ! $this->service_locator ) {
            return $override;
        }

        $processor = $this->service_locator->get_processor();
        $result = $processor->process_image_editor_save( $override, $filename, $image, $mime_type, $post_id );
        
        // Mark as processed
        $this->mark_attachment_processed( $post_id );
        
        return $result;
    }

    /**
     * Schedule metadata processing for later execution.
     *
     * Early hook that detects metadata updates and schedules processing via shutdown hook.
     * Necessary because wp_update_attachment_metadata can be called multiple times during upload.
     *
     * @since 3.0.0
     * @param array $data Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array Unmodified metadata.
     */
    public function schedule_metadata_processing( $data, $attachment_id ) {
        // Check if processing should be skipped
        if ( $this->should_skip_processing( $attachment_id ) ) {
            return $data;
        }

        if ( ! $this->service_locator ) {
            return $data;
        }

        // Register shutdown hook to process pending attachments after all metadata updates are complete.
        // shutdown hook runs at the very end of the request, ensuring all metadata is stable.
        // Only register once to avoid duplicate processing.
        if ( ! has_action( 'shutdown', [ $this, 'handle_final_metadata_processing' ] ) ) {
            add_action( 'shutdown', [ $this, 'handle_final_metadata_processing' ] );
        }
        
        // Mark this attachment as pending processing.
        // The actual processing will happen in handle_final_metadata_processing() during shutdown.
        if ( ! in_array( $attachment_id, self::$pending_attachments, true ) ) {
            self::$pending_attachments[] = $attachment_id;
        }

        return $data;
    }

    /**
     * Handle final metadata processing after all other callbacks have run.
     *
     * Runs during shutdown hook when metadata is stable. Handles all file types via service locator.
     * For images: all sizes are generated. For non-images: processes immediately.
     * Local processing uses incremental conversion; external processing submits jobs with all sizes.
     *
     * @since 3.0.0
     * @return void
     */
    public function handle_final_metadata_processing() {
        // If no pending attachments, nothing to process
        if ( empty( self::$pending_attachments ) ) {
            return;
        }

        if ( ! $this->service_locator ) {
            return;
        }

        // Get a copy of pending attachments and clear the original
        $pending = self::$pending_attachments;
        self::$pending_attachments = [];

        // Process each pending attachment
        $processor = $this->service_locator->get_processor();
        foreach ( $pending as $attachment_id ) {
            // Check if processing should be skipped (double-check in case state changed)
            if ( $this->should_skip_processing( $attachment_id ) ) {
                continue;
            }

            // Process all file types via service locator with stable metadata
            $processor->process_metadata_update(
                wp_get_attachment_metadata( $attachment_id ), 
                $attachment_id 
            );
            
            // Mark as processed
            $this->mark_attachment_processed( $attachment_id );
        }
    }


    /**
     * Handle file updates for attachments to trigger reconversion.
     *
     * Routes to service locator for processing file replacements.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use service locator pattern and bail early if inside upload callback.
     * @param string $file New file path for the attachment.
     * @param int    $attachment_id Attachment ID.
     * @return string File path (unmodified).
     */
    public function handle_update_attached_file( $file, $attachment_id ) {
        // Bail early if called within a WordPress upload callback.
        if (
            defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_REQUEST['action'] ) &&
            ( $_REQUEST['action'] === 'upload-attachment' || $_REQUEST['action'] === 'async-upload' )
        ) {
            return $file;
        }

        // Check if processing should be skipped
        if ( $this->should_skip_processing( $attachment_id ) ) {
            return $file;
        }

        if ( ! $this->service_locator ) {
            return $file;
        }

        $processor = $this->service_locator->get_processor();
        $result = $processor->process_file_update( $file, $attachment_id );
        
        // Mark as processed
        $this->mark_attachment_processed( $attachment_id );
        
        return $result;
    }

    /**
     * Handle REST API attachment preparation.
     *
     * Modifies REST API responses to include converted URLs in source_url and media_details.
     * This ensures the block editor receives converted URLs when requesting attachment data.
     *
     * @since 1.0.2
     * @param \WP_REST_Response $response The response object.
     * @param \WP_Post          $post     The attachment post object.
     * @param \WP_REST_Request  $request  The request object.
     * @return \WP_REST_Response Modified response object.
     */
    public function handle_rest_prepare_attachment( $response, $post, $request ) {
        if ( ! $response || ! $post || ! isset( $response->data ) ) {
            return $response;
        }

        $attachment_id = $post->ID;

        // Get converted files from size-specific structure
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
            ? $converted_files_by_size['full'] 
            : [];
        
        if ( empty( $converted_files ) ) {
            return $response;
        }

        // Modify source_url in REST API response
        // Prefer WebP over AVIF for source_url to ensure compatibility with plugins that validate URLs
        // AVIF can still be used in srcset and other contexts where it's more appropriate
        if ( isset( $response->data['source_url'] ) && ! empty( $response->data['source_url'] ) ) {
            $original_url = $response->data['source_url'];
            $modified_url = $original_url;
            
            if ( $this->has_video_formats( $converted_files ) ) {
                $modified_url = $this->video_renderer->modify_attachment_url( $original_url, $attachment_id, $converted_files );
            } elseif ( $this->has_image_formats( $converted_files ) ) {
                // Prefer WebP for source_url to ensure compatibility with plugins that validate URLs
                // AVIF can still be used in srcset and other contexts
                if ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
                    $webp_url = $this->image_renderer->get_image_url_from_attachment( $attachment_id, Converter::FORMAT_WEBP, 'full' );
                    if ( ! empty( $webp_url ) ) {
                        $modified_url = $webp_url;
                    }
                } elseif ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
                    $avif_url = $this->image_renderer->get_image_url_from_attachment( $attachment_id, Converter::FORMAT_AVIF, 'full' );
                    if ( ! empty( $avif_url ) ) {
                        $modified_url = $avif_url;
                    }
                }
            }
            
            // Only update if we got a valid modified URL
            if ( ! empty( $modified_url ) && $modified_url !== $original_url ) {
                $response->data['source_url'] = $modified_url;
            }
        }

        // Modify media_details sizes URLs if present
        if ( isset( $response->data['media_details']['sizes'] ) && is_array( $response->data['media_details']['sizes'] ) ) {
            foreach ( $response->data['media_details']['sizes'] as $size_name => &$size_data ) {
                if ( isset( $size_data['source_url'] ) && ! empty( $size_data['source_url'] ) ) {
                    $original_url = $size_data['source_url'];
                    
                    // Get converted files for this specific size
                    $size_converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size[ $size_name ] ) 
                        ? $converted_files_by_size[ $size_name ] 
                        : $converted_files;
                    
                    if ( ! empty( $size_converted_files ) ) {
                        $modified_url = $original_url;
                        
                        if ( $this->has_video_formats( $size_converted_files ) ) {
                            $modified_url = $this->video_renderer->modify_attachment_url( $original_url, $attachment_id, $size_converted_files );
                        } elseif ( $this->has_image_formats( $size_converted_files ) ) {
                            // Prefer WebP for source_url to ensure compatibility with plugins that validate URLs
                            // AVIF can still be used in srcset and other contexts
                            if ( isset( $size_converted_files[ Converter::FORMAT_WEBP ] ) ) {
                                $webp_url = $this->image_renderer->get_image_url_from_attachment( $attachment_id, Converter::FORMAT_WEBP, $size_name );
                                if ( ! empty( $webp_url ) ) {
                                    $modified_url = $webp_url;
                                }
                            } elseif ( isset( $size_converted_files[ Converter::FORMAT_AVIF ] ) ) {
                                $avif_url = $this->image_renderer->get_image_url_from_attachment( $attachment_id, Converter::FORMAT_AVIF, $size_name );
                                if ( ! empty( $avif_url ) ) {
                                    $modified_url = $avif_url;
                                }
                            }
                        }
                        
                        // Only update if we got a valid modified URL
                        if ( ! empty( $modified_url ) && $modified_url !== $original_url ) {
                            $size_data['source_url'] = $modified_url;
                        }
                    }
                }
            }
            unset( $size_data ); // Break reference
        }

        return $response;
    }
}


