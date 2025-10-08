<?php
/**
 * WordPress image rendering service for serving optimized formats.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Public;

use FluxMedia\Services\ImageConverter;
use FluxMedia\Services\VideoConverter;
use FluxMedia\Interfaces\Converter;

/**
 * Handles rendering of optimized images with WordPress integration.
 * This class contains the callback methods that WordPressProvider will register.
 *
 * @since 1.0.0
 */
class WordPressImageRenderer {

    /**
     * Image converter service.
     *
     * @since 1.0.0
     * @var ImageConverter
     */
    private $image_converter;

    /**
     * Video converter service.
     *
     * @since 1.0.0
     * @var VideoConverter
     */
    private $video_converter;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param ImageConverter $image_converter Image converter service.
     * @param VideoConverter $video_converter Video converter service.
     */
    public function __construct( ImageConverter $image_converter, VideoConverter $video_converter ) {
        $this->image_converter = $image_converter;
        $this->video_converter = $video_converter;
    }


    /**
     * Modify attachment image attributes to use optimized formats.
     *
     * @since 1.0.0
     * @param array    $attr Image attributes.
     * @param \WP_Post $attachment Attachment post object.
     * @param string   $size Image size.
     * @param array    $converted_files Converted files array (passed by WordPressProvider).
     * @return array Modified attributes.
     */
    public function modify_image_attributes( $attr, $attachment, $size, $converted_files = [] ) {
        // If we have any converted files, we'll handle this in the content filter
        // to generate proper <picture> tags with fallbacks
        if ( ! empty( $converted_files ) ) {
            return $attr;
        }

        return $attr;
    }

    /**
     * Modify content images to use optimized formats.
     *
     * @since 1.0.0
     * @param string $filtered_image The filtered image HTML.
     * @param string $context The context of the image.
     * @param int    $attachment_id The attachment ID.
     * @param array  $converted_files Converted files array (passed by WordPressProvider).
     * @return string Modified image HTML.
     */
    public function modify_content_images( $filtered_image, $context, $attachment_id, $converted_files = [] ) {
        $options = get_option( 'flux_media_options', [] );
        $hybrid_approach = $options['hybrid_approach'] ?? true;

        // Use picture tag if we have any converted formats (for proper fallback support)
        if ( ! empty( $converted_files ) ) {
            return $this->generate_picture_tag( $filtered_image, $attachment_id, $converted_files );
        }

        return $filtered_image;
    }

    /**
     * Modify post content images to use optimized formats.
     *
     * @since 1.0.0
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function modify_post_content_images( $content ) {
        // Only process if we have content
        if ( empty( $content ) ) {
            return $content;
        }

        // Find all img tags in the content
        $pattern = '/<img[^>]+>/i';
        $content = preg_replace_callback( $pattern, [ $this, 'process_content_image' ], $content );

        return $content;
    }

    /**
     * Process individual image in content.
     *
     * @since 1.0.0
     * @param array $matches Regex matches.
     * @return string Processed image HTML.
     */
    private function process_content_image( $matches ) {
        $img_tag = $matches[0];

        // Extract src attribute
        if ( ! preg_match( '/src=["\']([^"\']+)["\']/', $img_tag, $src_matches ) ) {
            return $img_tag;
        }

        $original_src = $src_matches[1];
        $attachment_id = $this->get_attachment_id_from_url( $original_src );

        if ( ! $attachment_id ) {
            return $img_tag;
        }

        // Get converted files for this attachment (will be passed by WordPressProvider)
        $converted_files = $this->get_converted_files_for_attachment( $attachment_id );
        $options = get_option( 'flux_media_options', [] );
        $hybrid_approach = $options['hybrid_approach'] ?? true;

        // Use picture tag if we have any converted formats (for proper fallback support)
        if ( ! empty( $converted_files ) ) {
            return $this->generate_picture_tag_from_content( $img_tag, $attachment_id, $converted_files );
        }

        return $img_tag;
    }

