<?php
/**
 * WordPress image renderer for handling image display and optimization.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\AttachmentIdResolver;
use FluxMedia\App\Services\ExternalOptimizationProvider;
use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\Logger;

/**
 * WordPress image renderer for handling image display and optimization.
 *
 * @since 0.1.0
 */
class WordPressImageRenderer {

    /**
     * Video converter instance.
     *
     * Used for detecting video files in admin UI to show async processing notices.
     *
     * @since 0.1.0
     * @var VideoConverter
     */
    private $video_converter;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param VideoConverter $video_converter Video converter service.
     */
    public function __construct( VideoConverter $video_converter ) {
        $this->video_converter = $video_converter;
    }

    /**
     * Enqueue inline CSS for picture elements.
     * This ensures proper styling when picture elements are rendered.
     *
     * @since 0.1.0
     * @return void
     */
    private function enqueue_picture_css() {
        // Only enqueue once per request
        static $css_enqueued = false;
        if ( $css_enqueued ) {
            return;
        }
        
        $css = '
        .wp-block-image source {
            max-width: 100%;
            display: block;
            margin: 0 auto; /* Center if needed */
        }
        .wp-block-image source {
            max-width: 100%;
            height: auto; /* Preserve aspect ratio */
        }
        .aligncenter source {
            margin-left: auto;
            margin-right: auto;
        }
        .alignleft source {
            float: left;
            margin-right: 1em;
        }
        .alignright source {
            float: right;
            margin-left: 1em;
        }';
        
        // Try multiple approaches to ensure CSS is loaded
        $this->add_picture_css_inline( $css );
        $css_enqueued = true;
    }

