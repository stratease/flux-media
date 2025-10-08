<?php
/**
 * WordPress provider for Flux Media plugin.
 *
 * @package FluxMedia\Providers
 * @since 0.1.0
 */

namespace FluxMedia\Providers;

use FluxMedia\Services\ImageConverter;
use FluxMedia\Services\VideoConverter;
use FluxMedia\Services\QuotaManager;
use FluxMedia\Services\ConversionTracker;
use FluxMedia\Public\WordPressImageRenderer;
use FluxMedia\Utils\Logger;
use FluxMedia\Core\Settings;

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
     * WordPress image renderer instance.
     *
     * @since 0.1.0
     * @var WordPressImageRenderer
     */
    private $image_renderer;

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
        $this->image_renderer = new WordPressImageRenderer( $image_converter, $video_converter );
        $this->quota_manager = new QuotaManager( $this->logger );
        $this->conversion_tracker = new ConversionTracker( $this->logger );
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
    private function register_hooks() {
        // Image upload hooks
        add_action( 'add_attachment', [ $this, 'handle_image_upload' ] );
        add_action( 'wp_generate_attachment_metadata', [ $this, 'handle_image_metadata_generation' ], 10, 2 );
        
        // Video upload hooks
        add_action( 'add_attachment', [ $this, 'handle_video_upload' ] );
        
        // Cleanup hooks
        add_action( 'delete_attachment', [ $this, 'handle_attachment_deletion' ] );
        
        // Image rendering hooks
        add_filter( 'wp_get_attachment_image_attributes', [ $this, 'handle_image_attributes_filter' ], 10, 3 );
        add_filter( 'wp_content_img_tag', [ $this, 'handle_content_images_filter' ], 10, 3 );
        add_filter( 'the_content', [ $this, 'handle_post_content_images_filter' ], 20 );
        add_filter( 'attachment_fields_to_edit', [ $this, 'handle_attachment_fields_filter' ], 10, 2 );
    }

    /**
     * Handle image upload.
     *
     * @since 0.1.0
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    public function handle_image_upload( $attachment_id ) {
        // Check if auto-conversion is enabled
        if ( ! Settings::is_image_auto_convert_enabled() ) {
            return;
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return;
        }

        // Check if it's an image
        if ( ! $this->image_converter->is_supported_image( $file_path ) ) {
            return;
        }

        $this->process_image_conversion( $attachment_id, $file_path );
    }

    /**
     * Handle video upload.
     *
     * @since 0.1.0
     * @param int $attachment_id Attachment ID.
     * @return void
     */
    public function handle_video_upload( $attachment_id ) {
        // Check if auto-conversion is enabled
        if ( ! Settings::is_video_auto_convert_enabled() ) {
            return;
        }

        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return;
        }

        // Check if it's a video
        if ( ! $this->video_converter->is_supported_video( $file_path ) ) {
            return;
        }

        $this->process_video_conversion( $attachment_id, $file_path );
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
     * @since 0.1.0
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    private function process_image_conversion( $attachment_id, $file_path ) {
        // Get upload directory info
        $upload_dir = wp_upload_dir();
        $file_info = pathinfo( $file_path );
        $file_dir = $file_info['dirname'];
        $file_name = $file_info['filename'];

        // Get settings from WordPress
        $settings = [
            'hybrid_approach' => Settings::is_hybrid_approach_enabled(),
            'webp_quality' => Settings::get_webp_quality(),
            'avif_quality' => Settings::get_avif_quality(),
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
            // Record quota usage
            $converted_count = count( $results['converted_formats'] );
            for ( $i = 0; $i < $converted_count; $i++ ) {
                $this->quota_manager->record_usage( 'image' );
            }

            // Record conversions in tracking table
            foreach ( $results['converted_formats'] as $format ) {
                $this->conversion_tracker->record_conversion( $attachment_id, $format );
            }

            // Update WordPress meta
            update_post_meta( $attachment_id, '_flux_media_converted_formats', $results['converted_formats'] );
            update_post_meta( $attachment_id, '_flux_media_conversion_date', current_time( 'mysql' ) );
            update_post_meta( $attachment_id, '_flux_media_converted_files', $results['converted_files'] );

            $this->logger->info( "Image conversion completed for attachment {$attachment_id}: " . implode( ', ', $results['converted_formats'] ) );
        } else {
            $this->logger->error( "Image conversion failed for attachment {$attachment_id}: " . implode( ', ', $results['errors'] ) );
        }
    }

    /**
     * Process video conversion.
     *
     * @since 0.1.0
     * @param int    $attachment_id Attachment ID.
     * @param string $file_path Source file path.
     * @return void
     */
    private function process_video_conversion( $attachment_id, $file_path ) {
        // Get upload directory info
        $upload_dir = wp_upload_dir();
        $file_info = pathinfo( $file_path );
        $file_dir = $file_info['dirname'];
        $file_name = $file_info['filename'];

        // Get settings from WordPress
        $settings = [
            'video_av1_crf' => Settings::get_av1_crf(),
            'video_webm_crf' => Settings::get_webm_crf(),
        ];

        // Create destination paths for requested formats
        $destination_paths = [];
        $video_formats = Settings::get_video_formats();
        
        foreach ( $video_formats as $format ) {
            $destination_paths[ $format ] = $file_dir . '/' . $file_name . '.' . $format;
        }

        // Process the video
        $results = $this->video_converter->process_video( $file_path, $destination_paths, $settings );

        // Handle results
        if ( $results['success'] ) {
            // Record quota usage
            $converted_count = count( $results['converted_formats'] );
            for ( $i = 0; $i < $converted_count; $i++ ) {
                $this->quota_manager->record_usage( 'video' );
            }

            // Record conversions in tracking table
            foreach ( $results['converted_formats'] as $format ) {
                $this->conversion_tracker->record_conversion( $attachment_id, $format );
            }

            // Update WordPress meta
            update_post_meta( $attachment_id, '_flux_media_converted_formats', $results['converted_formats'] );
            update_post_meta( $attachment_id, '_flux_media_conversion_date', current_time( 'mysql' ) );
            update_post_meta( $attachment_id, '_flux_media_converted_files', $results['converted_files'] );

            $this->logger->info( "Video conversion completed for attachment {$attachment_id}: " . implode( ', ', $results['converted_formats'] ) );
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
        $converted_files = get_post_meta( $attachment_id, '_flux_media_converted_files', true );
        
        if ( empty( $converted_files ) ) {
            return;
        }

        $deleted_count = 0;
        $total_count = count( $converted_files );

        foreach ( $converted_files as $format => $file_path ) {
            if ( file_exists( $file_path ) && unlink( $file_path ) ) {
                $deleted_count++;
                $this->logger->info( "Deleted converted file: {$file_path} (format: {$format})" );
            } else {
                $this->logger->warning( "Failed to delete converted file: {$file_path} (format: {$format})" );
            }
        }

        // Clear post meta data
        delete_post_meta( $attachment_id, '_flux_media_converted_files' );
        delete_post_meta( $attachment_id, '_flux_media_converted_formats' );
        delete_post_meta( $attachment_id, '_flux_media_conversion_date' );

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
        return get_post_meta( $attachment_id, '_flux_media_converted_files', true ) ?: [];
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

        $deleted_count = 0;
        $total_count = count( $converted_files );

        foreach ( $converted_files as $format => $file_path ) {
            if ( file_exists( $file_path ) && unlink( $file_path ) ) {
                $deleted_count++;
                $this->logger->info( "Deleted converted file: {$file_path} (format: {$format})" );
            } else {
                $this->logger->warning( "Failed to delete converted file: {$file_path} (format: {$format})" );
            }
        }

        // Clear post meta data
        delete_post_meta( $attachment_id, '_flux_media_converted_files' );
        delete_post_meta( $attachment_id, '_flux_media_converted_formats' );
        delete_post_meta( $attachment_id, '_flux_media_conversion_date' );

        $this->logger->info( "Deleted {$deleted_count}/{$total_count} converted files for attachment {$attachment_id}" );

        return $deleted_count === $total_count;
    }

    /**
     * Handle image attributes filter.
     *
     * @since 0.1.0
     * @param array    $attr Image attributes.
     * @param \WP_Post $attachment Attachment post object.
     * @param string   $size Image size.
     * @return array Modified attributes.
     */
    public function handle_image_attributes_filter( $attr, $attachment, $size ) {
        $converted_files = $this->get_converted_files( $attachment->ID );
        return $this->image_renderer->modify_image_attributes( $attr, $attachment, $size, $converted_files );
    }

    /**
     * Handle content images filter.
     *
     * @since 0.1.0
     * @param string $filtered_image The filtered image HTML.
     * @param string $context The context of the image.
     * @param int    $attachment_id The attachment ID.
     * @return string Modified image HTML.
     */
    public function handle_content_images_filter( $filtered_image, $context, $attachment_id ) {
        $converted_files = $this->get_converted_files( $attachment_id );
        return $this->image_renderer->modify_content_images( $filtered_image, $context, $attachment_id, $converted_files );
    }

    /**
     * Handle post content images filter.
     *
     * @since 0.1.0
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function handle_post_content_images_filter( $content ) {
        return $this->image_renderer->modify_post_content_images( $content );
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
     * @since 0.1.0
     * @param int $attachment_id WordPress attachment ID.
     * @return array Conversion results.
     */
    public function convert_attachment( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return [
                'success' => false,
                'errors' => ['Attachment file not found'],
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
            $this->process_video_conversion( $attachment_id, $file_path );
            return [
                'success' => true,
                'type' => 'video',
                'converted_files' => $this->get_converted_files( $attachment_id ),
            ];
        }

        return [
            'success' => false,
            'errors' => ['Unsupported file format'],
        ];
    }
}
