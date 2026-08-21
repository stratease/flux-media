<?php
/**
 * Converter interface for media conversion services.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

/**
 * Converter interface for media conversion services.
 *
 * @since 0.1.0
 */
interface Converter {

    /**
     * Image format constants.
     */
    public const FORMAT_JPEG = 'jpeg';
    public const FORMAT_PNG = 'png';
    public const FORMAT_GIF = 'gif';
    public const FORMAT_WEBP = 'webp';
    public const FORMAT_AVIF = 'avif';
    public const FORMAT_HEIC = 'heic';
    public const FORMAT_HEIF = 'heif';

    /**
     * Video format constants.
     */
    public const FORMAT_AV1 = 'av1';
    public const FORMAT_WEBM = 'webm';

    /**
     * Converter type constants.
     */
    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';

    /**
     * Set the source file path.
     *
     * @since 0.1.0
     * @param string $source_path Source file path.
     * @return Converter Fluent interface.
     */
    public function from( $source_path );

    /**
     * Set the destination file path.
     *
     * @since 0.1.0
     * @param string $destination_path Destination file path.
     * @return Converter Fluent interface.
     */
    public function to( $destination_path );

    /**
     * Set conversion options.
     *
     * @since 0.1.0
     * @param array $options Conversion options.
     * @return Converter Fluent interface.
     */
    public function with_options( $options );

    /**
     * Set a specific option.
     *
     * @since 0.1.0
     * @param string $key Option key.
     * @param mixed  $value Option value.
     * @return Converter Fluent interface.
     */
    public function set_option( $key, $value );

    /**
     * Perform the conversion using fluent interface.
     *
     * @since 0.1.0
     * @return bool True on success, false on failure.
     */
    public function convert();

    /**
     * Get the last error message.
     *
     * @since 0.1.0
     * @return string|null Error message or null if no error.
     */
    public function get_last_error();

    /**
     * Get all error messages.
     *
     * @since 0.1.0
     * @return array Array of error messages.
     */
    public function get_errors();

    /**
     * Check if conversion is supported.
     *
     * @since 0.1.0
     * @param string $format Target format.
     * @return bool True if supported, false otherwise.
     */
    public function is_format_supported( $format );

    /**
     * Get supported formats for this converter.
     *
     * @since 0.1.0
     * @return array Array of supported formats.
     */
    public function get_supported_formats();

    /**
     * Get converter type.
     *
     * @since 0.1.0
     * @return string Converter type constant.
     */
    public function get_type();

    /**
     * Reset the converter state.
     *
     * @since 0.1.0
     * @return Converter Fluent interface.
     */
    public function reset();
}
