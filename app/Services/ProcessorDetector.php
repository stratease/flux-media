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
     * @since 0.1.0
     * @return bool True if FFmpeg is available, false otherwise.
     */
    public function is_ffmpeg_available() {
        return $this->is_ffmpeg_binary_available();
    }

    /**
     * Check if PHP-FFmpeg library is available.
     *
     * @since 0.1.0
     * @return bool True if PHP-FFmpeg library is available, false otherwise.
     */
    public function is_php_ffmpeg_available() {
        return class_exists( 'FluxMedia\FFMpeg\FFMpeg' );
    }

    /**
     * Check if FFmpeg binary is available.
     *
     * @since 0.1.0
     * @return bool True if FFmpeg binary is available, false otherwise.
     */
    private function is_ffmpeg_binary_available() {
        $possible_paths = [
            'ffmpeg',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
        ];

        foreach ( $possible_paths as $path ) {
            if ( $this->is_executable( $path ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if binary is executable.
     *
     * @since 0.1.0
     * @param string $path Binary path.
     * @return bool True if executable, false otherwise.
     */
    private function is_executable( $path ) {
        // Check if file exists and is executable
        if ( ! file_exists( $path ) || ! is_executable( $path ) ) {
            return false;
        }
        
        // Use WordPress-compatible method to check if command works
        $output = [];
        $return_var = 0;
        $result = @exec( escapeshellarg( $path ) . ' -version 2>&1', $output, $return_var );
        
        return $return_var === 0;
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
     * @since 0.1.0
     * @return string FFmpeg version or 'Unknown'.
     */
    private function get_ffmpeg_version() {
        $output = [];
        $return_var = 0;
        $result = @exec( 'ffmpeg -version 2>&1', $output, $return_var );
        
        if ( $return_var === 0 && ! empty( $output ) ) {
            $version_output = implode( ' ', $output );
            if ( preg_match( '/ffmpeg version ([^\s]+)/', $version_output, $matches ) ) {
                return $matches[1];
            }
        }
        
        return 'Unknown';
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
     * Check if FFmpeg supports AV1.
     *
     * @since 0.1.0
     * @return bool True if FFmpeg supports AV1, false otherwise.
     */
    private function ffmpeg_supports_av1() {
        if ( ! $this->is_ffmpeg_available() ) {
            return false;
        }

        return $this->ffmpeg_supports_codec( 'libaom-av1' );
    }

    /**
     * Check if FFmpeg supports WebM.
     *
     * @since 0.1.0
     * @return bool True if FFmpeg supports WebM, false otherwise.
     */
    private function ffmpeg_supports_webm() {
        if ( ! $this->is_ffmpeg_available() ) {
            return false;
        }

        return $this->ffmpeg_supports_codec( 'libvpx-vp9' );
    }

    /**
     * Check if FFmpeg supports a specific codec.
     *
     * @since 0.1.0
     * @param string $codec Codec name to check.
     * @return bool True if codec is supported, false otherwise.
     */
    private function ffmpeg_supports_codec( $codec ) {
        $output = [];
        $return_var = 0;
        $result = @exec( 'ffmpeg -encoders 2>&1', $output, $return_var );
        
        if ( $return_var === 0 && ! empty( $output ) ) {
            $encoders_output = implode( ' ', $output );
            return strpos( $encoders_output, $codec ) !== false;
        }

        return false;
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
