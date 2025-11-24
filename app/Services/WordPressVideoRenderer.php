<?php
/**
 * WordPress video renderer for handling video display and optimization.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\AttachmentMetaHandler;

/**
 * WordPress video renderer for handling video display and optimization.
 *
 * @since 1.0.0
 */
class WordPressVideoRenderer {

    /**
     * Modify attachment URL for optimized video display.
     *
     * @since 1.0.0
     * @param string $url The original attachment URL.
     * @param int    $attachment_id The attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified URL (always returns original URL as fallback, never null or empty).
     */
    public function modify_attachment_url( $url, $attachment_id, $converted_files ) {
        // Always ensure we have a valid URL to return
        if ( empty( $url ) ) {
            return $url;
        }

        if ( empty( $converted_files ) ) {
            return $url;
        }

        // For videos: Use priority AV1 > WebM (single format approach)
        // When hybrid approach is enabled, we still return the best format for direct URL access
        // Always check for null/empty returns and fallback to original URL
        if ( isset( $converted_files[ Converter::FORMAT_AV1 ] ) ) {
            $converted_url = WordPressImageRenderer::get_image_url_from_file_path( $converted_files[ Converter::FORMAT_AV1 ] );
            if ( ! empty( $converted_url ) ) {
                return $converted_url;
            }
        }
        
        if ( isset( $converted_files[ Converter::FORMAT_WEBM ] ) ) {
            $converted_url = WordPressImageRenderer::get_image_url_from_file_path( $converted_files[ Converter::FORMAT_WEBM ] );
            if ( ! empty( $converted_url ) ) {
                return $converted_url;
            }
        }

        // Always return original URL as fallback (never null or empty)
        return $url;
    }

    /**
     * Modify content videos for optimized display.
     *
     * @since 1.0.0
     * @param string $filtered_video The filtered video HTML.
     * @param string $context The context of the video.
     * @param int    $attachment_id The attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified video HTML.
     */
    public function modify_content_videos( $filtered_video, $context, $attachment_id, $converted_files ) {
        if ( empty( $converted_files ) ) {
            return $filtered_video;
        }

        // Check if we have converted video formats available
        if ( isset( $converted_files[ Converter::FORMAT_AV1 ] ) || isset( $converted_files[ Converter::FORMAT_WEBM ] ) ) {
            return $this->replace_video_src( $filtered_video, $attachment_id, $converted_files );
        }

        return $filtered_video;
    }

    /**
     * Modify block content for optimized video display.
     *
     * @since 1.0.0
     * @param string $block_content The block content.
     * @param array  $block The block data.
     * @return string Modified block content.
     */
    public function modify_block_content( $block_content, $block ) {
        // Only process video blocks
        if ( 'core/video' !== ( $block['blockName'] ?? '' ) ) {
            return $block_content;
        }

        // Get block attributes
        $attributes = $block['attrs'] ?? [];
        
        // Get attachment ID - try 'id' attribute first, then fall back to URL lookup
        $attachment_id = null;
        
        if ( ! empty( $attributes['id'] ) ) {
            $attachment_id = (int) $attributes['id'];
        } elseif ( ! empty( $attributes['url'] ) ) {
            $video_url = $attributes['url'];
            $attachment_id = $this->get_attachment_id_from_url( $video_url );
        }
        
        if ( ! $attachment_id ) {
            return $block_content;
        }

        // Get converted files
        $converted_files = AttachmentMetaHandler::get_converted_files( $attachment_id );
        if ( empty( $converted_files ) ) {
            return $block_content;
        }

        // Check if we have converted video formats available
        if ( isset( $converted_files[ Converter::FORMAT_AV1 ] ) || isset( $converted_files[ Converter::FORMAT_WEBM ] ) ) {
            return $this->replace_block_video_src( $block_content, $attachment_id, $converted_files );
        }

        return $block_content;
    }

    /**
     * Modify post content videos for optimized display.
     *
     * @since 1.0.0
     * @param string $content Post content.
     * @return string Modified content.
     */
    public function modify_post_content_videos( $content ) {
        // Find all video tags in content (including closing tag)
        $pattern = '/<video([^>]*?)>(.*?)<\/video>/is';
        
        return preg_replace_callback( $pattern, function( $matches ) {
            $full_match = $matches[0];
            $video_attrs = $matches[1];
            $video_content = $matches[2] ?? '';
            
            // Extract src attribute from video tag
            if ( preg_match( '/src=["\']([^"\']*?)["\']/', $video_attrs, $src_matches ) ) {
                $src_url = $src_matches[1];
                
                // Get attachment ID from URL
                $attachment_id = $this->get_attachment_id_from_url( $src_url );
                if ( ! $attachment_id ) {
                    return $full_match;
                }
                
                // Get converted files
                $converted_files = AttachmentMetaHandler::get_converted_files( $attachment_id );
                if ( empty( $converted_files ) ) {
                    return $full_match;
                }
                
                // Replace video src with converted formats
                return $this->replace_video_src( $full_match, $attachment_id, $converted_files );
            }
            
            return $full_match;
        }, $content );
    }

