<?php
/**
 * WordPress image renderer for handling image display and optimization.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\Converter;

/**
 * WordPress image renderer for handling image display and optimization.
 *
 * @since 0.1.0
 */
class WordPressImageRenderer {

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
     * Constructor.
     *
     * @since 0.1.0
     * @param ImageConverter $image_converter Image converter service.
     * @param VideoConverter $video_converter Video converter service.
     */
    public function __construct( ImageConverter $image_converter, VideoConverter $video_converter ) {
        $this->image_converter = $image_converter;
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
        $common_handles = [ 'wp-block-library', 'wp-includes', 'common', 'admin-bar' ];
        foreach ( $common_handles as $handle ) {
            if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'done' ) ) {
                wp_add_inline_style( $handle, $css );
                return;
            }
        }
        
        // Method 3: Create a custom handle and enqueue it
        wp_register_style( 'flux-media-picture-styles', false );
        wp_enqueue_style( 'flux-media-picture-styles' );
        wp_add_inline_style( 'flux-media-picture-styles', $css );
        
        // Method 4: Fallback - add directly to head if we're in the right context
        if ( ! is_admin() && ! did_action( 'wp_head' ) ) {
            add_action( 'wp_head', function() use ( $css ) {
                echo '<style type="text/css" id="flux-media-picture-styles">' . $css . '</style>';
            }, 20 );
        }
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
        $converted_formats = get_post_meta( $attachment_id, '_flux_media_converted_formats', true );
        return ! empty( $converted_formats );
    }

    /**
     * Get image URL from attachment for specific format.
     *
     * @since 0.1.0
     * @param int    $attachment_id Attachment ID.
     * @param string $format Target format (webp, avif).
     * @return string|null Image URL or null if not available.
     */
    public static function get_image_url_from_attachment( $attachment_id, $format ) {
        $converted_files = get_post_meta( $attachment_id, '_flux_media_converted_files', true );
        
        if ( empty( $converted_files ) || ! isset( $converted_files[ $format ] ) ) {
            return null;
        }
        
        return self::get_image_url_from_file_path( $converted_files[ $format ] );
    }

    /**
     * Modify attachment URL for optimized display.
     *
     * @since 0.1.0
     * @param string $url The original attachment URL.
     * @param int    $attachment_id The attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified URL.
     */
    public function modify_attachment_url( $url, $attachment_id, $converted_files ) {
        if ( empty( $converted_files ) ) {
            return $url;
        }

        // Single format approach: Use priority AVIF > WebP
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            // Use AVIF as primary format
            return self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_AVIF );
        } elseif ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            // Use WebP as primary format
            return self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_WEBP );
        }

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
            if ( Settings::is_hybrid_approach_enabled() ) {
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
     * Modify block content for optimized display.
     *
     * @since 0.1.0
     * @param string $block_content The block content.
     * @param array  $block The block data.
     * @return string Modified block content.
     */
    public function modify_block_content( $block_content, $block ) {
        // Only process image blocks
        if ( ! in_array( $block['blockName'], [ 'core/image' ], true ) ) {
            return $block_content;
        }

        // Get block attributes
        $attributes = $block['attrs'] ?? [];
        
        // Get attachment ID - try 'id' attribute first, then fall back to URL lookup
        $attachment_id = null;
        
        if ( ! empty( $attributes['id'] ) ) {
            $attachment_id = (int) $attributes['id'];
        } elseif ( ! empty( $attributes['url'] ) ) {
            $image_url = $attributes['url'];
            $attachment_id = $this->get_attachment_id_from_url( $image_url );
        }
        
        if ( ! $attachment_id ) {
            return $block_content;
        }

        // Get converted files
        $converted_files = get_post_meta( $attachment_id, '_flux_media_converted_files', true );
        if ( empty( $converted_files ) ) {
            return $block_content;
        }

        // Check if we have converted formats available
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) || isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            if ( Settings::is_hybrid_approach_enabled() ) {
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
     * Modify post content images for optimized display.
     *
     * @since 0.1.0
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
            $attachment_id = $this->get_attachment_id_from_url( $src_url );
            if ( ! $attachment_id ) {
                return $full_match;
            }
            
            // Get converted files
            $converted_files = get_post_meta( $attachment_id, '_flux_media_converted_files', true );
            if ( empty( $converted_files ) ) {
                return $full_match;
            }
            
            // Check if we have converted formats available
            if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) || isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
                if ( Settings::is_hybrid_approach_enabled() ) {
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
     * @param array   $form_fields Attachment form fields.
     * @param \WP_Post $post The attachment post object.
     * @return array Modified form fields.
     */
    public function modify_attachment_fields( $form_fields, $post ) {
        $converted_files = get_post_meta( $post->ID, '_flux_media_converted_files', true );
        $conversion_disabled = get_post_meta( $post->ID, '_flux_media_conversion_disabled', true );
        
        // Combine all sections under one "Flux Media" label
        $html_content = '';
        
        // Add conversion status if files exist
        if ( ! empty( $converted_files ) ) {
            $html_content .= $this->get_conversion_status_html( $post->ID, $converted_files );
        }
        
        // Always add conversion actions
        $html_content .= $this->get_conversion_actions_html( $post->ID, $conversion_disabled );
        
        // Single Flux Media section with all content
        $form_fields['flux_media'] = [
            'label' => __( 'Flux Media', 'flux-media' ),
            'input' => 'html',
            'html' => $html_content,
        ];
        
        return $form_fields;
    }


    /**
     * Create picture element for block editor images.
     *
     * @since 0.1.0
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @param string $block_content Original block content.
     * @param array  $attributes Block attributes.
     * @return string Picture element HTML.
     */
    private function create_block_picture_element( $attachment_id, $converted_files, $block_content, $attributes ) {
        // Get the size from block attributes, default to 'full'
        $size = $attributes['sizeSlug'] ?? 'full';
        
        // Get image attributes for the fallback img tag
        $img_attributes = [
            'alt' => $attributes['alt'] ?? '',
            'class' => $attributes['className'] ?? '',
            'loading' => $attributes['loading'] ?? 'lazy',
        ];
        
        // Extract wrapper attributes from the existing block content
        $wrapper_attributes = $this->extract_wrapper_attributes_from_block_content( $block_content );
        
        // Build picture element with proper wrapper attributes
        $picture_html = '<picture' . ( $wrapper_attributes ? ' ' . $wrapper_attributes : '' ) . '>';
        
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
        
        // Add fallback img element using WordPress function
        $picture_html .= wp_get_attachment_image( $attachment_id, $size, false, $img_attributes );
        $picture_html .= '</picture>';
        
        return $picture_html;
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
     * Get image URL from file path - the standard static method for URL generation.
     *
     * This is the centralized method used throughout the plugin for converting
     * file paths to URLs. It handles various path formats and provides proper
     * validation and error handling.
     *
     * @since TBD
     * @param string $file_path The file path to convert. Can be absolute or relative.
     * @param bool   $validate_exists Whether to validate that the file exists. Default true.
     * @return string|null The generated URL or null if conversion fails.
     * 
     * @example
     * // Convert absolute path
     * $url = WordPressImageRenderer::get_image_url_from_file_path('/var/www/uploads/2024/01/image.webp');
     * // Returns: 'https://example.com/wp-content/uploads/2024/01/image.webp'
     * 
     * @example
     * // Convert relative path
     * $url = WordPressImageRenderer::get_image_url_from_file_path('2024/01/image.webp');
     * // Returns: 'https://example.com/wp-content/uploads/2024/01/image.webp'
     * 
     * @example
     * // Skip file existence validation
     * $url = WordPressImageRenderer::get_image_url_from_file_path('/path/to/file.webp', false);
     * // Returns URL even if file doesn't exist yet
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
     * @since TBD
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
     * Get attachment ID from URL.
     *
     * @since 0.1.0
     * @param string $url Image URL.
     * @return int|null Attachment ID or null if not found.
     */
    private function get_attachment_id_from_url( $url ) {
        global $wpdb;
        
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        
        // Remove base URL to get relative path
        $relative_path = str_replace( $base_url . '/', '', $url );
        
        // Query for attachment ID
        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
            $relative_path
        ) );
        
        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Get conversion status HTML for admin display.
     *
     * @since 0.1.0
     * @param int   $attachment_id Attachment ID.
     * @param array $converted_files Array of converted file paths.
     * @return string HTML for conversion status.
     */
    private function get_conversion_status_html( $attachment_id, $converted_files ) {
        $upload_dir = wp_upload_dir();
        $original_file = get_attached_file( $attachment_id );
        $original_size = file_exists( $original_file ) ? filesize( $original_file ) : 0;
        $original_url = wp_get_attachment_url( $attachment_id );
        
        $html = '<div class="flux-media-conversion-status" style="background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin: 10px 0;">';
        
        // Original file info
        $html .= '<div style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">';
        $html .= '<h4 style="margin: 0 0 8px 0; color: #333; font-size: 14px;">' . __( 'Original File', 'flux-media' ) . '</h4>';
        $html .= '<div style="font-size: 12px; color: #666;">';
        $html .= '<strong>' . __( 'Size:', 'flux-media' ) . '</strong> ' . size_format( $original_size ) . '<br>';
        $html .= '<strong>' . __( 'URL:', 'flux-media' ) . '</strong> <a href="' . esc_url( $original_url ) . '" target="_blank" style="color: #0073aa; text-decoration: none;">' . esc_html( $original_url ) . '</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        // Converted files
        $html .= '<h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px;">' . __( 'Converted Files', 'flux-media' ) . '</h4>';
        
        if ( empty( $converted_files ) ) {
            $html .= '<p style="color: #666; font-style: italic; margin: 0;">' . __( 'No conversions available', 'flux-media' ) . '</p>';
        } else {
            foreach ( $converted_files as $format => $file_path ) {
                $file_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;
                $savings = $original_size > 0 ? ( ( $original_size - $file_size ) / $original_size ) * 100 : 0;
                
                // Use centralized URL generation
                $converted_url = self::get_image_url_from_file_path( $file_path );
                
                // Format-specific styling
                $format_color = $format === Converter::FORMAT_WEBP ? '#4285f4' : ( $format === Converter::FORMAT_AVIF ? '#ea4335' : '#34a853' );
                
                $html .= '<div style="background: white; border: 1px solid #e1e1e1; border-radius: 3px; padding: 12px; margin-bottom: 8px;">';
                $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">';
                $html .= '<span style="font-weight: bold; color: ' . $format_color . '; text-transform: uppercase; font-size: 12px;">' . esc_html( $format ) . '</span>';
                $html .= '<span style="background: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold;">' . round( $savings, 1 ) . '% ' . __( 'smaller', 'flux-media' ) . '</span>';
                $html .= '</div>';
                
                $html .= '<div style="font-size: 12px; color: #666;">';
                $html .= '<strong>' . __( 'Size:', 'flux-media' ) . '</strong> ' . size_format( $file_size ) . '<br>';
                $html .= '<strong>' . __( 'URL:', 'flux-media' ) . '</strong> <a href="' . esc_url( $converted_url ) . '" target="_blank" style="color: #0073aa; text-decoration: none; word-break: break-all;">' . esc_html( $converted_url ) . '</a>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Get conversion actions HTML for admin display.
     *
     * @since 0.1.0
     * @param int  $attachment_id Attachment ID.
     * @param bool $conversion_disabled Whether conversion is disabled.
     * @return string HTML for conversion actions.
     */
    private function get_conversion_actions_html( $attachment_id, $conversion_disabled ) {
        $html = '<div class="flux-media-conversion-actions" style="background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 12px; margin: 10px 0;">';
        $html .= '<h4 style="margin: 0 0 10px 0; color: #333; font-size: 14px;">' . __( 'Conversion Actions', 'flux-media' ) . '</h4>';
        
        if ( $conversion_disabled ) {
            $html .= sprintf(
                '<button type="button" class="button button-primary" onclick="fluxMediaEnableConversion(%d)" style="background: #00a32a; border-color: #00a32a; color: white; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                    %s
                </button>',
                $attachment_id,
                __( 'Enable Conversion', 'flux-media' )
            );
        } else {
            $html .= '<div style="display: flex; gap: 8px; flex-wrap: wrap;">';
            
            // Check if there are converted files to determine button text
            $converted_files = get_post_meta( $attachment_id, '_flux_media_converted_files', true );
            $button_text = ! empty( $converted_files ) ? __( 'Re-convert', 'flux-media' ) : __( 'Convert', 'flux-media' );
            
            $html .= sprintf(
                '<button type="button" class="button button-primary" onclick="fluxMediaConvertAttachment(%d)" style="background: #0073aa; border-color: #0073aa; color: white; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                    %s
                </button>',
                $attachment_id,
                $button_text
            );
            
            $html .= sprintf(
                '<button type="button" class="button button-secondary" onclick="fluxMediaDisableConversion(%d)" style="background: #f0f0f1; border-color: #c3c4c7; color: #2c3338; padding: 6px 12px; border-radius: 3px; cursor: pointer;">
                    %s
                </button>',
                $attachment_id,
                __( 'Disable Conversion', 'flux-media' )
            );
            $html .= '</div>';
        }
        
        $html .= '</div>';
        return $html;
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
     * Replace img src attribute in block content (single format approach).
     *
     * @since 0.1.0
     * @param string $block_content Original block content.
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified block content.
     */
    private function replace_block_img_src( $block_content, $attachment_id, $converted_files ) {
        // Priority: AVIF > WebP
        if ( isset( $converted_files[ Converter::FORMAT_AVIF ] ) ) {
            $new_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_AVIF );
        } elseif ( isset( $converted_files[ Converter::FORMAT_WEBP ] ) ) {
            $new_url = self::get_image_url_from_attachment( $attachment_id, Converter::FORMAT_WEBP );
        } else {
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
