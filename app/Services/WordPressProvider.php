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
use FluxMedia\App\Services\QuotaManager;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\BulkConverter;
use FluxMedia\App\Services\WordPressImageRenderer;
use FluxMedia\App\Services\WordPressVideoRenderer;
use FluxMedia\App\Services\Logger;
use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\Converter;

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
     * Quota manager instance.
     *
     * @since 0.1.0
     * @var QuotaManager
     */
    private $quota_manager;

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
        $this->quota_manager = new QuotaManager( $this->logger );
        $this->conversion_tracker = new ConversionTracker( $this->logger );
        $this->bulk_converter = new BulkConverter( $this->logger, $image_converter, $video_converter, $this->quota_manager, $this->conversion_tracker );
    }

    /**
     * Initialize the provider and register WordPress hooks.
     *
     * @since 0.1.0
     * @return void
     */
    public function init() {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_hooks() {
        // ===== CONVERT MEDIA =====
        // Media upload hooks (handles both images and videos)
        add_action( 'add_attachment', [ $this, 'handle_media_upload' ] );
        add_action( 'wp_generate_attachment_metadata', [ $this, 'handle_image_metadata_generation' ], 10, 2 );
        
        // Ensure conversions run when attachments are edited or files are replaced
        add_filter( 'wp_update_attachment_metadata', [ $this, 'handle_update_attachment_metadata' ], 10, 2 );
        add_filter( 'update_attached_file', [ $this, 'handle_update_attached_file' ], 10, 2 );
        add_filter( 'wp_save_image_editor_file', [ $this, 'handle_wp_save_image_editor_file' ], 10, 5 );
        
        // AJAX handlers for attachment actions
        add_action( 'wp_ajax_flux_media_optimizer_convert_attachment', [ $this, 'handle_ajax_convert_attachment' ] );
        add_action( 'wp_ajax_flux_media_optimizer_disable_conversion', [ $this, 'handle_ajax_disable_conversion' ] );
        add_action( 'wp_ajax_flux_media_optimizer_enable_conversion', [ $this, 'handle_ajax_enable_conversion' ] );
        // Cron job for individual video processing
        add_action( 'flux_media_optimizer_process_video', [ $this, 'handle_process_video_cron' ], 10, 2 );
        // Cron job for bulk conversion (only if enabled)
        if ( Settings::is_bulk_conversion_enabled() ) {
            add_action( 'flux_media_optimizer_bulk_conversion', [ $this, 'handle_bulk_conversion_cron' ] );
            
            // Schedule cron job if not already scheduled
            if ( ! wp_next_scheduled( 'flux_media_optimizer_bulk_conversion' ) ) {
                wp_schedule_event( time(), 'hourly', 'flux_media_optimizer_bulk_conversion' );
            }
        }

        // ===== RENDER IMAGE =====
        // Image rendering hooks - all hooks are registered, hybrid approach is checked inside each callback
        if( ! is_admin() ) {
            add_filter( 'wp_get_attachment_url', [ $this, 'handle_attachment_url_filter' ], 10, 2 );
        }
        // Add converted files to attachment metadata so WordPress can match them naturally
        add_filter( 'wp_get_attachment_metadata', [ $this, 'handle_attachment_metadata' ], 10, 2 );
        add_filter( 'wp_content_img_tag', [ $this, 'handle_content_images_filter' ], 25, 3 );
        add_filter( 'the_content', [ $this, 'handle_post_content_images_filter' ], 20 );
        add_filter( 'render_block', [ $this, 'handle_render_block_filter' ], 10, 2 );
        
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
     * Handle media upload (images and videos).
     *
     * @since 0.1.0
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    public function handle_media_upload( $attachment_id ) {
        // Check if conversion is disabled for this attachment
        if ( get_post_meta( $attachment_id, '_flux_media_optimizer_conversion_disabled', true ) ) {
            return;
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! wp_check_filetype( $file_path )['ext'] ) {
            return;
        }

        // Determine file type and process accordingly
        if ( $this->image_converter->is_supported_image( $file_path ) ) {
            // Check if image auto-conversion is enabled
            if ( Settings::is_image_auto_convert_enabled() ) {
                $this->process_image_conversion( $attachment_id, $file_path );
            }
        } elseif ( $this->video_converter->is_supported_video( $file_path ) ) {
            // Check if video auto-conversion is enabled
            if ( Settings::is_video_auto_convert_enabled() ) {
                $this->enqueue_video_processing( $attachment_id, $file_path );
            }
        }
    }

    /**
     * Handle image metadata generation.
     *
     * @since 0.1.0
     * @param array $metadata Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array Modified metadata.
     */
    public function handle_image_metadata_generation( $metadata, $attachment_id ) {
        // This hook is called after image metadata is generated
        // We can use this to ensure our conversion happens after WordPress processes the image
        return $metadata;
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
     * Do not check our disabled flag here - sometimes we run this from explicit image conversions which should override.
     *
     * @since 1.0.0
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    private function process_image_conversion( $attachment_id, $file_path ) {
        // Get upload directory info
        $file_info = pathinfo( $file_path );
        $file_dir = $file_info['dirname'];
        $file_name = $file_info['filename'];

        // Get settings from WordPress
        $settings = [
            'webp_quality' => Settings::get_webp_quality(),
            'avif_quality' => Settings::get_avif_quality(),
            'avif_speed' => Settings::get_avif_speed(),
        ];

        // Create destination paths for requested formats
        $destination_paths = [];
        $image_formats = Settings::get_image_formats();
        
        foreach ( $image_formats as $format ) {
            $destination_paths[ $format ] = $file_dir . '/' . $file_name . '.' . $format;
        }

        // Process the image
        $results = $this->image_converter->process_image( $file_path, $destination_paths, $settings );

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

            // Record conversion with file size data for each format
            // Quota tracking is handled automatically in record_conversion()
            foreach ( $results['converted_formats'] as $format ) {
                $converted_file_path = $results['converted_files'][ $format ] ?? '';
                $converted_size = $wp_filesystem && $wp_filesystem->exists( $converted_file_path ) ? $wp_filesystem->size( $converted_file_path ) : 0;
                
                $this->conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $converted_size );
            }

            // Update WordPress meta
            update_post_meta( $attachment_id, '_flux_media_optimizer_converted_formats', $results['converted_formats'] );
            update_post_meta( $attachment_id, '_flux_media_optimizer_conversion_date', current_time( 'mysql' ) );
            update_post_meta( $attachment_id, '_flux_media_optimizer_converted_files', $results['converted_files'] );

            // Image conversion completed
        } else {
            $this->logger->error( "Image conversion failed for attachment {$attachment_id}: " . implode( ', ', $results['errors'] ) );
        }
    }

    /**
     * Process video conversion.
     *
     * @since 1.0.0
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    private function process_video_conversion( $attachment_id, $file_path ) {
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

            // Record conversion with file size data for each format
            // Quota tracking is handled automatically in record_conversion()
            foreach ( $results['converted_formats'] as $format ) {
                $converted_file_path = $results['converted_files'][ $format ] ?? '';
                $converted_size = $wp_filesystem && $wp_filesystem->exists( $converted_file_path ) ? $wp_filesystem->size( $converted_file_path ) : 0;
                
                $this->conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $converted_size );
            }

            // Update WordPress meta
            update_post_meta( $attachment_id, '_flux_media_optimizer_converted_formats', $results['converted_formats'] );
            update_post_meta( $attachment_id, '_flux_media_optimizer_conversion_date', current_time( 'mysql' ) );
            update_post_meta( $attachment_id, '_flux_media_optimizer_converted_files', $results['converted_files'] );

            // Video conversion completed
        } else {
            $this->logger->error( "Video conversion failed for attachment {$attachment_id}: " . implode( ', ', $results['errors'] ) );
        }
    }

    /**
     * Clean up converted files when attachment is deleted.
     *
     * @since 0.1.0
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    private function cleanup_converted_files( $attachment_id ) {
        $converted_files = get_post_meta( $attachment_id, '_flux_media_optimizer_converted_files', true );
        
        if ( empty( $converted_files ) ) {
            return;
        }

        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        
        $deleted_count = 0;
        $total_count = count( $converted_files );

        foreach ( $converted_files as $format => $file_path ) {
            if ( $wp_filesystem && $wp_filesystem->exists( $file_path ) && $wp_filesystem->delete( $file_path ) ) {
                $deleted_count++;
                $this->logger->info( "Deleted converted file: {$file_path} (format: {$format})" );
            } else {
                $this->logger->warning( "Failed to delete converted file: {$file_path} (format: {$format})" );
            }
        }

        // Clear post meta data
        delete_post_meta( $attachment_id, '_flux_media_optimizer_converted_files' );
        delete_post_meta( $attachment_id, '_flux_media_optimizer_converted_formats' );
        delete_post_meta( $attachment_id, '_flux_media_optimizer_conversion_date' );

        $this->logger->info( "Deleted {$deleted_count}/{$total_count} converted files for attachment {$attachment_id}" );
    }

    /**
     * Get converted file paths for an attachment.
     *
     * @since 0.1.0
     * @param int $attachment_id WordPress attachment ID.
     * @return array Array of format => file_path mappings.
     */
    public function get_converted_files( $attachment_id ) {
        return get_post_meta( $attachment_id, '_flux_media_optimizer_converted_files', true ) ?: [];
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
        $converted_files = $this->get_converted_files( $attachment_id );
        return $converted_files[ $format ] ?? null;
    }

    /**
     * Check if attachment has converted files.
     *
     * @since 0.1.0
     * @param int $attachment_id WordPress attachment ID.
     * @return bool True if converted files exist, false otherwise.
     */
    public function has_converted_files( $attachment_id ) {
        $converted_files = $this->get_converted_files( $attachment_id );
        return ! empty( $converted_files );
    }

    /**
     * Delete all converted files for an attachment.
     *
     * @since 0.1.0
     * @param int $attachment_id WordPress attachment ID.
     * @return bool True if files were deleted successfully, false otherwise.
     */
    public function delete_converted_files( $attachment_id ) {
        $converted_files = $this->get_converted_files( $attachment_id );
        
        if ( empty( $converted_files ) ) {
            return true; // Nothing to delete
        }

        // Initialize WordPress filesystem
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        
        global $wp_filesystem;
        
        $deleted_count = 0;
        $total_count = count( $converted_files );

        foreach ( $converted_files as $format => $file_path ) {
            if ( $wp_filesystem && $wp_filesystem->exists( $file_path ) && $wp_filesystem->delete( $file_path ) ) {
                $deleted_count++;
                $this->logger->info( "Deleted converted file: {$file_path} (format: {$format})" );
            } else {
                $this->logger->warning( "Failed to delete converted file: {$file_path} (format: {$format})" );
            }
        }

        // Clear post meta data
        delete_post_meta( $attachment_id, '_flux_media_optimizer_converted_files' );
        delete_post_meta( $attachment_id, '_flux_media_optimizer_converted_formats' );
        delete_post_meta( $attachment_id, '_flux_media_optimizer_conversion_date' );

        $this->logger->info( "Deleted {$deleted_count}/{$total_count} converted files for attachment {$attachment_id}" );

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
     * Handle attachment metadata filter to add converted files.
     *
     * Adds converted files (AVIF/WebP) to the metadata sizes array so WordPress
     * can naturally match them when getting dimensions. This ensures converted
     * files are recognized as valid image sizes with the same dimensions as the original.
     *
     * @since TBD
     * @param array|false $metadata       Metadata array or false if not found.
     * @param int          $attachment_id  Attachment ID.
     * @return array|false Modified metadata or false.
     */
    public function handle_attachment_metadata( $metadata, $attachment_id ) {
        // If no metadata, return as-is
        if ( ! is_array( $metadata ) || empty( $metadata ) ) {
            return $metadata;
        }
        
        // Get converted files for this attachment
        $converted_files = $this->get_converted_files( $attachment_id );
        if ( empty( $converted_files ) ) {
            return $metadata;
        }
        
        // Ensure sizes array exists
        if ( ! isset( $metadata['sizes'] ) ) {
            $metadata['sizes'] = [];
        }
        
        // Get original file dimensions
        $width = $metadata['width'] ?? 0;
        $height = $metadata['height'] ?? 0;
        
        if ( ! $width || ! $height ) {
            return $metadata;
        }
        
        // Add converted files to sizes array so WordPress can match them
        foreach ( $converted_files as $format => $file_path ) {
            // Only process image formats (AVIF/WebP)
            if ( $format !== Converter::FORMAT_AVIF && $format !== Converter::FORMAT_WEBP ) {
                continue;
            }
            
            // Get the filename from the file path
            $filename = wp_basename( $file_path );
            
            // Create a size entry for the converted file
            // Use format name as the size key (e.g., 'webp', 'avif')
            $size_key = 'flux-' . $format;
            
            // Only add if not already present
            if ( ! isset( $metadata['sizes'][ $size_key ] ) ) {
                $metadata['sizes'][ $size_key ] = [
                    'file' => $filename,
                    'width' => $width,
                    'height' => $height,
                    'mime-type' => $format === Converter::FORMAT_AVIF ? 'image/avif' : 'image/webp',
                ];
                
                // Add filesize if available
                if ( file_exists( $file_path ) ) {
                    $metadata['sizes'][ $size_key ]['filesize'] = filesize( $file_path );
                }
            }
        }
        
        return $metadata;
    }

    /**
     * Handle attachment URL filter.
     *
     * @since 1.0.0
     * @param string $url The attachment URL.
     * @param int    $attachment_id The attachment ID.
     * @return string Modified URL.
     */
    public function handle_attachment_url_filter( $url, $attachment_id ) {
        $converted_files = $this->get_converted_files( $attachment_id );
        if ( empty( $converted_files ) ) {
            return $url;
        }

        // Determine media type and use appropriate renderer
        if ( $this->has_video_formats( $converted_files ) ) {
            return $this->video_renderer->modify_attachment_url( $url, $attachment_id, $converted_files );
        } elseif ( $this->has_image_formats( $converted_files ) ) {
            return $this->image_renderer->modify_attachment_url( $url, $attachment_id, $converted_files );
        }

        return $url;
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
        $converted_files = $this->get_converted_files( $attachment_id );
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
        } elseif ( 'core/image' === $block_name ) {
            return $this->image_renderer->modify_block_content( $block_content, $block );
        }

        return $block_content;
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
     * Images are processed synchronously, videos are enqueued for async processing.
     *
     * @since 1.0.0
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

        // Determine if it's an image or video
        if ( $this->image_converter->is_supported_image( $file_path ) ) {
            $this->process_image_conversion( $attachment_id, $file_path );
            return [
                'success' => true,
                'type' => 'image',
                'converted_files' => $this->get_converted_files( $attachment_id ),
            ];
        } elseif ( $this->video_converter->is_supported_video( $file_path ) ) {
            // Enqueue video processing for async processing
            $this->enqueue_video_processing( $attachment_id, $file_path );
            return [
                'success' => true,
                'type' => 'video',
                'queued' => true,
                'message' => 'Video conversion has been queued for processing',
                'converted_files' => $this->get_converted_files( $attachment_id ),
            ];
        }

        return [
            'success' => false,
            'errors' => ['Unsupported file format'],
        ];
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
        update_post_meta( $attachment_id, '_flux_media_optimizer_conversion_disabled', true );

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
        delete_post_meta( $attachment_id, '_flux_media_optimizer_conversion_disabled' );

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
    private function enqueue_video_processing( $attachment_id, $file_path ) {
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
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    public function handle_process_video_cron( $attachment_id, $file_path ) {
        // Verify attachment still exists
        if ( ! get_post( $attachment_id ) ) {
            $this->logger->warning( "Video processing cron skipped: attachment {$attachment_id} no longer exists" );
            return;
        }

        // Check if conversion is disabled for this attachment
        if ( get_post_meta( $attachment_id, '_flux_media_optimizer_conversion_disabled', true ) ) {
            $this->logger->info( "Video processing cron skipped: conversion disabled for attachment {$attachment_id}" );
            return;
        }

        // Verify file still exists
        if ( ! file_exists( $file_path ) ) {
            $this->logger->warning( "Video processing cron skipped: file not found for attachment {$attachment_id}: {$file_path}" );
            return;
        }

        // Verify it's still a supported video
        if ( ! $this->video_converter->is_supported_video( $file_path ) ) {
            $this->logger->warning( "Video processing cron skipped: unsupported video format for attachment {$attachment_id}" );
            return;
        }

        // Process the video conversion
        $this->process_video_conversion( $attachment_id, $file_path );
    }

    /**
     * Handle bulk conversion cron job.
     *
     * @since 0.1.0
     * @return void
     */
    public function handle_bulk_conversion_cron() {
        // Check if bulk conversion is enabled
        if ( ! Settings::is_bulk_conversion_enabled() ) {
            return;
        }

        // Check if auto-conversion is enabled
        if ( ! Settings::is_image_auto_convert_enabled() && ! Settings::is_video_auto_convert_enabled() ) {
            return;
        }

        // Process bulk conversion with small batch size for cron
        $results = $this->bulk_converter->process_bulk_conversion( 5 );

        $this->logger->info( 'Bulk conversion cron completed. Processed: ' . $results['processed'] . ', Converted: ' . $results['converted'] . ', Errors: ' . $results['errors'] );
    }

    /**
     * Handle image editor file save to reconvert edited images.
     *
     * This runs when the WP image editor saves a file (e.g., crop/rotate/scale).
     * Do not override core behavior; just trigger conversion and return $override.
     *
     * @since TBD
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

        // Bail if conversion disabled for this attachment
        if ( get_post_meta( $post_id, '_flux_media_optimizer_conversion_disabled', true ) ) {
            return $override;
        }

        if ( ! $filename || ! wp_check_filetype( $filename )['ext'] ) {
            return $override;
        }

        // Only process supported images
        if ( $this->image_converter->is_supported_image( $filename ) ) {
            $this->process_image_conversion( (int) $post_id, $filename );
        }

        return $override;
    }

    /**
     * Handle attachment metadata updates to reconvert edited images.
     *
     * Runs when image metadata is updated, including after edits like crop/rotate.
     *
     * @since TBD
     * @param array $data Attachment metadata.
     * @param int   $attachment_id Attachment ID.
     * @return array Unmodified metadata array.
     */
    public function handle_update_attachment_metadata( $data, $attachment_id ) {
        // Bail if conversion disabled for this attachment
        if ( get_post_meta( $attachment_id, '_flux_media_optimizer_conversion_disabled', true ) ) {
            return $data;
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! wp_check_filetype( $file_path )['ext'] ) {
            return $data;
        }

        // Process based on file type
        if ( $this->image_converter->is_supported_image( $file_path ) ) {
            if ( Settings::is_image_auto_convert_enabled() ) {
                $this->process_image_conversion( $attachment_id, $file_path );
            }
        } elseif ( $this->video_converter->is_supported_video( $file_path ) ) {
            if ( Settings::is_video_auto_convert_enabled() ) {
                $this->enqueue_video_processing( $attachment_id, $file_path );
            }
        }

        return $data;
    }

    /**
     * Handle file updates for attachments to trigger reconversion.
     *
     * Fires when the attached file path is updated (e.g., replace media or edit creates a new file).
     * Must return the (possibly unchanged) file path per filter contract.
     *
     * @since TBD
     * @param string $file New file path for the attachment.
     * @param int    $attachment_id Attachment ID.
     * @return string File path (unmodified).
     */
    public function handle_update_attached_file( $file, $attachment_id ) {
        // Bail if conversion disabled for this attachment
        if ( get_post_meta( $attachment_id, '_flux_media_optimizer_conversion_disabled', true ) ) {
            return $file;
        }

        if ( ! $file || ! wp_check_filetype( $file )['ext'] ) {
            return $file;
        }

        // Process based on file type
        if ( $this->image_converter->is_supported_image( $file ) ) {
            if ( Settings::is_image_auto_convert_enabled() ) {
                $this->process_image_conversion( $attachment_id, $file );
            }
        } elseif ( $this->video_converter->is_supported_video( $file ) ) {
            if ( Settings::is_video_auto_convert_enabled() ) {
                $this->enqueue_video_processing( $attachment_id, $file );
            }
        }

        return $file;
    }
}