    /**
     * Add picture CSS using the most reliable method available.
     *
     * @since 0.1.0
     * @param string $css The CSS to add.
     * @return void
     */
    private function add_picture_css_inline( $css ) {
        // Method 2: Try to add to common WordPress stylesheets
        $common_handles = [ 'wp-block-library', 'wp-includes', 'common' ];
        foreach ( $common_handles as $handle ) {
            if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'done' ) ) {
                wp_add_inline_style( $handle, $css );
                return;
            }
        }
        
        // Method 3: Create a custom handle and enqueue it
        wp_register_style( 'flux-media-optimizer-picture-styles', false, [], FLUX_MEDIA_OPTIMIZER_VERSION );
        wp_enqueue_style( 'flux-media-optimizer-picture-styles' );
        wp_add_inline_style( 'flux-media-optimizer-picture-styles', $css );
    }

    /**
     * Render optimized image with proper format selection.
     *
     * @since 0.1.0
     * @param string $image_url Original image URL.
     * @param array  $attributes Image attributes.
     * @return string Optimized image HTML.
     */
    public function render_optimized_image( $image_url, $attributes = [] ) {
        // Get the optimized version of the image
        $optimized_url = $this->get_optimized_image_url( $image_url );
        
        // Build attributes
        $attr_string = '';
        foreach ( $attributes as $key => $value ) {
            $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
        }
        
        return sprintf( '<img src="%s"%s>', esc_url( $optimized_url ), $attr_string );
    }

    /**
     * Get optimized image URL with format selection.
     *
     * @since 0.1.0
     * @param string $original_url Original image URL.
     * @return string Optimized image URL.
     */
    public function get_optimized_image_url( $original_url ) {
        // For now, return the original URL
        // This will be enhanced to check for converted versions
        return $original_url;
    }

    /**
     * Check if image has been converted.
     *
     * @since 0.1.0
     * @param int $attachment_id Attachment ID.
     * @return bool True if converted, false otherwise.
     */
    public function is_image_converted( $attachment_id ) {
        $converted_formats = AttachmentMetaHandler::get_converted_formats( $attachment_id );
        return ! empty( $converted_formats );
    }

    /**
     * Get image URL from attachment for specific format and size.
     *
     * @since 1.0.0
     * @param int    $attachment_id Attachment ID.
     * @param string $format Target format (webp, avif).
     * @param string $size   Optional. Image size (default: 'full').
     * @return string|null Image URL or null if not available.
     */
    public static function get_image_url_from_attachment( $attachment_id, $format, $size = 'full' ) {
        // Use AttachmentMetaHandler for centralized URL resolution.
        return AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size );
    }

    /**
     * Modify attachment URL for optimized image display.
     *
     * @since 1.0.0
     * @param string $url The original attachment URL.
     * @param int    $attachment_id The attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified URL (always returns original URL as fallback, never null or empty).
     */
    public function modify_attachment_url( $url, $attachment_id, $converted_files ) {
        if ( empty( $converted_files ) ) {
            return $url;
        }

        // For images: Use priority AVIF > WebP
        // Always check for null/empty returns and fallback to original URL
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $converted_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_AVIF );
            if ( ! empty( $converted_url ) ) {
                return $converted_url;
            }
        }
        
        if ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $converted_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_WEBP );
            if ( ! empty( $converted_url ) ) {
                return $converted_url;
            }
        }

        // Always return original URL as fallback (never null or empty)
        return $url;
    }

    /**
     * Modify content images for optimized display.
     *
     * @since 0.1.0
     * @param string $filtered_image The filtered image HTML.
     * @param string $context The context of the image.
     * @param int    $attachment_id The attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified image HTML.
     */
    public function modify_content_images( $filtered_image, $context, $attachment_id, $converted_files ) {
        if ( empty( $converted_files ) ) {
            return $filtered_image;
        }

        // Check if we have converted formats available
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) || isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            if ( Settings::is_image_hybrid_approach_enabled() ) {
                // Hybrid approach: Use picture element with sources and fallback
                $this->enqueue_picture_css();
                return $this->create_picture_element( $attachment_id, $converted_files, $filtered_image );
            } else {
                // Single format approach: Replace src with best available format
                return $this->replace_img_src( $filtered_image, $attachment_id, $converted_files );
            }
        }

        return $filtered_image;
    }

    /**
     * Modify block content for optimized image display.
     *
     * @since 1.0.0
     * @param string $block_content The block content.
     * @param array  $block The block data.
     * @return string Modified block content.
     */
    public function modify_block_content( $block_content, $block ) {
        $block_name = $block['blockName'] ?? '';
        
        // Process image blocks and featured image blocks
        if ( 'core/image' === $block_name ) {
            return $this->modify_image_block( $block_content, $block );
        } elseif ( 'core/post-featured-image' === $block_name ) {
            return $this->modify_featured_image_block( $block_content, $block );
        }
        
        return $block_content;
    }

    /**
     * Modify image block content for optimized display.
     *
     * @since 1.0.0
     * @param string $block_content The block content.
     * @param array  $block The block data.
     * @return string Modified block content.
     */
    private function modify_image_block( $block_content, $block ) {

        // Get block attributes
        $attributes = $block['attrs'] ?? [];
        
        // Get attachment ID - try 'id' attribute first, then fall back to URL lookup
        $attachment_id = null;
        
        if ( ! empty( $attributes['id'] ) ) {
            $attachment_id = (int) $attributes['id'];
        } elseif ( ! empty( $attributes['url'] ) ) {
            $image_url = $attributes['url'];
            $attachment_id = AttachmentIdResolver::from_url( $image_url );
        }
        
        if ( ! $attachment_id ) {
            return $block_content;
        }

        // Get converted files - prefer size from block attributes, fallback to full
        $size = $attributes['sizeSlug'] ?? 'full';
        $converted_files = AttachmentMetaHandler::get_converted_files_for_size( $attachment_id, $size );
        
        if ( empty( $converted_files ) ) {
            return $block_content;
        }

        // Check if we have converted formats available
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) || isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            if ( Settings::is_image_hybrid_approach_enabled() ) {
                // Hybrid approach: Use picture element with sources and fallback
                $this->enqueue_picture_css();
                return $this->create_block_picture_element( $attachment_id, $converted_files, $block_content, $attributes );
            } else {
                // Single format approach: Replace src with best available format
                return $this->replace_block_img_src( $block_content, $attachment_id, $converted_files );
            }
        }

        return $block_content;
    }

    /**
     * Modify featured image block content for optimized display.
     *
     * Reuses conversion logic from image blocks, differs only in attachment ID retrieval.
     *
     * @since 1.0.0
     * @param string $block_content The block content.
     * @param array  $block The block data.
     * @return string Modified block content.
     */
    private function modify_featured_image_block( $block_content, $block ) {
        // Get the post ID from context or block attributes
        $post_id = get_the_ID();
        if ( ! $post_id ) {
            // Try to get from block context
            $post_id = $block['attrs']['postId'] ?? null;
        }
        
        if ( ! $post_id ) {
            return $block_content;
        }
        
        // Get featured image attachment ID
        $attachment_id = get_post_thumbnail_id( $post_id );
        if ( ! $attachment_id ) {
            return $block_content;
        }
        
        // Get converted files - prefer post-thumbnail size, fallback to full
        $converted_files = AttachmentMetaHandler::get_converted_files_for_size( $attachment_id );

        if ( empty( $converted_files ) ) {
            return $block_content;
        }
        
        // Check if we have converted formats available
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) || isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            if ( Settings::is_image_hybrid_approach_enabled() ) {
                // Hybrid approach: Use picture element with sources and fallback
                $this->enqueue_picture_css();
                // Reuse existing method - pass empty attributes since featured images don't have block attributes
                return $this->create_block_picture_element( $attachment_id, $converted_files, $block_content, [] );
            } else {
                // Single format approach: Replace src with best available format
                // Reuse existing method
                return $this->replace_block_img_src( $block_content, $attachment_id, $converted_files );
            }
        }
        
        return $block_content;
    }

    /**
     * Modify post content images for optimized display.
     *
     * @since 0.1.0
     * @since 3.0.0 Updated to use size-specific structure from AttachmentMetaHandler.
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function modify_post_content_images( $content ) {
        // Find all img tags in content
        $pattern = '/<img([^>]*?)src=["\']([^"\']*?)["\']([^>]*?)>/i';
        
        return preg_replace_callback( $pattern, function( $matches ) {
            $full_match = $matches[0];
            $before_src = $matches[1];
            $src_url = $matches[2];
            $after_src = $matches[3];
            
            // Get attachment ID from URL
            $attachment_id = AttachmentIdResolver::from_url( $src_url );
            if ( ! $attachment_id ) {
                return $full_match;
            }
            
            // Get converted files from size-specific structure
            $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
            $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
                ? $converted_files_by_size['full'] 
                : [];
            
            if ( empty( $converted_files ) ) {
                return $full_match;
            }
            
            // Check if we have converted formats available
            if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) || isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
                if ( Settings::is_image_hybrid_approach_enabled() ) {
                    // Hybrid approach: Use picture element with sources and fallback
                    $this->enqueue_picture_css();
                    return $this->create_picture_element( $attachment_id, $converted_files, $full_match );
                } else {
                    // Single format approach: Replace src with best available format
                    return $this->replace_img_src( $full_match, $attachment_id, $converted_files );
                }
            }
            
            return $full_match;
        }, $content );
    }

    /**
     * Modify attachment fields for admin display.
     *
     * @since 0.1.0
     * @since 3.0.0 Updated to use size-specific structure from AttachmentMetaHandler and simplified external status display.
     * @param array   $form_fields Attachment form fields.
     * @param \WP_Post $post The attachment post object.
     * @return array Modified form fields.
     */
    public function modify_attachment_fields( $form_fields, $post ) {
        // Wrap entire method in try-catch to prevent fatal errors during AJAX queries.
        try {
            // Validate post object.
            if ( ! $post || ! isset( $post->ID ) ) {
                return $form_fields;
            }
            
            // Get converted files from size-specific structure
            $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $post->ID );
            $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
                ? $converted_files_by_size['full'] 
                : [];
            $conversion_disabled = AttachmentMetaHandler::is_conversion_disabled( $post->ID );
            
            // Combine all sections under one "Flux Media Optimizer" label
            $html_content = '';
            
        // Check for external service processing status
        if ( Settings::is_external_service_enabled() ) {
            try {
                $external_provider = new ExternalOptimizationProvider( new Logger() );
                $status = $external_provider->get_job_status( $post->ID );
                
                if ( $status ) {
                    $html_content .= $this->get_external_processing_status_html( $post->ID, $status );
                }
            } catch ( \Exception $e ) {
                // Silently fail if external provider can't be initialized (e.g., table doesn't exist yet)
                // This prevents fatal errors on attachment screen
            }
        }
            
            // Add conversion status if files exist
            // Use size-specific structure if available, otherwise use legacy
            // Note: $converted_files_by_size was already retrieved above, reuse it
            if ( ! empty( $converted_files_by_size ) ) {
                $html_content .= $this->get_conversion_status_html( $post->ID, $converted_files_by_size );
            } elseif ( ! empty( $converted_files ) ) {
                $html_content .= $this->get_conversion_status_html( $post->ID, $converted_files );
            }
            
            // Always add conversion actions
            $html_content .= $this->get_conversion_actions_html( $post->ID, $conversion_disabled );
            
            // Single Flux Media Optimizer section with all content
            $form_fields['flux_media_optimizer'] = [
                'label' => __( 'Flux Media Optimizer', 'flux-media-optimizer' ),
                'input' => 'html',
                'html' => $html_content,
            ];
        } catch ( \Exception $e ) {
            // Log error but don't break the attachment query
            if ( function_exists( 'error_log' ) ) {
                error_log( 'Flux Media Optimizer: Error in modify_attachment_fields: ' . $e->getMessage() );
            }
            // Return form_fields unchanged if there's an error
        } catch ( \Error $e ) {
            // Catch fatal errors (PHP 7+)
            if ( function_exists( 'error_log' ) ) {
                error_log( 'Flux Media Optimizer: Fatal error in modify_attachment_fields: ' . $e->getMessage() );
            }
            // Return form_fields unchanged if there's an error
        }
        
        return $form_fields;
    }

    /**
     * Create picture element for block editor images.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use size-specific structure for srcset generation.
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Array of converted file paths (full size only).
     * @param string $block_content Original block content.
     * @param array  $attributes Block attributes.
     * @return string Picture element HTML.
     */
    private function create_block_picture_element( $attachment_id, $converted_files, $block_content, $attributes ) {
        // Get the size from block attributes, default to 'full'
        $size = $attributes['sizeSlug'] ?? 'full';
        
        // Extract width and height from original block content
        $dimensions = $this->extract_width_height_from_img( $block_content );
        
        // Get image attributes for the fallback img tag
        $img_attributes = [
            'alt' => $attributes['alt'] ?? '',
            'class' => $attributes['className'] ?? '',
            'loading' => $attributes['loading'] ?? 'lazy',
        ];
        
        // Preserve width and height if they exist in the original
        if ( ! empty( $dimensions['width'] ) ) {
            $img_attributes['width'] = $dimensions['width'];
        }
        if ( ! empty( $dimensions['height'] ) ) {
            $img_attributes['height'] = $dimensions['height'];
        }
        
        // Extract wrapper attributes from the existing block content
        $wrapper_attributes = $this->extract_wrapper_attributes_from_block_content( $block_content );
        
        // Get converted files by size for srcset generation
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        
        // Determine preferred format (AVIF > WebP)
        $preferred_format = null;
        $fallback_format = null;
        
        if ( ! empty( $converted_files_by_size ) ) {
            foreach ( $converted_files_by_size as $size_formats ) {
                if ( isset( $size_formats[ Converter::FORMAT_AVIF ] ) ) {
                    $preferred_format = Converter::FORMAT_AVIF;
                    $fallback_format = Converter::FORMAT_WEBP;
                    break;
                } elseif ( isset( $size_formats[ Converter::FORMAT_WEBP ] ) ) {
                    $preferred_format = Converter::FORMAT_WEBP;
                    break;
                }
            }
        } elseif ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $preferred_format = Converter::FORMAT_AVIF;
            $fallback_format = Converter::FORMAT_WEBP;
        } elseif ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $preferred_format = Converter::FORMAT_WEBP;
        }
        
        // Build picture element with proper wrapper attributes
        $picture_html = '<picture' . ( $wrapper_attributes ? ' ' . $wrapper_attributes : '' ) . '>';
        
        // Add AVIF source with srcset if available
        if ( $preferred_format === Converter::FORMAT_AVIF || ( $preferred_format === Converter::FORMAT_WEBP && isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) ) {
            $avif_srcset = $this->build_srcset_for_format( $attachment_id, Converter::FORMAT_AVIF, $converted_files_by_size );
            if ( $avif_srcset ) {
                $picture_html .= '<source srcset="' . esc_attr( $avif_srcset ) . '" type="image/avif">';
            }
        }
        
        // Add WebP source with srcset if available
        if ( $preferred_format === Converter::FORMAT_WEBP || isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $webp_srcset = $this->build_srcset_for_format( $attachment_id, Converter::FORMAT_WEBP, $converted_files_by_size );
            if ( $webp_srcset ) {
                $picture_html .= '<source srcset="' . esc_attr( $webp_srcset ) . '" type="image/webp">';
            }
        }
        
        // Add fallback img element using WordPress function (will use converted formats via srcset filter)
        $picture_html .= wp_get_attachment_image( $attachment_id, $size, false, $img_attributes );
        $picture_html .= '</picture>';
        
        return $picture_html;
    }

    /**
     * Build srcset string for a specific format across all sizes.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use AttachmentMetaHandler for URL retrieval.
     * @param int    $attachment_id         Attachment ID.
     * @param string $format                Format (avif or webp).
     * @param array  $converted_files_by_size Converted files organized by size.
     * @return string Srcset string or empty if no sizes available.
     */
    private function build_srcset_for_format( $attachment_id, $format, $converted_files_by_size ) {
        if ( empty( $converted_files_by_size ) ) {
            return '';
        }

        $metadata = wp_get_attachment_metadata( $attachment_id );
        if ( empty( $metadata ) ) {
            return '';
        }

        $srcset_parts = [];

        // Add full size
        if ( isset( $converted_files_by_size['full'][ $format ] ) ) {
            $full_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, 'full' );
            if ( $full_url && isset( $metadata['width'] ) ) {
                $srcset_parts[] = esc_url( $full_url ) . ' ' . (int) $metadata['width'] . 'w';
            }
        }

        // Add all intermediate sizes
        if ( ! empty( $metadata['sizes'] ) ) {
            foreach ( $metadata['sizes'] as $size_name => $size_data ) {
                if ( isset( $converted_files_by_size[ $size_name ][ $format ] ) && isset( $size_data['width'] ) ) {
                    $size_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size_name );
                    if ( $size_url ) {
                        $srcset_parts[] = esc_url( $size_url ) . ' ' . (int) $size_data['width'] . 'w';
                    }
                }
            }
        }

        return ! empty( $srcset_parts ) ? implode( ', ', $srcset_parts ) : '';
    }

    /**
     * Create picture element for hybrid approach.
     *
     * @since 0.1.0
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @param string $original_html Original image HTML.
     * @return string Picture element HTML.
     */
    private function create_picture_element( $attachment_id, $converted_files, $original_html ) {
        $original_url = wp_get_attachment_url( $attachment_id );
        if ( ! $original_url ) {
            // If we can't get the URL, return original HTML
            return $original_html;
        }
        
        // Extract attributes from original HTML
        preg_match( '/<img([^>]*?)>/i', $original_html, $matches );
        $attributes = $matches[1] ?? '';
        
        // Replace src in attributes with original URL
        $attributes = preg_replace( '/src=["\'][^"\']*["\']/', 'src="' . esc_url( $original_url ) . '"', $attributes );
        
        // Build picture element with available sources
        $picture_html = '<picture>';
        
        // Add AVIF source if available
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $avif_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_AVIF );
            $picture_html .= '<source srcset="' . esc_attr( $avif_url ) . '" type="image/avif">';
        }
        
        // Add WebP source if available
        if ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $webp_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_WEBP );
            $picture_html .= '<source srcset="' . esc_attr( $webp_url ) . '" type="image/webp">';
        }
        
        // Add fallback img element
        $picture_html .= '<img' . $attributes . '>';
        $picture_html .= '</picture>';
        
        return $picture_html;
    }

    /**
     * Get image URL from file path.
     *
     * Centralized method for converting file paths to URLs. Handles absolute, relative, and URL formats.
     *
     * @since 1.0.0
     * @param string $file_path      The file path to convert. Can be absolute or relative.
     * @param bool   $validate_exists Whether to validate that the file exists. Default true.
     * @return string|null The generated URL or null if conversion fails.
     */
    public static function get_image_url_from_file_path( $file_path, $validate_exists = true ) {
        // Validate input
        if ( empty( $file_path ) || ! is_string( $file_path ) ) {
            return null;
        }
        
        // Get WordPress upload directory information
        $upload_dir = wp_upload_dir();
        
        // Handle different path formats
        $relative_path = self::normalize_file_path( $file_path, $upload_dir );
        
        if ( $relative_path === null ) {
            return null;
        }
        
        // Validate file exists if requested
        if ( $validate_exists ) {
            $full_path = $upload_dir['basedir'] . '/' . $relative_path;
            if ( ! file_exists( $full_path ) ) {
                return null;
            }
        }
        
        // Generate and return URL
        return $upload_dir['baseurl'] . '/' . $relative_path;
    }

    /**
     * Normalize file path to relative path from uploads directory.
     *
     * @since 1.0.0
     * @param string $file_path The file path to normalize.
     * @param array  $upload_dir WordPress upload directory array.
     * @return string|null Normalized relative path or null if invalid.
     */
    private static function normalize_file_path( $file_path, $upload_dir ) {
        // Handle absolute paths
        if ( strpos( $file_path, $upload_dir['basedir'] ) === 0 ) {
            // Remove the upload directory base path
            $relative_path = str_replace( $upload_dir['basedir'] . '/', '', $file_path );
            return $relative_path;
        }
        
        // Handle relative paths that start with uploads directory name
        $uploads_dir_name = basename( $upload_dir['basedir'] );
        if ( strpos( $file_path, $uploads_dir_name . '/' ) === 0 ) {
            // Remove the uploads directory name prefix
            return str_replace( $uploads_dir_name . '/', '', $file_path );
        }
        
        // Handle paths that are already relative to uploads directory
        if ( strpos( $file_path, '/' ) !== 0 && ! strpos( $file_path, '://' ) ) {
            // Path doesn't start with / and doesn't contain protocol, assume it's relative
            return $file_path;
        }
        
        // Handle full URLs (extract path component)
        if ( strpos( $file_path, '://' ) !== false ) {
            $parsed_url = wp_parse_url( $file_path );
            if ( isset( $parsed_url['path'] ) ) {
                $path = $parsed_url['path'];
                // Remove leading slash and check if it's in uploads directory
                $path = ltrim( $path, '/' );
                if ( strpos( $path, $uploads_dir_name . '/' ) === 0 ) {
                    return str_replace( $uploads_dir_name . '/', '', $path );
                }
            }
        }
        
        // If we can't normalize the path, return null
        return null;
    }

    /**
     * Extract wrapper attributes from existing block content.
     *
     * @since 0.1.0
     * @param string $block_content The existing block content HTML.
     * @return string Extracted wrapper attributes string.
     */
    private function extract_wrapper_attributes_from_block_content( $block_content ) {
        // Look for figure wrapper (most common for image blocks)
        if ( preg_match( '/<figure([^>]*?)>/i', $block_content, $matches ) ) {
            return trim( $matches[1] );
        }
        
        // Look for div wrapper (alternative wrapper)
        if ( preg_match( '/<div([^>]*?)>/i', $block_content, $matches ) ) {
            return trim( $matches[1] );
        }
        
        // Look for any wrapper element that contains an img tag
        if ( preg_match( '/<([a-zA-Z][a-zA-Z0-9]*)([^>]*?)>\s*<img/i', $block_content, $matches ) ) {
            return trim( $matches[2] );
        }
        
        return '';
    }

    /**
     * Extract width and height attributes from image HTML.
     *
     * @since 1.0.0
     * @param string $img_html The image HTML to extract attributes from.
     * @return array Array with 'width' and 'height' keys, or empty strings if not found.
     */
    private function extract_width_height_from_img( $img_html ) {
        $result = [
            'width' => '',
            'height' => '',
        ];
        
        // Extract width attribute
        if ( preg_match( '/width=["\']?(\d+)["\']?/i', $img_html, $matches ) ) {
            $result['width'] = (int) $matches[1];
        }
        
        // Extract height attribute
        if ( preg_match( '/height=["\']?(\d+)["\']?/i', $img_html, $matches ) ) {
            $result['height'] = (int) $matches[1];
        }
        
        return $result;
    }

    /**
     * Get conversion status HTML for admin display.
     *
     * @since 2.0.1
     * @since 3.0.0 Updated to use size-specific structure from AttachmentMetaHandler and support CDN URLs.
     * @param int   $attachment_id Attachment ID.
     * @param array $converted_files Array of converted file paths (can be size-specific structure or legacy format).
     * @return string HTML for conversion status.
     */
    private function get_conversion_status_html( $attachment_id, $converted_files ) {
        try {
            // Initialize WordPress filesystem for file operations
            if ( ! function_exists( 'WP_Filesystem' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            WP_Filesystem();
            
            global $wp_filesystem;
            
            // Get the original file path from _wp_attached_file meta (bypasses any filters)
            // This ensures we get the actual original file, not a converted one
            $original_file_meta = get_post_meta( $attachment_id, '_wp_attached_file', true );
            if ( ! $original_file_meta ) {
                return '';
            }
            
            // Build the full file path
            $upload_dir = wp_upload_dir();
            $original_file = $upload_dir['basedir'] . '/' . $original_file_meta;
            
            // Get file size
            $original_size = $wp_filesystem && $wp_filesystem->exists( $original_file ) ? $wp_filesystem->size( $original_file ) : ( file_exists( $original_file ) ? filesize( $original_file ) : 0 );
            
            // Build the original URL directly from the meta (bypasses wp_get_attachment_url filter)
            $original_url = $upload_dir['baseurl'] . '/' . $original_file_meta;
        
        $html = '<div class="flux-media-optimizer-conversion-status" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        
        // Original file info
        $html .= '<div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">';
        $html .= '<h4 style="margin: 0 0 8px 0; color: #333; font-size: 14px;">' . __( 'Original File', 'flux-media-optimizer' ) . '</h4>';
        $html .= '<div style="font-size: 12px; color: #666;">';
        $html .= '<strong>' . __( 'Size:', 'flux-media-optimizer' ) . '</strong> ' . size_format( $original_size ) . '<br>';
        $html .= '<strong>' . __( 'URL:', 'flux-media-optimizer' ) . '</strong> <a href="' . esc_url( $original_url ) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . esc_html( $original_url ) . '</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Check if this is size-specific structure (nested) or legacy format (flat)
        $is_size_specific = ! empty( $converted_files ) && isset( $converted_files['full'] ) && is_array( $converted_files['full'] );
        
        // Converted files
        $html .= '<h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px;">' . __( 'Converted Files', 'flux-media-optimizer' ) . '</h4>';
        
        if ( empty( $converted_files ) ) {
            $html .= '<p style="color: #666; font-style: italic; margin: 0;">' . esc_html( __( 'No conversions available', 'flux-media-optimizer' ) ) . '</p>';
        } elseif ( $is_size_specific ) {
            // Display size-specific structure
            // Get valid WordPress size names (includes 'thumbnail', 'medium', 'large', and custom sizes)
            $valid_sizes = get_intermediate_image_sizes();
            // Add 'full' to the list of valid sizes
            $valid_sizes[] = 'full';
            
            foreach ( $converted_files as $size_name => $size_formats ) {
                if ( ! is_array( $size_formats ) ) {
                    continue;
                }
                
                // Only display sizes that are valid WordPress registered sizes
                if ( ! in_array( $size_name, $valid_sizes, true ) ) {
                    continue;
                }
                
                // Get size dimensions from metadata
                $metadata = wp_get_attachment_metadata( $attachment_id );
                $width = 0;
                $height = 0;
                
                if ( ! empty( $metadata ) && is_array( $metadata ) ) {
                    if ( 'full' === $size_name ) {
                        $width = $metadata['width'] ?? 0;
                        $height = $metadata['height'] ?? 0;
                    } elseif ( isset( $metadata['sizes'][ $size_name ] ) ) {
                        $width = $metadata['sizes'][ $size_name ]['width'] ?? 0;
                        $height = $metadata['sizes'][ $size_name ]['height'] ?? 0;
                    }
                }
                
                $size_label = 'full' === $size_name ? __( 'Full Size', 'flux-media-optimizer' ) : ucfirst( $size_name );
                $html .= '<div style="margin-bottom: 15px;">';
                $html .= '<h5 style="margin: 0 0 8px 0; color: #555; font-size: 13px; font-weight: bold;">' . esc_html( $size_label );
                if ( $width && $height ) {
                    $html .= ' (' . esc_html( $width ) . '×' . esc_html( $height ) . ')';
                }
                $html .= '</h5>';
                
                foreach ( $size_formats as $format => $data ) {
                    // Skip original file in the list of converted files
                    if ( $format === 'original' ) {
                        continue;
                    }

                    // Extract URL/path from unified structure.
                    if ( ! is_array( $data ) || ! isset( $data['url'] ) ) {
                        continue;
                    }
                    
                    $url_or_path = $data['url'];
                    
                    // Check if it's a URL (CDN) or file path (local).
                    $is_url = is_string( $url_or_path ) && ( strpos( $url_or_path, 'http://' ) === 0 || strpos( $url_or_path, 'https://' ) === 0 );
                    
                    // Get file size using AttachmentMetaHandler (handles both URLs and file paths).
                    $file_size = AttachmentMetaHandler::get_file_size( $attachment_id, $format, $size_name ) ?? 0;
                    
                    // Get original size for this specific size from meta first, then fallback to file system
                    $size_original = AttachmentMetaHandler::get_converted_file_size( $attachment_id, 'original', $size_name );
                    
                    if ( $size_original === null ) {
                        // Fallback to file system calculation
                        if ( 'full' === $size_name ) {
                            $size_original = $original_size;
                        } else {
                            // Get the original file path for this specific size
                            $size_file = get_attached_file( $attachment_id );
                            if ( ! $size_file ) {
                                $size_original = 0;
                            } else {
                                $file_dir = dirname( $size_file );
                                $size_file_name = $metadata['sizes'][ $size_name ]['file'] ?? '';
                                
                                if ( ! empty( $size_file_name ) ) {
                                    $size_file_path = $file_dir . '/' . $size_file_name;
                                    // Get original file size from file system.
                                    $size_original = $wp_filesystem && $wp_filesystem->exists( $size_file_path ) ? $wp_filesystem->size( $size_file_path ) : ( file_exists( $size_file_path ) ? filesize( $size_file_path ) : 0 );
                                } else {
                                    $size_original = 0;
                                }
                            }
                        }
                    }
                    
                    // Calculate savings percentage (compare converted file against original file of same size)
                    // Only calculate if we have both sizes (not available for CDN URLs).
                    $savings = ( $size_original > 0 && $file_size > 0 ) ? ( ( $size_original - $file_size ) / $size_original ) * 100 : 0;
                    
                    // Get converted file URL from AttachmentMetaHandler
                    $converted_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size_name );
                    
                    // Format-specific styling
                    $format_color = $format === Converter::FORMAT_WEBP ? '#4285f4' : ( $format === Converter::FORMAT_AVIF ? '#ea4335' : '#34a853' );
                    
                    $html .= '<div style="background: white; border: 1px solid #e1e1e1; border-radius: 3px; padding: 12px; margin-bottom: 8px; margin-left: 15px;">';
                    $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
                    $html .= '<span style="font-weight: bold; color: ' . $format_color . '; text-transform: uppercase; font-size: 12px;">' . esc_html( $format ) . '</span>';
                    if ( $savings > 0 ) {
                        $html .= '<span style="background: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">' . round( $savings, 1 ) . '% ' . __( 'smaller', 'flux-media-optimizer' ) . '</span>';
                    } elseif ( $size_original > 0 && $file_size > 0 ) {
                        // File is larger or equal
                        $increase = abs( $savings );
                        $tooltip = __( 'File is larger than original. This usually happens when the original is already highly compressed or when using high quality settings.', 'flux-media-optimizer' );
                        $html .= '<span title="' . esc_attr( $tooltip ) . '" style="background: #ffebee; color: #c62828; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; cursor: help; border-bottom: 1px dotted #c62828;">' . round( $increase, 1 ) . '% ' . __( 'larger', 'flux-media-optimizer' ) . '</span>';
                    }
                    $html .= '</div>';
                    
                    $html .= '<div style="font-size: 12px; color: #666;">';
                    if ( $file_size > 0 ) {
                        $html .= '<strong>' . __( 'Size:', 'flux-media-optimizer' ) . '</strong> ' . size_format( $file_size ) . '<br>';
                    }
                    if ( $converted_url ) {
                        // Check if URL is from CDN by checking if it's a URL (starts with http/https).
                        $is_cdn = AttachmentMetaHandler::is_file_url( $converted_url );
                        $url_label = $is_cdn ? __( 'CDN URL:', 'flux-media-optimizer' ) : __( 'URL:', 'flux-media-optimizer' );
                        $html .= '<strong>' . esc_html( $url_label ) . '</strong> <a href="' . esc_url( $converted_url ) . '" target="_blank" style="color: #0073aa; text-decoration: none; word-break: break-all;">' . esc_html( $converted_url ) . '</a>';
                    }
                    $html .= '</div>';
                    $html .= '</div>';
                }
                
                $html .= '</div>';
            }
        } else {
            // Handle flat structure (should not exist, but handle gracefully)
            foreach ( $converted_files as $format => $data ) {
                // Extract URL from unified structure.
                if ( ! is_array( $data ) || ! isset( $data['url'] ) ) {
                    continue;
                }
                
                $url_or_path = $data['url'];
                
                // Check if it's a URL (CDN) or file path (local).
                $is_url = ( strpos( $url_or_path, 'http://' ) === 0 || strpos( $url_or_path, 'https://' ) === 0 );
                
                // Get file size using AttachmentMetaHandler (handles both URLs and file paths).
                $file_size = AttachmentMetaHandler::get_file_size( $attachment_id, $format, 'full' ) ?? 0;
                
                $savings = ( $original_size > 0 && $file_size > 0 ) ? ( ( $original_size - $file_size ) / $original_size ) * 100 : 0;
                
                // Get converted file URL from AttachmentMetaHandler
                $converted_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, 'full' );
                
                // Format-specific styling
                $format_color = $format === Converter::FORMAT_WEBP ? '#4285f4' : ( $format === Converter::FORMAT_AVIF ? '#ea4335' : '#34a853' );
                
                $html .= '<div style="background: white; border: 1px solid #e1e1e1; border-radius: 3px; padding: 12px; margin-bottom: 8px;">';
                $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
                $html .= '<span style="font-weight: bold; color: ' . $format_color . '; text-transform: uppercase; font-size: 12px;">' . esc_html( $format ) . '</span>';
                if ( $savings > 0 ) {
                    $html .= '<span style="background: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">' . round( $savings, 1 ) . '% ' . __( 'smaller', 'flux-media-optimizer' ) . '</span>';
                } elseif ( $original_size > 0 && $file_size > 0 ) {
                    // File is larger or equal
                    $increase = abs( $savings );
                    $tooltip = __( 'File is larger than original. This usually happens when the original is already highly compressed or when using high quality settings.', 'flux-media-optimizer' );
                    $html .= '<span title="' . esc_attr( $tooltip ) . '" style="background: #ffebee; color: #c62828; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; cursor: help; border-bottom: 1px dotted #c62828;">' . round( $increase, 1 ) . '% ' . __( 'larger', 'flux-media-optimizer' ) . '</span>';
                }
                $html .= '</div>';
                
                $html .= '<div style="font-size: 12px; color: #666;">';
                if ( $file_size > 0 ) {
                    $html .= '<strong>' . __( 'Size:', 'flux-media-optimizer' ) . '</strong> ' . size_format( $file_size ) . '<br>';
                }
                if ( $converted_url ) {
                    // Check if URL is from CDN by checking if it's a URL (starts with http/https).
                    $is_cdn = AttachmentMetaHandler::is_file_url( $converted_url );
                    $url_label = $is_cdn ? __( 'CDN URL:', 'flux-media-optimizer' ) : __( 'URL:', 'flux-media-optimizer' );
                    $html .= '<strong>' . esc_html( $url_label ) . '</strong> <a href="' . esc_url( $converted_url ) . '" target="_blank" style="color: #0073aa; text-decoration: none; word-break: break-all;">' . esc_html( $converted_url ) . '</a>';
                }
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        return $html;
        } catch ( \Exception $e ) {
            // Return empty string on error to prevent breaking attachment query
            if ( function_exists( 'error_log' ) ) {
                error_log( 'Flux Media Optimizer: Error in get_conversion_status_html: ' . $e->getMessage() );
            }
            return '';
        } catch ( \Error $e ) {
            // Catch fatal errors
            if ( function_exists( 'error_log' ) ) {
                error_log( 'Flux Media Optimizer: Fatal error in get_conversion_status_html: ' . $e->getMessage() );
            }
            return '';
        }
    }

    /**
     * Get external processing status HTML.
     *
     * @since 3.0.0
     * @param int    $attachment_id Attachment ID.
     * @param string $status        Job status string ('queued', 'processing', 'completed', 'failed').
     * @return string HTML content.
     */
    private function get_external_processing_status_html( $attachment_id, $status ) {
        $html = '<div class="flux-media-optimizer-external-status" style="background: #fff3cd; border: 1px solid #ffb900; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        $html .= '<h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px;">' . __( 'External Processing Status', 'flux-media-optimizer' ) . '</h4>';
        
        $status = $status ?? 'unknown';
        $status_colors = [
            'queued' => '#0073aa',
            'processing' => '#0073aa',
            'completed' => '#00a32a',
            'failed' => '#d63638',
        ];
        $status_color = $status_colors[ $status ] ?? '#666';
        
        $html .= '<div style="font-size: 12px; color: #666; margin-bottom: 10px;">';
        $html .= '<strong>' . __( 'Status:', 'flux-media-optimizer' ) . '</strong> ';
        $html .= '<span style="color: ' . esc_attr( $status_color ) . '; font-weight: bold;">' . esc_html( ucfirst( $status ) ) . '</span>';
        
        if ( $status === 'queued' || $status === 'processing' ) {
            $html .= '<br><em style="color: #856404;">' . __( 'Processing in progress. Please check back in a few minutes.', 'flux-media-optimizer' ) . '</em>';
        }
        
        if ( $status === 'failed' ) {
            $html .= '<br><em style="color: #d63638;">' . __( 'Processing failed. You may retry the conversion.', 'flux-media-optimizer' ) . '</em>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Get conversion actions HTML.
     *
     * @since 1.0.0
     * @since 3.0.0 Updated to use size-specific structure from AttachmentMetaHandler.
     * @param int  $attachment_id Attachment ID.
     * @param bool $conversion_disabled Whether conversion is disabled.
     * @return string HTML for conversion actions.
     */
    private function get_conversion_actions_html( $attachment_id, $conversion_disabled ) {
        try {
            $html = '<div class="flux-media-optimizer-conversion-actions" style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 12px; margin: 10px 0;">';
            $html .= '<h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px;">' . __( 'Conversion Actions', 'flux-media-optimizer' ) . '</h4>';
            
            // Check if this is a video attachment and if there are no converted files
            $file_path = get_attached_file( $attachment_id );
            if ( ! $file_path ) {
                return '';
            }
            
            $is_video = $file_path && $this->video_converter->is_supported_video( $file_path );
        $converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
        $converted_files = ! empty( $converted_files_by_size ) && isset( $converted_files_by_size['full'] ) 
            ? $converted_files_by_size['full'] 
            : [];
        $no_converted_files = empty( $converted_files );
        
        // Show async notice for videos with no converted files
        if ( $is_video && $no_converted_files && ! $conversion_disabled ) {
            $html .= '<p style="margin: 0 0 10px 0; padding: 8px; background: #fff3cd; border-left: 4px solid #ffb900; color: #856404; font-size: 13px; line-height: 1.5;">';
            $html .= '<strong>' . __( 'Note:', 'flux-media-optimizer' ) . '</strong> ';
            $html .= __( 'Video conversions are processed asynchronously and may not appear immediately. Please check back in a few minutes.', 'flux-media-optimizer' );
            $html .= '</p>';
        }
        
        if ( $conversion_disabled ) {
            $html .= sprintf(
                '<button type="button" class="button button-primary" onclick="fluxMediaEnableConversion(%d)" style="background: #00a32a; border-color: #00a32a; color: white; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                    %s
                </button>',
                $attachment_id,
                __( 'Enable Conversion', 'flux-media-optimizer' )
            );
        } else {
            $html .= '<div style="display: flex; gap: 8px; flex-wrap: wrap;">';
            
            // Check if there are converted files to determine button text
            $button_text = ! empty( $converted_files ) ? __( 'Re-convert', 'flux-media-optimizer' ) : __( 'Convert', 'flux-media-optimizer' );
            
            $html .= sprintf(
                '<button type="button" class="button button-primary" onclick="fluxMediaConvertAttachment(%d)" style="background: #0073aa; border-color: #0073aa; color: white; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                    %s
                </button>',
                esc_attr( $attachment_id ),
                esc_html( $button_text )
            );
            
            $html .= sprintf(
                '<button type="button" class="button button-secondary" onclick="fluxMediaDisableConversion(%d)" style="background: #f0f0f1; border-color: #c3c4c7; color: #2c3338; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                    %s
                </button>',
                esc_attr( $attachment_id ),
                esc_html( __( 'Disable Conversion', 'flux-media-optimizer' ) )
            );
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
        } catch ( \Exception $e ) {
            // Return empty string on error to prevent breaking attachment query
            if ( function_exists( 'error_log' ) ) {
                error_log( 'Flux Media Optimizer: Error in get_conversion_actions_html: ' . $e->getMessage() );
            }
            return '';
        } catch ( \Error $e ) {
            // Catch fatal errors
            if ( function_exists( 'error_log' ) ) {
                error_log( 'Flux Media Optimizer: Fatal error in get_conversion_actions_html: ' . $e->getMessage() );
            }
            return '';
        }
    }

    /**
     * Replace img src attribute with optimized format (single format approach).
     *
     * @since 0.1.0
     * @param string $img_html Original img HTML.
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified img HTML.
     */
    private function replace_img_src( $img_html, $attachment_id, $converted_files ) {
        // Priority: AVIF > WebP
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $new_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_AVIF );
        } elseif ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $new_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_WEBP );
        } else {
            return $img_html;
        }
        
        // Replace src attribute
        return preg_replace(
            '/src=["\']([^"\']*?)["\']/',
            'src="' . esc_url( $new_url ) . '"',
            $img_html
        );
    }

    /**
     * Replace image src in block content with converted format URL.
     *
     * @since 1.0.0
     * @param string $block_content The block content.
     * @param int    $attachment_id Attachment ID (unused, kept for consistency).
     * @param array  $converted_files Array of format => file_path mappings.
     * @return string Modified block content.
     */
    private function replace_block_img_src( $block_content, $attachment_id, $converted_files ) {
        // Priority: AVIF > WebP
        $format = null;
        $file_path = null;
        
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $format = Converter::FORMAT_AVIF;
            $file_path = $converted_files[ Converter::FORMAT_AVIF ];
        } elseif ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $format = Converter::FORMAT_WEBP;
            $file_path = $converted_files[ Converter::FORMAT_WEBP ];
        } else {
            return $block_content;
        }
        
        // Get URL using AttachmentMetaHandler
        $new_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, 'full' );
        if ( ! $new_url ) {
            return $block_content;
        }
        
        // Replace src attribute in block content
        return preg_replace(
            '/src=["\']([^"\']*?)["\']/',
            'src="' . esc_url( $new_url ) . '"',
            $block_content
        );
    }

}
