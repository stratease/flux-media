<?php
/**
 * Image processor interface for image processing libraries.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

/**
 * Image processor interface for image processing libraries.
 *
 * @since 0.1.0
 */
interface ImageProcessorInterface {

    /**
     * Convert image to WebP format.
     *
     * @since 0.1.0
     * @param string $source_path Source image path.
     * @param string $destination_path Destination path.
     * @param array  $options Conversion options.
     * @return bool True on success, false on failure.
     */
    public function convert_to_webp( $source_path, $destination_path, $options = [] );

    /**
     * Convert image to AVIF format.
     *
     * @since 0.1.0
     * @param string $source_path Source image path.
     * @param string $destination_path Destination path.
     * @param array  $options Conversion options.
     * @return bool True on success, false on failure.
     */
    public function convert_to_avif( $source_path, $destination_path, $options = [] );

    /**
     * Get processor information.
     *
     * @since 0.1.0
     * @return array Processor information.
     */
    public function get_info();

    /**
     * Check if processor supports animated GIF conversion.
     *
     * @since TBD
     * @return bool True if processor can handle animated GIFs, false otherwise.
     */
    public function supports_animated_gif();

    /**
     * Check if a GIF file is animated.
     *
     * @since TBD
     * @param string $file_path Path to the GIF file.
     * @return bool True if animated, false otherwise.
     */
    public function is_animated_gif( $file_path );
}