    /**
     * Replace video src attribute with optimized formats.
     *
     * When hybrid approach is enabled, adds multiple source elements for AV1 and WebM.
     * When disabled, only updates the src URL to the best available format (AV1 priority).
     *
     * @since 1.0.0
     * @param string $video_html Original video HTML.
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified video HTML.
     */
    private function replace_video_src( $video_html, $attachment_id, $converted_files ) {
        // Extract original video src and attributes
        if ( ! preg_match( '/<video([^>]*?)>(.*?)<\/video>/is', $video_html, $matches ) ) {
            return $video_html;
        }

        $video_attrs = $matches[1];
        $video_content = $matches[2] ?? '';
        $original_src = '';
        
        // Extract original src
        if ( preg_match( '/src=["\']([^"\']*?)["\']/', $video_attrs, $src_matches ) ) {
            $original_src = $src_matches[1];
        }

        // Check if hybrid approach is enabled
        $hybrid_enabled = Settings::is_video_hybrid_approach_enabled();

        if ( $hybrid_enabled ) {
            // Hybrid approach: Build new video tag with source elements
            $new_video_html = '<video' . $video_attrs;
            
            // Remove existing src attribute (we'll use source elements instead)
            $new_video_html = preg_replace( '/\s+src=["\'][^"\']*["\']/', '', $new_video_html );
            $new_video_html .= '>';

            // Add both AV1 and WebM sources if available
            if ( isset( $converted_files[ Converter::FORMAT_AV1 ] ) ) {
                $av1_url = WordPressImageRenderer::get_image_url_from_file_path( $converted_files[ Converter::FORMAT_AV1 ] );
                if ( $av1_url ) {
                    $new_video_html .= '<source src="' . esc_url( $av1_url ) . '" type="video/mp4; codecs=av01">';
                }
            }

            if ( isset( $converted_files[ Converter::FORMAT_WEBM ] ) ) {
                $webm_url = WordPressImageRenderer::get_image_url_from_file_path( $converted_files[ Converter::FORMAT_WEBM ] );
                if ( $webm_url ) {
                    $new_video_html .= '<source src="' . esc_url( $webm_url ) . '" type="video/webm">';
                }
            }

            // Add original as fallback
            if ( $original_src ) {
                $new_video_html .= '<source src="' . esc_url( $original_src ) . '">';
            }

            // Preserve any existing content between video tags (e.g., track elements)
            $new_video_html .= $video_content;
            $new_video_html .= '</video>';

            return $new_video_html;
        } else {
            // Single format approach: Only update the src URL, keep original HTML structure
            $optimized_url = null;
            
            // Use priority AV1 > WebM
            if ( isset( $converted_files[ Converter::FORMAT_AV1 ] ) ) {
                $optimized_url = WordPressImageRenderer::get_image_url_from_file_path( $converted_files[ Converter::FORMAT_AV1 ] );
            } elseif ( isset( $converted_files[ Converter::FORMAT_WEBM ] ) ) {
                $optimized_url = WordPressImageRenderer::get_image_url_from_file_path( $converted_files[ Converter::FORMAT_WEBM ] );
            }
            
            // If we have an optimized URL, replace the src attribute
            if ( $optimized_url && $original_src ) {
                return preg_replace(
                    '/src=["\']([^"\']*?)["\']/',
                    'src="' . esc_url( $optimized_url ) . '"',
                    $video_html
                );
            }
            
            // No optimized format available, return original
            return $video_html;
        }
    }

    /**
     * Replace video src attribute in block content with optimized formats.
     *
     * @since 1.0.0
     * @param string $block_content Original block content.
     * @param int    $attachment_id Attachment ID.
     * @param array  $converted_files Array of converted file paths.
     * @return string Modified block content.
     */
    private function replace_block_video_src( $block_content, $attachment_id, $converted_files ) {
        // Find video tag in block content
        if ( ! preg_match( '/<video([^>]*?)>/i', $block_content, $matches ) ) {
            return $block_content;
        }

        $video_tag = $matches[0];

        // Replace the video tag with optimized version
        $optimized_video = $this->replace_video_src( $video_tag, $attachment_id, $converted_files );

        // Replace in block content
        return str_replace( $video_tag, $optimized_video, $block_content );
    }

    /**
     * Get attachment ID from URL.
     *
     * @since 1.0.0
     * @param string $url Video URL.
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
}

