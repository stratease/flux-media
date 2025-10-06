<?php
/**
 * Converter interface for all file conversion operations.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Interfaces;

/**
 * Interface for all file converters.
 *
 * @since 1.0.0
 */
interface Converter {

    /**
     * Conversion type constants.
     */
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_DOCUMENT = 'document';

    /**
     * Image format constants.
     */
    const FORMAT_WEBP = 'webp';
    const FORMAT_AVIF = 'avif';
    const FORMAT_JPEG = 'jpeg';
    const FORMAT_PNG = 'png';
    const FORMAT_GIF = 'gif';

    /**
     * Video format constants.
     */
    const FORMAT_AV1 = 'av1';
    const FORMAT_WEBM = 'webm';
    const FORMAT_MP4 = 'mp4';
    const FORMAT_OGV = 'ogv';

    /**
     * Audio format constants.
     */
    const FORMAT_OPUS = 'opus';
    const FORMAT_AAC = 'aac';
    const FORMAT_MP3 = 'mp3';

    /**
     * Set the source file path.
     *
     * @since 1.0.0
     * @param string $source_path Source file path.
     * @return Converter Fluent interface.
     */
    public function from( $source_path );

    /**
     * Set the destination file path.
     *
     * @since 1.0.0
     * @param string $destination_path Destination file path.
     * @return Converter Fluent interface.
     */
    public function to( $destination_path );

    /**
     * Set conversion options.
     *
     * @since 1.0.0
     * @param array $options Conversion options.
     * @return Converter Fluent interface.
     */
    public function with_options( $options );

    /**
     * Set a specific option.
     *
     * @since 1.0.0
     * @param string $key Option key.
     * @param mixed  $value Option value.
     * @return Converter Fluent interface.
     */
    public function set_option( $key, $value );

    /**
     * Perform the conversion.
     *
     * @since 1.0.0
     * @return bool True on success, false on failure.
     */
    public function convert();

    /**
     * Get the last error message.
     *
     * @since 1.0.0
     * @return string|null Error message or null if no error.
     */
    public function get_last_error();

    /**
     * Get all error messages.
     *
     * @since 1.0.0
     * @return array Array of error messages.
     */
    public function get_errors();

    /**
     * Check if conversion is supported.
     *
     * @since 1.0.0
     * @param string $format Target format.
     * @return bool True if supported, false otherwise.
     */
    public function is_format_supported( $format );

    /**
     * Get supported formats for this converter.
     *
     * @since 1.0.0
     * @return array Array of supported formats.
     */
    public function get_supported_formats();

    /**
     * Get converter type.
     *
     * @since 1.0.0
     * @return string Converter type constant.
     */
    public function get_type();

    /**
     * Reset the converter state.
     *
     * @since 1.0.0
     * @return Converter Fluent interface.
     */
    public function reset();
}
