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
use FluxMedia\App\Services\WordPressImageRenderer;
use FluxMedia\App\Services\WordPressVideoRenderer;
use FluxMedia\App\Services\AttachmentDetailsMountRenderer;
use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\App\Services\SourceFormatRegistry;
use FluxMedia\App\Services\MediaProcessingServiceLocator;
use FluxMedia\App\Services\ActionSchedulerService;

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
     * Attachment details mount renderer.
     *
     * @since 4.3.0
     * @var AttachmentDetailsMountRenderer
     */
    private $attachment_details_mount_renderer;

    /**
     * Media processing service locator instance.
     *
     * @since 3.0.0
     * @var MediaProcessingServiceLocator
     */
    private $service_locator;

    /**
     * Action Scheduler service instance.
     *
     * @since 3.0.0
     * @var ActionSchedulerService
     */
    private $action_scheduler_service;

    /**
     * Unified conversion retry service.
     *
     * @since 4.3.0
     * @var ConversionRetryService|null
     */
    private $conversion_retry_service;

    /**
     * Shared conversion orchestrator.
     *
     * @since 4.3.0
     * @var ConversionOrchestrator|null
     */
    private $conversion_orchestrator;

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
        $this->logger = \FluxMedia\FluxPlugins\Common\Logger\Logger::get_instance();
        $this->image_converter = $image_converter;
        $this->video_converter = $video_converter;
        $this->image_renderer = new WordPressImageRenderer( $video_converter );
        $this->video_renderer = new WordPressVideoRenderer();
        $this->attachment_details_mount_renderer = new AttachmentDetailsMountRenderer();
        $this->conversion_tracker = new ConversionTracker( $this->logger );
    }

    /**
     * Set service locator instance.
     *
     * @since 3.0.0
     * @param MediaProcessingServiceLocator $service_locator Service locator instance.
     * @return void
     */
    public function set_service_locator( MediaProcessingServiceLocator $service_locator ) {
        $this->service_locator = $service_locator;
    }

    /**
     * Get service locator instance.
     *
     * @since 3.0.0
     * @return MediaProcessingServiceLocator|null Service locator instance or null if not set.
     */
    public function get_service_locator() {
        return $this->service_locator;
    }

    /**
     * Set Action Scheduler service instance.
     *
     * @since 3.0.0
     * @param ActionSchedulerService $action_scheduler_service Action Scheduler service instance.
     * @return void
     */
    public function set_action_scheduler_service( ActionSchedulerService $action_scheduler_service ) {
        $this->action_scheduler_service = $action_scheduler_service;
    }

    /**
     * Set unified conversion retry service.
     *
     * @since 4.3.0
     * @param ConversionRetryService $conversion_retry_service Retry service.
     * @return void
     */
    public function set_conversion_retry_service( ConversionRetryService $conversion_retry_service ) {
        $this->conversion_retry_service = $conversion_retry_service;
    }

    /**
     * Set the shared conversion orchestrator.
     *
     * @since 4.3.0
     * @param ConversionOrchestrator $conversion_orchestrator Orchestrator.
     * @return void
     */
    public function set_conversion_orchestrator( ConversionOrchestrator $conversion_orchestrator ) {
        $this->conversion_orchestrator = $conversion_orchestrator;
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
        // Bulk conversion via Action Scheduler
        // Detection of local vs external processing happens inside callback.
        // Schedule on 'init' hook after Action Scheduler is ready.
        // @since 3.0.3
        // Ensure bulk discovery action is scheduled (replaces WP Cron).
        // Defer to 'init' hook to ensure Action Scheduler is ready.
        $as = $this->action_scheduler_service;
        add_action( 'init', function() use($as) {
            $as->ensure_bulk_discovery_scheduled();
        }, 20 );

        // ===== RENDER IMAGE =====
        // Primary mechanism: WordPress filters (image_downsize, wp_get_attachment_url, wp_get_attachment_image_src)
        // These filters retrieve URLs from AttachmentMetaHandler meta data (single source of truth)
        // Enable filters for frontend, REST API requests, and admin (except media library pages)

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
        
        // Hook into REST API to modify attachment responses directly
        // This ensures block editor gets converted URLs in REST API responses
        add_filter( 'rest_prepare_attachment', [ $this, 'handle_rest_prepare_attachment' ], 10, 3 );
        // Filter srcset to use converted formats (prefer AVIF, fallback to WebP)
        add_filter( 'wp_calculate_image_srcset', [ $this, 'handle_image_srcset_filter' ], 10, 5 );
        // Only register HTML parsing filters when hybrid approach is enabled
        // For non-hybrid, URLs are embedded in block content when edited and WordPress filters handle attachment URLs
        // HTML parsing is only needed for hybrid approach to create picture elements with multiple sources
        if ( Settings::is_image_hybrid_approach_enabled() ) {
            add_filter( 'wp_content_img_tag', [ $this, 'handle_content_images_filter' ], 25, 3 );
            add_filter( 'the_content', [ $this, 'handle_post_content_images_filter' ], 20 );
            add_filter( 'render_block', [ $this, 'handle_render_block_filter' ], 10, 2 );
        }
        // Featured image filters
        add_filter( 'post_thumbnail_html', [ $this, 'handle_post_thumbnail_html' ], 10, 5 );
        add_filter( 'wp_get_attachment_image', [ $this, 'handle_wp_get_attachment_image' ], 10, 5 );
        
        // Always add attachment fields for admin display
        add_filter( 'attachment_fields_to_edit', [ $this, 'handle_attachment_fields_filter' ], 10, 2 );

        // Allow HEIC/HEIF uploads when local Imagick libheif decode is available.
        add_filter( 'upload_mimes', [ $this, 'add_heic_upload_mimes' ] );
        add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_heic_filetype' ], 10, 5 );

        // ===== CLEANUP =====
        // Cleanup hooks
        add_action( 'delete_attachment', [ $this, 'handle_attachment_deletion' ] );
    }

    /**
     * Check if attachment can be processed.
     *
     * Prevents processing if conversion is disabled, already queued for processing,
     * external job is in progress, or auto-convert is disabled for the file type.
     * Allows reprocessing if job is 'completed' or 'failed'.
     *
     * @since 3.0.0
     * @since 4.0.0 Added auto-convert checks based on file type.
     * @since 4.1.0 Added $skip_auto_convert_check parameter for manual convert actions.
     * @param int  $attachment_id            Attachment ID.
     * @param bool $skip_auto_convert_check  If true, skip auto-convert setting checks (for manual convert actions).
     * @return bool True if processing should be skipped, false if processing can proceed.
     */
    private function should_skip_processing( $attachment_id, $skip_auto_convert_check = false ) {
        // Check if already queued for processing
        if ( in_array( $attachment_id, self::$pending_attachments, true ) ) {
            return true;
        }

        // Check if conversion is disabled for this attachment
        if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
            return true;
        }

        // Check external job state - prevent processing if job is 'queued' or 'processing'
        $job_state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
        if ( AttachmentMetaHandler::is_in_flight_job_state( $job_state ) ) {
            return true;
        }

        // Check auto-convert settings based on file type (only for upload hooks, not manual actions)
        if ( ! $skip_auto_convert_check ) {
            $file_path = $this->image_converter->get_optimization_source_path( $attachment_id );
            if ( ! $file_path ) {
                $file_path = get_attached_file( $attachment_id );
            }

            // Determine file type and check auto-convert settings.
            $is_supported_image = $file_path ? $this->image_converter->is_supported_image( $file_path ) : false;
            $is_supported_video = $file_path ? $this->video_converter->is_supported_video( $file_path ) : false;

            if ( $file_path && $is_supported_image && ! Settings::is_image_auto_convert_enabled() ) {
                return true;
            }
            if ( $file_path && $is_supported_video && ! Settings::is_video_auto_convert_enabled() ) {
                return true;
            }
        }

        // Allow processing if all checks pass
        return false;
    }




    /**
     * Handle attachment deletion.
     *
     * Delegates deletion to the processing service (local or external).
     *
     * @since 0.1.0
     * @since 3.0.0 Delegates deletion to the processing service (local or external).
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    public function handle_attachment_deletion( $attachment_id ) {
        if ( ! $this->service_locator ) {
            return;
        }

        // Delegate deletion to the processor
        // The processor handles service-specific deletion logic (file deletion and meta cleanup)
        $processor = $this->service_locator->get_processor();
        $processor->delete_attachment( $attachment_id );
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
     * Primary mechanism for URL conversion. Intercepts ALL WordPress image lookups (thumbnails, medium, large,
     * WooCommerce sizes, custom sizes, etc.). This is the most critical hook as it catches all image size requests
     * before WordPress processes them. URLs are retrieved from AttachmentMetaHandler meta data (single source of truth).
     *
     * @since 3.0.0
     * @param bool|array $default      Default return value (false or array with [url, width, height]).
     * @param int        $attachment_id Attachment ID.
     * @param string|int[] $size      Requested image size (string name or array of dimensions).
     * @return bool|array False if no file URL data (allows WordPress fallback), or array [url, width, height].
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

        // Get file URLs meta
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        if ( empty( $converted_files_by_size ) ) {
            return $default;
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
            return $default;
        }

        // Format priority: AVIF > WebP > original
        $file_url = null;
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $file_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_AVIF, $size_name );
        }
        if ( ! $file_url && isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $file_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_WEBP, $size_name );
        }
        if ( ! $file_url && isset( $converted_files['original'] ) ) {
            $file_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, 'original', $size_name );
        }

        if ( empty( $file_url ) ) {
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
        return [ $file_url, $width, $height ];
    }

    /**
     * Handle attachment URL filter.
     *
     * Primary mechanism for URL conversion. Returns file URL for 'full' size if available.
     * URLs are retrieved from AttachmentMetaHandler meta data (single source of truth).
     * This filter ensures wp_get_attachment_url() returns converted URLs when available.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use AttachmentMetaHandler for size-specific file URL lookup.
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
        } elseif ( isset( $converted_files['original'] ) && is_array( $converted_files['original'] ) && isset( $converted_files['original']['url'] ) ) {
            // For non-image/non-video files (PDFs, CSVs, etc.), use the "original" format URL if it's a CDN URL
            $original_url = $converted_files['original']['url'];
            if ( ! empty( $original_url ) && AttachmentMetaHandler::is_file_url( $original_url ) ) {
                // Return the CDN URL for the original file
                return esc_url_raw( $original_url );
            }
        }

        // Always return original URL as fallback if modified URL is empty/null
        return $url;
    }

    /**
     * Handle attachment image src filter.
     *
     * Primary mechanism for URL conversion. Filters wp_get_attachment_image_src() and maps requested size
     * to file URL from meta. URLs are retrieved from AttachmentMetaHandler meta data (single source of truth).
     * Returns size-specific file URLs for attachment detail pages.
     *
     * @since 1.0.2
     * @since 3.0.0 Updated to use AttachmentMetaHandler for size-specific file URL lookup and removed bypass for upload.php pages.
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

        // Use AttachmentMetaHandler to get file URL for the requested size and format
        // Priority: AVIF > WebP > original
        $file_url = null;
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $file_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_AVIF, $size_name );
        }
        if ( ! $file_url && isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $file_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, Converter::FORMAT_WEBP, $size_name );
        }
        if ( ! $file_url && isset( $converted_files['original'] ) ) {
            $file_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, 'original', $size_name );
        }

        // Update the URL in the image array if file URL is available
        if ( ! empty( $file_url ) && $file_url !== $url ) {
            $image[0] = $file_url;
        }

        return $image;
    }

    /**
     * Handle image srcset filter to generate srcset from file URLs meta.
     *
     * Generates srcset directly from file URLs meta data instead of modifying existing sources.
     * Iterates through all sizes in meta and builds complete srcset array.
     *
     * @since 1.0.0
     * @since 3.0.0 Refactored to generate srcset directly from file URLs meta instead of modifying existing sources.
     * @param array  $sources       Array of image sources (ignored, we generate from file URLs meta).
     * @param array  $size_array    Array of width and height values.
     * @param string $image_src     The 'src' of the image.
     * @param array  $image_meta    The image metadata.
     * @param int    $attachment_id Image attachment ID.
     * @return array|false Srcset array with file URLs, or false if no file URL data.
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

        // Build srcset array from file URLs meta
        $srcset = [];
        foreach ( $converted_files_by_size as $size_name => $formats ) {
            // Extract width from size name
            $width = $this->get_width_from_size_name( $size_name, $attachment_id );
            if ( ! $width ) {
                continue; // Skip if we can't determine width
            }

            // Get URL with format priority
            $file_url = null;
            foreach ( $format_priority as $format ) {
                if ( isset( $formats[ $format ] ) ) {
                    $file_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size_name );
                    if ( $file_url ) {
                        break;
                    }
                }
            }

            if ( $file_url ) {
                $srcset[ $width ] = [
                    'url' => $file_url,
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
     * Only registered when hybrid approach is enabled. For non-hybrid mode, WordPress filters
     * (image_downsize, wp_get_attachment_url, etc.) handle URL conversion via AttachmentMetaHandler.
     * This filter is used for hybrid approach to create picture elements with multiple sources.
     *
     * @since 1.0.0
     * @since 3.0.0 Only registered when hybrid approach is enabled.
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
     * Only registered when hybrid approach is enabled. For non-hybrid mode, WordPress filters
     * (image_downsize, wp_get_attachment_url, etc.) handle URL conversion via AttachmentMetaHandler.
     * Block content URLs are embedded at edit time via REST API filters. This filter parses HTML
     * content at runtime for hybrid approach to create picture elements with multiple sources.
     *
     * @since 1.0.0
     * @since 3.0.0 Only registered when hybrid approach is enabled.
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
        return $this->attachment_details_mount_renderer->modify_attachment_fields( $form_fields, $post );
    }

    /**
     * Manually convert an attachment.
     *
     * Queues attachment for processing on shutdown hook to ensure metadata is stable.
     * Returns success response indicating the conversion has been queued.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to queue processing via shutdown hook instead of processing directly.
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

        if ( ! $this->service_locator ) {
            return [
                'success' => false,
                'errors' => ['Service locator not available'],
            ];
        }

        // Queue for processing on shutdown hook
        // Skip auto-convert check since this is a manual convert action
        $this->queue_attachment_processing( $attachment_id, true );
        
        // For external service, other file types are also processed
        return [
            'success' => true,
            'type' => 'other',
            'queued' => true,
            'message' => 'File processing job has been queued',
        ];
    
    }

    /**
     * Handle AJAX request to convert attachment.
     *
     * @since 0.1.0
     * @since 4.3.0 Uses edit_post attachment meta capability.
     * @return void
     */
    public function handle_ajax_convert_attachment() {
        // Verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'flux_media_optimizer_convert_attachment' ) ) {
            wp_die( esc_html__( 'Security check failed', 'flux-media-optimizer' ) );
        }

        $attachment_id = (int) sanitize_text_field( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
            wp_send_json_error( esc_html__( 'Insufficient permissions', 'flux-media-optimizer' ) );
        }

        // Reset retry cycle and lifecycle meta so Re-convert starts a fresh 3-attempt window.
        if ( $this->conversion_retry_service ) {
            $this->conversion_retry_service->reset_cycle( $attachment_id );
        } else {
            AttachmentMetaHandler::delete_external_job_lifecycle_meta( $attachment_id );
            AttachmentMetaHandler::clear_conversion_failure( $attachment_id );
        }

        $result = $this->convert_attachment( $attachment_id );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            $error_message = implode( ', ', $result['errors'] ?? [ __( 'Unknown error', 'flux-media-optimizer' ) ] );
            wp_send_json_error( esc_html( $error_message ) );
        }
    }

    /**
     * Handle AJAX request to disable conversion for attachment.
     *
     * @since 0.1.0
     * @since 4.3.0 Uses edit_post attachment meta capability.
     * @return void
     */
    public function handle_ajax_disable_conversion() {
        // Verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'flux_media_optimizer_disable_conversion' ) ) {
            wp_die( esc_html__( 'Security check failed', 'flux-media-optimizer' ) );
        }

        $attachment_id = (int) sanitize_text_field( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
            wp_send_json_error( esc_html__( 'Insufficient permissions', 'flux-media-optimizer' ) );
        }

        if ( ! $this->service_locator ) {
            wp_send_json_error( 'Service locator not available' );
        }

        // Delegate deletion to the processor
        // The processor handles service-specific deletion logic (file deletion and meta cleanup)
        $processor = $this->service_locator->get_processor();
        $processor->delete_attachment( $attachment_id );

        // After deletion, mark conversion as disabled
        // (The processor's delete_attachment() clears the disabled flag, so we set it again here)
        AttachmentMetaHandler::disable_conversion( $attachment_id );

        // Remove from conversion tracking
        $this->conversion_tracker->delete_attachment_conversions( $attachment_id );

        wp_send_json_success( esc_html__( 'Conversion disabled successfully', 'flux-media-optimizer' ) );
    }

    /**
     * Handle AJAX request to enable conversion for attachment.
     *
     * @since 0.1.0
     * @since 4.3.0 Uses edit_post attachment meta capability.
     * @return void
     */
    public function handle_ajax_enable_conversion() {
        // Verify nonce
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, 'flux_media_optimizer_enable_conversion' ) ) {
            wp_die( esc_html__( 'Security check failed', 'flux-media-optimizer' ) );
        }

        $attachment_id = (int) sanitize_text_field( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
        if ( ! $attachment_id || ! current_user_can( 'edit_post', $attachment_id ) ) {
            wp_send_json_error( esc_html__( 'Insufficient permissions', 'flux-media-optimizer' ) );
        }

        // Remove conversion disabled flag
        AttachmentMetaHandler::enable_conversion( $attachment_id );

        wp_send_json_success( esc_html__( 'Conversion enabled successfully', 'flux-media-optimizer' ) );
    }


    /**
     * Handle video processing cron job.
     *
     * Routes video conversion to the appropriate processing service.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to route to processing service.
     * @since 3.0.2 Updated to route directly to processor instead of queuing for shutdown.
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    public function handle_process_video_cron( $attachment_id, $file_path ) {
        if ( ! $this->service_locator ) {
            return;
        }

        // Route directly to the processor's process_video_cron method
        $processor = $this->service_locator->get_processor();
        $processor->process_video_cron( $attachment_id, $file_path );
    }


    /**
     * Handle image editor file save to reconvert edited images.
     *
     * Runs when the WP image editor saves a file (e.g., crop/rotate/scale).
     * Queues attachment for processing on shutdown hook to ensure metadata is stable.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to queue processing via shutdown hook instead of processing directly.
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

        // Queue for processing on shutdown hook
        $this->queue_attachment_processing( $post_id );
        
        return $override;
    }

    /**
     * Check if attachment is a media upload (not plugin/theme upload).
     *
     * Verifies that the attachment is a media file in the uploads directory,
     * not a plugin or theme file. Only media uploads should be processed.
     *
     * @since 3.0.0
     * @param int $attachment_id Attachment ID to check.
     * @return bool True if media attachment, false otherwise.
     */
    private function is_media_attachment( $attachment_id ) {
        // Verify post type is attachment
        $post_type = get_post_type( $attachment_id );
        if ( 'attachment' !== $post_type ) {
            return false;
        }

        // Get file path
        $file_path = get_attached_file( $attachment_id );
        if ( empty( $file_path ) ) {
            return false;
        }

        // Normalize paths for comparison
        $file_path = wp_normalize_path( $file_path );
        $upload_dir = wp_upload_dir();
        $upload_basedir = wp_normalize_path( $upload_dir['basedir'] );

        // Check if file is in uploads directory
        // Plugin/theme uploads are in wp-content/plugins or wp-content/themes, not in uploads
        if ( strpos( $file_path, $upload_basedir ) !== 0 ) {
            return false;
        }

        // Additional check: ensure it's not in plugins or themes directories
        $wp_content_dir = wp_normalize_path( WP_CONTENT_DIR );
        $plugins_dir = trailingslashit( $wp_content_dir ) . 'plugins';
        $themes_dir = trailingslashit( $wp_content_dir ) . 'themes';

        // If file is in plugins or themes directory, it's not a media upload
        if ( strpos( $file_path, $plugins_dir ) === 0 || strpos( $file_path, $themes_dir ) === 0 ) {
            return false;
        }

        return true;
    }

    /**
     * Queue attachment for processing on shutdown hook.
     *
     * Unified method to queue attachments for processing. Ensures all data is available
     * (file_path in metadata, finalized file metadata) before processing occurs.
     * All processing callbacks should use this method to queue attachments.
     *
     * @since 3.0.0
     * @since 4.1.0 Added $skip_auto_convert_check parameter for manual convert actions.
     * @param int  $attachment_id            Attachment ID to queue.
     * @param bool $skip_auto_convert_check  If true, skip auto-convert setting checks (for manual convert actions).
     * @return void
     */
    private function queue_attachment_processing( $attachment_id, $skip_auto_convert_check = false ) {
        // Validate attachment ID
        if ( ! $attachment_id || ! is_numeric( $attachment_id ) ) {
            return;
        }

        // Only process media attachments (skip plugin/theme uploads)
        if ( ! $this->is_media_attachment( $attachment_id ) ) {
            return;
        }

        // Check if processing should be skipped (includes pending_attachments check)
        if ( $this->should_skip_processing( $attachment_id, $skip_auto_convert_check ) ) {
            return;
        }

        if ( ! $this->service_locator ) {
            return;
        }

        // Register shutdown hook to process pending attachments after all metadata updates are complete.
        // shutdown hook runs at the very end of the request, ensuring all metadata is stable.
        // Only register once to avoid duplicate processing.
        if ( ! has_action( 'shutdown', [ $this, 'process_queued_attachments' ] ) ) {
            add_action( 'shutdown', [ $this, 'process_queued_attachments' ] );
        }
        
        // Add to pending attachments queue
        self::$pending_attachments[] = $attachment_id;
    }

    /**
     * Schedule metadata processing for later execution.
     *
     * Early hook that detects metadata updates and queues processing via shutdown hook.
     * Necessary because wp_update_attachment_metadata can be called multiple times during upload.
     *
     * @since 3.0.0
     * @param array $data Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array Unmodified metadata.
     */
    public function schedule_metadata_processing( $data, $attachment_id ) {
        $this->queue_attachment_processing( $attachment_id );
        return $data;
    }

    /**
     * Process all queued attachments on shutdown hook.
     *
     * Runs during shutdown hook when all metadata is stable. Processes all queued attachments
     * via service locator. Handles all file types (images, videos, other files).
     * For images: routes to WordPressProvider::process_image_conversion() for WordPress-specific logic.
     * For videos and other files: routes to processing service.
     *
     * @since 3.0.0
     * @since 3.0.2 Updated to route all processing through service.
     * @return void
     */
    public function process_queued_attachments() {
        // If no pending attachments, nothing to process
        if ( empty( self::$pending_attachments ) ) {
            return;
        }

        if ( ! $this->service_locator && ! $this->conversion_orchestrator ) {
            return;
        }

        // Get a copy of pending attachments and clear the original
        $pending = self::$pending_attachments;
        self::$pending_attachments = [];

        foreach ( $pending as $attachment_id ) {
            if ( $this->conversion_orchestrator ) {
                $this->conversion_orchestrator->dispatch(
                    new ConversionRequest( (int) $attachment_id, ConversionRequest::TRIGGER_UPLOAD )
                );
                continue;
            }

            $processor = $this->service_locator->get_processor();
            $processor->process( $attachment_id );
        }
    }


    /**
     * Handle file updates for attachments to trigger reconversion.
     *
     * Queues attachment for processing on shutdown hook to ensure metadata is stable.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to queue processing via shutdown hook instead of processing directly.
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

        // Queue for processing on shutdown hook
        $this->queue_attachment_processing( $attachment_id );
        
        return $file;
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
            } elseif ( isset( $converted_files['original'] ) && is_array( $converted_files['original'] ) && isset( $converted_files['original']['url'] ) ) {
                // For non-image/non-video files (PDFs, CSVs, etc.), use the "original" format URL if it's a CDN URL
                $original_cdn_url = $converted_files['original']['url'];
                if ( ! empty( $original_cdn_url ) && AttachmentMetaHandler::is_file_url( $original_cdn_url ) ) {
                    $modified_url = esc_url_raw( $original_cdn_url );
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

    /**
     * Add HEIC/HEIF MIME types when local decode support is available.
     *
     * @since 4.3.0
     * @param array $mimes Existing upload MIME map.
     * @return array
     */
    public function add_heic_upload_mimes( $mimes ) {
        $processor_detector = new ProcessorDetector();
        $registry = new SourceFormatRegistry();
        $heic_mimes = $registry->get_heic_upload_mimes( $processor_detector->imagick_supports_heic() );

        return array_merge( $mimes, $heic_mimes );
    }

    /**
     * Ensure WordPress recognizes HEIC/HEIF extensions during upload validation.
     *
     * Requires a matching HEIC/HEIF extension, an allowed real MIME when provided,
     * and a decodable temporary file. Renamed or spoofed files stay rejected.
     *
     * @since 4.3.0
     * @param array       $data      File data array.
     * @param string      $file      Full temporary file path.
     * @param string      $filename  File name.
     * @param array       $mimes     Allowed MIME types.
     * @param string|null $real_mime Detected MIME type.
     * @return array
     */
    public function fix_heic_filetype( $data, $file, $filename, $mimes, $real_mime ) {
        $processor_detector = new ProcessorDetector();
        if ( ! $processor_detector->imagick_supports_heic() ) {
            return $data;
        }

        $extension = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
        $registry  = new SourceFormatRegistry();
        $mime      = $registry->get_mime_for_extension( $extension );

        if ( ! $mime ) {
            return $data;
        }

        $allowed_mimes = array_values( $registry->get_heic_upload_mimes( true ) );
        if ( is_string( $real_mime ) && '' !== $real_mime && ! in_array( $real_mime, $allowed_mimes, true ) ) {
            return $data;
        }

        if ( ! is_string( $file ) || '' === $file || ! file_exists( $file ) ) {
            return $data;
        }

        $probe = new HeifCapabilityProbe();
        if ( ! $probe->can_decode_file( $file ) ) {
            return $data;
        }

        $data['ext']             = $extension;
        $data['type']            = $mime;
        $data['proper_filename'] = $data['proper_filename'] ?? false;

        return $data;
    }
}