    /**
     * Generate picture tag with available converted formats and original fallback.
     *
     * @since 1.0.0
     * @param string $original_img Original img tag.
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Converted file paths.
     * @return string Picture tag HTML.
     */
    private function generate_picture_tag( $original_img, $attachment_id, $converted_files ) {
        // Extract all attributes from the original img tag
        $attributes = $this->extract_img_attributes( $original_img );

        // Start building the picture tag
        $picture = '<picture>';

        // Add AVIF source if available (highest priority)
        if ( isset( $converted_files[Converter::FORMAT_AVIF] ) ) {
            $avif_url = $this->get_converted_url( $converted_files[Converter::FORMAT_AVIF] );
            if ( $avif_url ) {
                $avif_srcset = $this->generate_format_srcset( $attachment_id, Converter::FORMAT_AVIF );
                if ( $avif_srcset ) {
                    $picture .= '<source srcset="' . esc_attr( $avif_srcset ) . '" type="image/avif">';
                } else {
                    $picture .= '<source srcset="' . esc_attr( $avif_url ) . '" type="image/avif">';
                }
            }
        }

        // Add WebP source if available (fallback)
        if ( isset( $converted_files[Converter::FORMAT_WEBP] ) ) {
            $webp_url = $this->get_converted_url( $converted_files[Converter::FORMAT_WEBP] );
            if ( $webp_url ) {
                $webp_srcset = $this->generate_format_srcset( $attachment_id, Converter::FORMAT_WEBP );
                if ( $webp_srcset ) {
                    $picture .= '<source srcset="' . esc_attr( $webp_srcset ) . '" type="image/webp">';
                } else {
                    $picture .= '<source srcset="' . esc_attr( $webp_url ) . '" type="image/webp">';
                }
            }
        }

        // Always include original img tag as final fallback (preserves all WordPress attributes including srcset)
        $img_tag = '<img' . $this->build_attributes_string( $attributes ) . '>';

        $picture .= $img_tag;
        $picture .= '</picture>';

        return $picture;
    }

    /**
     * Generate picture tag from content image with available converted formats and original fallback.
     *
     * @since 1.0.0
     * @param string $original_img Original img tag.
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Converted file paths.
     * @return string Picture tag HTML.
     */
    private function generate_picture_tag_from_content( $original_img, $attachment_id, $converted_files ) {
        // Extract all attributes from the original img tag
        $attributes = $this->extract_img_attributes( $original_img );

        // Start building the picture tag
        $picture = '<picture>';

        // Add AVIF source if available (highest priority)
        if ( isset( $converted_files[Converter::FORMAT_AVIF] ) ) {
            $avif_url = $this->get_converted_url( $converted_files[Converter::FORMAT_AVIF] );
            if ( $avif_url ) {
                $avif_srcset = $this->generate_format_srcset( $attachment_id, Converter::FORMAT_AVIF );
                if ( $avif_srcset ) {
                    $picture .= '<source srcset="' . esc_attr( $avif_srcset ) . '" type="image/avif">';
                } else {
                    $picture .= '<source srcset="' . esc_attr( $avif_url ) . '" type="image/avif">';
                }
            }
        }

        // Add WebP source if available (fallback)
        if ( isset( $converted_files[Converter::FORMAT_WEBP] ) ) {
            $webp_url = $this->get_converted_url( $converted_files[Converter::FORMAT_WEBP] );
            if ( $webp_url ) {
                $webp_srcset = $this->generate_format_srcset( $attachment_id, Converter::FORMAT_WEBP );
                if ( $webp_srcset ) {
                    $picture .= '<source srcset="' . esc_attr( $webp_srcset ) . '" type="image/webp">';
                } else {
                    $picture .= '<source srcset="' . esc_attr( $webp_url ) . '" type="image/webp">';
                }
            }
        }

        // Always include original img tag as final fallback (preserves all WordPress attributes including srcset)
        $img_tag = '<img' . $this->build_attributes_string( $attributes ) . '>';

        $picture .= $img_tag;
        $picture .= '</picture>';

        return $picture;
    }

