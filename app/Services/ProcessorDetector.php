<?php
/**
 * Processor detector for checking PHP library availability.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\ProcessorTypes;

/**
 * Detects available PHP processors (GD, Imagick, FFmpeg, etc.).
 *
 * @since 0.1.0
 */
class ProcessorDetector {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        // No dependencies needed - this class handles all detection internally
    }

    /**
     * Get available image processors.
     *
     * @since 0.1.0
     * @return array Available image processors with their capabilities.
     */
    public function get_available_image_processors() {
        $processors = [];

        // Check Imagick
        if ( $this->is_imagick_available() ) {
            $processors[ ProcessorTypes::IMAGE_IMAGICK ] = [
                'available' => true,
                'type' => ProcessorTypes::IMAGE_IMAGICK,
                'version' => $this->get_imagick_version(),
                'webp_support' => $this->imagick_supports_webp(),
                'avif_support' => $this->imagick_supports_avif(),
                'animated_gif_support' => $this->imagick_supports_animated_gif(),
            ];
        }

        // Check GD
        if ( $this->is_gd_available() ) {
            $processors[ ProcessorTypes::IMAGE_GD ] = [
                'available' => true,
                'type' => ProcessorTypes::IMAGE_GD,
                'version' => $this->get_gd_version(),
                'webp_support' => $this->gd_supports_webp(),
                'avif_support' => $this->gd_supports_avif(),
                'animated_gif_support' => false, // GD cannot preserve animation.
            ];
        }

        return $processors;
    }

    /**
     * Get available video processors.
     *
     * @since 0.1.0
     * @return array Available video processors with their capabilities.
     */
    public function get_available_video_processors() {
        $processors = [];

        // Check FFmpeg binary AND PHP-FFmpeg library
        if ( $this->is_ffmpeg_available() && $this->is_php_ffmpeg_available() ) {
            $processors[ ProcessorTypes::VIDEO_FFMPEG ] = [
                'available' => true,
                'type' => ProcessorTypes::VIDEO_FFMPEG,
                'version' => $this->get_ffmpeg_version(),
                'av1_support' => $this->ffmpeg_supports_av1(),
                'webm_support' => $this->ffmpeg_supports_webm(),
            ];
        }

        return $processors;
    }

    /**
     * Get the best image processor for a specific format.
     *
     * @since 0.1.0
     * @param string $format Target format (webp, avif).
     * @return string|null Best processor type or null if none available.
     */
    public function get_best_image_processor( $format ) {
        $processors = $this->get_available_image_processors();

        // Prefer Imagick for better quality and more features
        if ( isset( $processors[ ProcessorTypes::IMAGE_IMAGICK ] ) && $this->processor_supports_format( $processors[ ProcessorTypes::IMAGE_IMAGICK ], $format ) ) {
            return ProcessorTypes::IMAGE_IMAGICK;
        }

        // Fallback to GD
        if ( isset( $processors[ ProcessorTypes::IMAGE_GD ] ) && $this->processor_supports_format( $processors[ ProcessorTypes::IMAGE_GD ], $format ) ) {
            return ProcessorTypes::IMAGE_GD;
        }

        return null;
    }

    /**
     * Get the best video processor for a specific format.
     *
     * @since 0.1.0
     * @param string $format Target format (av1, webm).
     * @return string|null Best processor type or null if none available.
     */
    public function get_best_video_processor( $format ) {
        $processors = $this->get_available_video_processors();

        // FFmpeg is the only video processor we support
        if ( isset( $processors[ ProcessorTypes::VIDEO_FFMPEG ] ) && $this->processor_supports_format( $processors[ ProcessorTypes::VIDEO_FFMPEG ], $format ) ) {
            return ProcessorTypes::VIDEO_FFMPEG;
        }

        return null;
    }

    /**
     * Check if Imagick is available.
     *
     * @since 0.1.0
     * @return bool True if Imagick is available, false otherwise.
     */
    public function is_imagick_available() {
        return extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
    }

    /**
     * Check if GD is available.
     *
     * @since 0.1.0
     * @return bool True if GD is available, false otherwise.
     */
    public function is_gd_available() {
        return extension_loaded( 'gd' );
    }

    /**
     * Check if FFmpeg is available.
     *
     * Uses the PHP-FFmpeg library to detect if FFmpeg is available.
     * The library handles binary detection internally.
     *
     * @since 1.0.0
     * @return bool True if FFmpeg is available, false otherwise.
     */
    public function is_ffmpeg_available() {
        // First check if the library is available
        if ( ! $this->is_php_ffmpeg_available() ) {
            return false;
        }

        // Try to create an FFMpeg instance - the library will auto-detect the binary
        try {
            $ffmpeg = \FluxMedia\FFMpeg\FFMpeg::create();
            return $ffmpeg !== null;
        } catch ( \Exception $e ) {
            // FFmpeg is not available or cannot be detected
            return false;
        }
    }

    /**
     * Check if PHP-FFmpeg library is available.
     *
     * @since 1.0.0
     * @return bool True if PHP-FFmpeg library is available, false otherwise.
     */
    public function is_php_ffmpeg_available() {
        return class_exists( 'FluxMedia\FFMpeg\FFMpeg' );
    }

    /**
     * Get Imagick version information.
     *
     * @since 0.1.0
     * @return string Imagick version or 'Unknown'.
     */
    private function get_imagick_version() {
        if ( ! $this->is_imagick_available() ) {
            return 'Not available';
        }

        try {
            $imagick = new \Imagick();
            $version = $imagick->getVersion();
            return $version['versionString'] ?? 'Unknown';
        } catch ( \Exception $e ) {
            return 'Unknown';
        }
    }

    /**
     * Get GD version information.
     *
     * @since 0.1.0
     * @return string GD version or 'Unknown'.
     */
    private function get_gd_version() {
        if ( ! $this->is_gd_available() ) {
            return 'Not available';
        }

        $gd_info = gd_info();
        return $gd_info['GD Version'] ?? 'Unknown';
    }

    /**
     * Get FFmpeg version information.
     *
     * Uses the PHP-FFmpeg library to get version information.
     *
     * @since 1.0.0
     * @return string FFmpeg version or 'Unknown'.
     */
    private function get_ffmpeg_version() {
        if ( ! $this->is_ffmpeg_available() ) {
            return 'Not available';
        }

        try {
            // The library doesn't expose version directly
            // We can create an instance to confirm it's available
            $ffmpeg = \FluxMedia\FFMpeg\FFMpeg::create();
            if ( $ffmpeg !== null ) {
                return 'Available';
            }
            return 'Unknown';
        } catch ( \Exception $e ) {
            return 'Unknown';
        }
    }

    /**
     * Check if Imagick supports WebP.
     *
     * @since 0.1.0
     * @return bool True if Imagick supports WebP, false otherwise.
     */
    private function imagick_supports_webp() {
        if ( ! $this->is_imagick_available() ) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats();
            return in_array( 'WEBP', $formats, true );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Check if Imagick supports AVIF.
     *
     * @since 0.1.0
     * @return bool True if Imagick supports AVIF, false otherwise.
     */
    private function imagick_supports_avif() {
        if ( ! $this->is_imagick_available() ) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats();
            return in_array( 'AVIF', $formats, true );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Check if GD supports WebP.
     *
     * @since 0.1.0
     * @return bool True if GD supports WebP, false otherwise.
     */
    private function gd_supports_webp() {
        if ( ! $this->is_gd_available() ) {
            return false;
        }

        $gd_info = gd_info();
        return isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'];
    }

    /**
     * Check if GD supports AVIF.
     *
     * @since 0.1.0
     * @return bool True if GD supports AVIF, false otherwise.
     */
    private function gd_supports_avif() {
        return $this->is_gd_available() && function_exists( 'imageavif' );
    }

    /**
     * Check if Imagick supports animated GIF.
     *
     * @since TBD
     * @return bool True if Imagick supports GIF format, false otherwise.
     */
    private function imagick_supports_animated_gif() {
        if ( ! $this->is_imagick_available() ) {
            return false;
        }

        try {
            $imagick = new \Imagick();
            $formats = $imagick->queryFormats();
            return in_array( 'GIF', $formats, true );
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Check if FFmpeg supports AV1.
     *
     * Uses the PHP-FFmpeg library to check if AV1 codec is available.
     * If FFmpeg is available, we assume AV1 support. The actual conversion
     * will fail gracefully if the codec isn't supported.
     *
     * @since 1.0.0
     * @return bool True if FFmpeg supports AV1, false otherwise.
     */
    private function ffmpeg_supports_av1() {
        // If FFmpeg is available, assume AV1 support
        // The actual conversion will handle codec availability errors
        return $this->is_ffmpeg_available();
    }

    /**
     * Check if FFmpeg supports WebM.
     *
     * Uses the PHP-FFmpeg library to check if WebM codec is available.
     * If FFmpeg is available, we assume WebM support. The actual conversion
     * will fail gracefully if the codec isn't supported.
     *
     * @since 1.0.0
     * @return bool True if FFmpeg supports WebM, false otherwise.
     */
    private function ffmpeg_supports_webm() {
        // If FFmpeg is available, assume WebM support
        // The actual conversion will handle codec availability errors
        return $this->is_ffmpeg_available();
    }

    /**
     * Check if a processor supports a specific format.
     *
     * @since 0.1.0
     * @param array  $processor Processor information.
     * @param string $format Target format.
     * @return bool True if processor supports format, false otherwise.
     */
    private function processor_supports_format( $processor, $format ) {
        switch ( $format ) {
            case 'webp':
                return $processor['webp_support'] ?? false;
            case 'avif':
                return $processor['avif_support'] ?? false;
            case 'av1':
                return $processor['av1_support'] ?? false;
            case 'webm':
                return $processor['webm_support'] ?? false;
            default:
                return false;
        }
    }
}