    /**
     * Generate srcset for converted formats with responsive sizing.
     *
     * @since 1.0.0
     * @param int    $attachment_id Attachment ID.
     * @param string $format Target format (webp, avif).
     * @return string Srcset string.
     */
    private function generate_format_srcset( $attachment_id, $format ) {
        $srcset = wp_get_attachment_image_srcset( $attachment_id, 'full' );
        
        if ( ! $srcset ) {
            return '';
        }

        // Replace URLs in srcset with converted format URLs
        $srcset_parts = explode( ', ', $srcset );
        $converted_srcset_parts = [];

        foreach ( $srcset_parts as $part ) {
            if ( preg_match( '/^(.+?)\s+(\d+w)$/', $part, $matches ) ) {
                $url = $matches[1];
                $width = $matches[2];
                
                // Get the size-specific converted URL
                $converted_url = $this->get_size_specific_converted_url( $url, $format );
                if ( $converted_url ) {
                    $converted_srcset_parts[] = $converted_url . ' ' . $width;
                }
            }
        }

        return implode( ', ', $converted_srcset_parts );
    }

    /**
     * Get size-specific converted URL.
     *
     * @since 1.0.0
     * @param string $original_url Original image URL.
     * @param string $format Target format.
     * @return string|null Converted URL or null if not found.
     */
    private function get_size_specific_converted_url( $original_url, $format ) {
        // Extract the base filename from the URL
        $path = parse_url( $original_url, PHP_URL_PATH );
        $path_info = pathinfo( $path );
        $base_name = $path_info['filename'];
        $directory = dirname( $path );

        // Remove size suffix (e.g., -1024x576) to get base filename
        $base_name = preg_replace( '/-\d+x\d+$/', '', $base_name );

        // Construct converted URL
        $converted_path = $directory . '/' . $base_name . '.' . $format;
        $converted_url = home_url( $converted_path );

        // Check if the converted file exists
        $upload_dir = wp_upload_dir();
        $converted_file_path = $upload_dir['basedir'] . $converted_path;

        if ( file_exists( $converted_file_path ) ) {
            return $converted_url;
        }

        return null;
    }

    /**
     * Replace image src and srcset with converted format.
     *
     * @since 1.0.0
     * @param string $img_tag Original img tag.
     * @param int    $attachment_id Attachment ID.
     * @param string $format Target format.
     * @return string Modified img tag.
     */
    private function replace_image_src_with_srcset( $img_tag, $attachment_id, $format ) {
        // Extract attributes
        $attributes = $this->extract_img_attributes( $img_tag );
        
        // Get converted URL for the main src
        $converted_srcset = $this->generate_format_srcset( $attachment_id, $format );
        if ( $converted_srcset ) {
            // Use the first (largest) image from srcset as the main src
            $srcset_parts = explode( ', ', $converted_srcset );
            $main_src = trim( explode( ' ', $srcset_parts[0] )[0] );
            $attributes['src'] = $main_src;
            $attributes['srcset'] = $converted_srcset;
        } else {
            // Fallback to single converted image
            $converted_files = $this->get_converted_files_for_attachment( $attachment_id );
            if ( isset( $converted_files[ $format ] ) ) {
                $converted_url = $this->get_converted_url( $converted_files[ $format ] );
                if ( $converted_url ) {
                    $attributes['src'] = $converted_url;
                }
            }
        }

        // Rebuild the img tag
        return '<img' . $this->build_attributes_string( $attributes ) . '>';
    }

    /**
     * Get converted URL from file path.
     *
     * @since 1.0.0
     * @param string $file_path File path.
     * @return string|null URL or null if file doesn't exist.
     */
    private function get_converted_url( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }

        $upload_dir = wp_upload_dir();
        $relative_path = str_replace( $upload_dir['basedir'], '', $file_path );
        
        return $upload_dir['baseurl'] . $relative_path;
    }

    /**
     * Get preferred format based on browser support and settings.
     *
     * @since 1.0.0
     * @param array $converted_files Available converted files.
     * @return string|null Preferred format.
     */
    private function get_preferred_format( $converted_files ) {
        // For images: Prefer AVIF if available, fallback to WebP
        if ( isset( $converted_files[Converter::FORMAT_AVIF] ) ) {
            return Converter::FORMAT_AVIF;
        }
        
        if ( isset( $converted_files[Converter::FORMAT_WEBP] ) ) {
            return Converter::FORMAT_WEBP;
        }

        // For videos: Prefer AV1 if available, fallback to WebM
        if ( isset( $converted_files[Converter::FORMAT_AV1] ) ) {
            return Converter::FORMAT_AV1;
        }
        
        if ( isset( $converted_files[Converter::FORMAT_WEBM] ) ) {
            return Converter::FORMAT_WEBM;
        }

        return null;
    }

    /**
     * Get attachment ID from URL.
     *
     * @since 1.0.0
     * @param string $url Image URL.
     * @return int|null Attachment ID or null if not found.
     */
    private function get_attachment_id_from_url( $url ) {
        global $wpdb;

        // Remove size suffix from URL
        $url = preg_replace( '/-\d+x\d+\.(jpg|jpeg|png|gif|' . Converter::FORMAT_WEBP . '|' . Converter::FORMAT_AVIF . ')$/', '.$1', $url );

        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s",
                $url
            )
        );

        if ( $attachment_id ) {
            return (int) $attachment_id;
        }

        // Try to find by file path
        $upload_dir = wp_upload_dir();
        $relative_path = str_replace( $upload_dir['baseurl'], '', $url );
        $file_path = $upload_dir['basedir'] . $relative_path;

        $attachment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
                $relative_path
            )
        );

        return $attachment_id ? (int) $attachment_id : null;
    }

    /**
     * Extract all attributes from img tag.
     *
     * @since 1.0.0
     * @param string $img_tag Image tag HTML.
     * @return array Attributes array.
     */
    private function extract_img_attributes( $img_tag ) {
        $attributes = [];
        
        if ( preg_match_all( '/(\w+)=["\']([^"\']*)["\']/', $img_tag, $matches, PREG_SET_ORDER ) ) {
            foreach ( $matches as $match ) {
                $attributes[ $match[1] ] = $match[2];
            }
        }

        return $attributes;
    }

    /**
     * Build attributes string from array.
     *
     * @since 1.0.0
     * @param array $attributes Attributes array.
     * @return string Attributes string.
     */
    private function build_attributes_string( $attributes ) {
        $attr_string = '';
        
        foreach ( $attributes as $key => $value ) {
            $attr_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
        }

        return $attr_string;
    }

    /**
     * Modify attachment fields to show optimized formats.
     *
     * @since 1.0.0
     * @param array   $form_fields Attachment form fields.
     * @param \WP_Post $post The attachment post object.
     * @return array Modified form fields.
     */
    public function modify_attachment_fields( $form_fields, $post ) {
        // Only modify for images and videos
        $is_image = wp_attachment_is_image( $post->ID );
        $is_video = $this->is_video_attachment( $post->ID );
        
        if ( ! $is_image && ! $is_video ) {
            return $form_fields;
        }

        // Get converted files for this attachment
        $converted_files = $this->get_converted_files_for_attachment( $post->ID );
        
        if ( empty( $converted_files ) ) {
            return $form_fields;
        }
        
        // Update the File URL field to show the most optimized format
        if ( isset( $form_fields['url'] ) ) {
            $preferred_format = $this->get_preferred_format( $converted_files );
            if ( $preferred_format && isset( $converted_files[ $preferred_format ] ) ) {
                $optimized_url = $this->get_converted_url( $converted_files[ $preferred_format ] );
                if ( $optimized_url ) {
                    $form_fields['url']['value'] = $optimized_url;
                    $form_fields['url']['label'] = __( 'Optimized File URL:', 'flux-media' );
                    $form_fields['url']['helps'] = sprintf( 
                        __( 'This is the %s version (most optimized). Original file is preserved below.', 'flux-media' ),
                        strtoupper( $preferred_format )
                    );
                }
            }
        }

        // Add converted formats section after the original file
        $converted_formats_html = $this->generate_converted_formats_html( $post->ID, $converted_files );
        
        $form_fields['flux_media_converted'] = [
            'label' => __( 'Flux Media', 'flux-media' ),
            'input' => 'html',
            'html' => $converted_formats_html,
        ];

        return $form_fields;
    }

    /**
     * Generate HTML for converted formats display.
     *
     * @since 1.0.0
     * @param int   $attachment_id Attachment ID.
     * @param array $converted_files Converted file paths.
     * @return string HTML content.
     */
    private function generate_converted_formats_html( $attachment_id, $converted_files ) {
        $html = '<div class="flux-media-converted-formats">';
        
        // Show converted formats
        $html .= '<div class="converted-formats">';
        $html .= '<h4>' . esc_html__( 'Optimized Formats:', 'flux-media' ) . '</h4>';
        $html .= '<ul>';
        
        foreach ( $converted_files as $format => $file_path ) {
            $format_name = $this->get_format_display_name( $format );
            $file_url = $this->get_converted_url( $file_path );
            $file_size = $this->get_file_size_display( $file_path );
            
            $html .= '<li>';
            $html .= '<strong>' . esc_html( $format_name ) . '</strong>';
            if ( $file_size ) {
                $html .= ' <span class="file-size">(' . esc_html( $file_size ) . ')</span>';
            }
            $html .= '<br>';
            $html .= '<a href="' . esc_url( $file_url ) . '" target="_blank">' . esc_html( basename( $file_path ) ) . '</a>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        // Add styling
        $html .= '<style>
            .flux-media-converted-formats {
                margin-top: 10px;
            }
            .flux-media-converted-formats h4 {
                margin: 15px 0 5px 0;
                font-size: 13px;
                font-weight: 600;
            }
            .flux-media-converted-formats ul {
                margin: 5px 0;
                padding-left: 0;
                list-style: none;
            }
            .flux-media-converted-formats li {
                margin-bottom: 8px;
                padding: 8px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 3px;
            }
            .flux-media-converted-formats .file-size {
                color: #666;
                font-weight: normal;
            }
            .flux-media-converted-formats a {
                color: #0073aa;
                text-decoration: none;
            }
            .flux-media-converted-formats a:hover {
                color: #005177;
            }
            .original-file {
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
                margin-bottom: 10px;
            }
        </style>';
        
        return $html;
    }

    /**
     * Get display name for format.
     *
     * @since 1.0.0
     * @param string $format Format constant.
     * @return string Display name.
     */
    private function get_format_display_name( $format ) {
        switch ( $format ) {
            case Converter::FORMAT_AVIF:
                return __( 'AVIF', 'flux-media' );
            case Converter::FORMAT_WEBP:
                return __( 'WebP', 'flux-media' );
            case Converter::FORMAT_AV1:
                return __( 'AV1', 'flux-media' );
            case Converter::FORMAT_WEBM:
                return __( 'WebM', 'flux-media' );
            default:
                return strtoupper( $format );
        }
    }

    /**
     * Check if attachment is a video file.
     *
     * @since 1.0.0
     * @param int $attachment_id Attachment ID.
     * @return bool True if video, false otherwise.
     */
    private function is_video_attachment( $attachment_id ) {
        $mime_type = get_post_mime_type( $attachment_id );
        return strpos( $mime_type, 'video/' ) === 0;
    }

    /**
     * Get human-readable file size.
     *
     * @since 1.0.0
     * @param string $file_path File path.
     * @return string|null File size or null if file doesn't exist.
     */
    private function get_file_size_display( $file_path ) {
        if ( ! file_exists( $file_path ) ) {
            return null;
        }

        $bytes = filesize( $file_path );
        if ( $bytes === false ) {
            return null;
        }

        $units = [ 'B', 'KB', 'MB', 'GB' ];
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );

        $bytes /= pow( 1024, $pow );

        return round( $bytes, 1 ) . ' ' . $units[ $pow ];
    }

    /**
     * Get converted files for an attachment from WordPress post meta.
     *
     * @since 1.0.0
     * @param int $attachment_id Attachment ID.
     * @return array Converted files array.
     */
    private function get_converted_files_for_attachment( $attachment_id ) {
        return get_post_meta( $attachment_id, '_flux_media_converted_files', true ) ?: [];
    }
}
