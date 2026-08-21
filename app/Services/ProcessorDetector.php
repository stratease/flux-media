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
     * Logger instance.
     *
     * @since 3.0.4
     * @var Logger
     */
    private $logger;

    /**
     * HEIF capability probe.
     *
     * @since 4.3.0
     * @var HeifCapabilityProbe
     */
    private $heif_probe;

    /**
     * Constructor.
     *
     * @since 0.1.0
     * @since 3.0.4 Added Logger instance for detection error logging.
     * @since 4.3.0 Added HEIF capability probe.
     */
    public function __construct() {
        $this->logger = \FluxMedia\FluxPlugins\Common\Logger\Logger::get_instance();
        $this->heif_probe = new HeifCapabilityProbe();
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
                'heic_support' => $this->imagick_supports_heic(),
                'animated_heic_support' => $this->imagick_supports_animated_heic(),
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
                'heic_support' => false,
                'animated_heic_support' => false,
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
     * @since 3.0.4 Added logging for FFmpeg detection failures.
     * @return bool True if FFmpeg is available, false otherwise.
     */
    public function is_ffmpeg_available() {
        // First check if the library is available
        if ( ! $this->is_php_ffmpeg_available() ) {
            $this->logger->info( 'FFmpeg detection: PHP-FFmpeg library not available' );
            return false;
        }

        // Try to create an FFMpeg instance - the library will auto-detect the binary
        try {
            $ffmpeg = \FluxMedia\FFMpeg\FFMpeg::create();
            return $ffmpeg !== null;
        } catch ( \Exception $e ) {
            // FFmpeg is not available or cannot be detected
            $this->logger->info( 'FFmpeg detection: Failed to create FFMpeg instance - ' . $e->getMessage() );
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
     * @since 3.0.4 Added logging for Imagick version detection failures.
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
            $this->logger->info( 'Imagick version detection: Failed to get version - ' . $e->getMessage() );
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
     * @since 3.0.4 Added logging for FFmpeg version detection failures.
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
            $this->logger->info( 'FFmpeg version detection: FFMpeg instance created but returned null' );
            return 'Unknown';
        } catch ( \Exception $e ) {
            $this->logger->info( 'FFmpeg version detection: Failed to get version - ' . $e->getMessage() );
            return 'Unknown';
        }
    }

    /**
     * Check if Imagick supports WebP.
     *
     * @since 0.1.0
     * @since 3.0.4 Added logging for Imagick WebP detection failures.
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
            $this->logger->info( 'Imagick WebP detection: Failed to check WebP support - ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if Imagick supports AVIF.
     *
     * @since 0.1.0
     * @since 3.0.4 Added logging for Imagick AVIF detection failures.
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
            $this->logger->info( 'Imagick AVIF detection: Failed to check AVIF support - ' . $e->getMessage() );
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
     * @since 3.0.4 Added logging for Imagick GIF detection failures.
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
            $this->logger->info( 'Imagick GIF detection: Failed to check GIF support - ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if Imagick supports static HEIC/HEIF decode.
     *
     * @since 4.3.0
     * @return bool True when libheif-backed HEIC decode is available.
     */
    public function imagick_supports_heic() {
        return $this->heif_probe->supports_static_heic();
    }

    /**
     * Check if Imagick supports animated HEIC/HEIF sequence conversion.
     *
     * @since 4.3.0
     * @return bool True when animated HEIC can be converted locally.
     */
    public function imagick_supports_animated_heic() {
        return $this->heif_probe->supports_animated_heic( $this->imagick_supports_webp() );
    }

    /**
     * Check if FFmpeg supports AV1.
     *
     * Uses PHP-FFmpeg library's driver to check if AV1 encoder is available.
     * This uses the library's internal command execution (Symfony Process) which is
     * WordPress-compliant and safer than direct exec() calls.
     *
     * @since 1.0.0
     * @since 3.0.4 Added logging for AV1 detection failures and issues.
     * @return bool True if FFmpeg supports AV1 encoding, false otherwise.
     */
    private function ffmpeg_supports_av1() {
        if ( ! $this->is_ffmpeg_available() ) {
            return false;
        }

        try {
            // Use PHP-FFmpeg library to create FFMpeg instance
            $ffmpeg = \FluxMedia\FFMpeg\FFMpeg::create();
            if ( ! $ffmpeg ) {
                $this->logger->info( 'AV1 detection: Failed to create FFMpeg instance' );
                return false;
            }
            
            // Get the FFMpegDriver from the instance
            $driver = $ffmpeg->getFFMpegDriver();
            if ( ! $driver ) {
                $this->logger->info( 'AV1 detection: Failed to get FFMpegDriver instance' );
                return false;
            }
            
            // Use the driver's command method to check encoders
            // This uses Symfony Process internally, which is WordPress-compliant
            $output = $driver->command( [ '-encoders' ] );
            
            if ( empty( $output ) ) {
                $this->logger->info( 'AV1 detection: FFmpeg encoders command returned empty output' );
                return false;
            }
            
            // Check for AV1 encoders in the output
            $encoders_output = is_array( $output ) ? implode( "\n", $output ) : (string) $output;
            
            // Check for common AV1 encoders
            $has_av1 = (
                strpos( $encoders_output, 'libaom-av1' ) !== false ||
                strpos( $encoders_output, 'libsvtav1' ) !== false ||
                strpos( $encoders_output, 'librav1e' ) !== false ||
                preg_match( '/\bav1\b/i', $encoders_output )
            );
            
            if ( ! $has_av1 ) {
                $this->logger->info( 'AV1 detection: No AV1 encoders found (libaom-av1, libsvtav1, librav1e) in FFmpeg encoder list' );
            }
            
            return $has_av1;
            
        } catch ( \Exception $e ) {
            // If we can't check, assume false for safety
            $this->logger->info( 'AV1 detection: Exception during detection - ' . $e->getMessage() );
            return false;
        } catch ( \Error $e ) {
            // Catch fatal errors
            $this->logger->info( 'AV1 detection: Fatal error during detection - ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if FFmpeg supports WebM.
     *
     * Uses PHP-FFmpeg library's driver to check if WebM codec/encoder is available.
     * This uses the library's internal command execution (Symfony Process) which is
     * WordPress-compliant and safer than direct exec() calls.
     *
     * Improved detection logic checks for actual WebM encoders (libvpx, vp8, vp9)
     * rather than just assuming support if FFmpeg is available, preventing false
     * positives when WebM codecs are not installed.
     *
     * @since 1.0.0
     * @since 3.0.3 Improved WebM detection to check for actual encoder support.
     * @since 3.0.4 Added comprehensive error logging for troubleshooting WebM
     *              detection issues in different server environments.
     * @return bool True if FFmpeg supports WebM encoding, false otherwise.
     */
    private function ffmpeg_supports_webm() {
        if ( ! $this->is_ffmpeg_available() ) {
            return false;
        }

        try {
            // Use PHP-FFmpeg library to create FFMpeg instance
            $ffmpeg = \FluxMedia\FFMpeg\FFMpeg::create();
            if ( ! $ffmpeg ) {
                $this->logger->info( 'WebM detection: Failed to create FFMpeg instance' );
                return false;
            }
            
            // Get the FFMpegDriver from the instance
            $driver = $ffmpeg->getFFMpegDriver();
            if ( ! $driver ) {
                $this->logger->info( 'WebM detection: Failed to get FFMpegDriver instance' );
                return false;
            }
            
            // Use the driver's command method to check encoders
            // This uses Symfony Process internally, which is WordPress-compliant
            $output = $driver->command( [ '-encoders' ] );
            
            if ( empty( $output ) ) {
                $this->logger->info( 'WebM detection: FFmpeg encoders command returned empty output' );
                return false;
            }
            
            // Check for WebM encoders in the output
            $encoders_output = is_array( $output ) ? implode( "\n", $output ) : (string) $output;
            
            // Check for common WebM encoders (libvpx-vp8, libvpx-vp9, libvpx)
            $has_webm_encoder = (
                strpos( $encoders_output, 'libvpx' ) !== false ||
                strpos( $encoders_output, 'libvpx-vp8' ) !== false ||
                strpos( $encoders_output, 'libvpx-vp9' ) !== false ||
                preg_match( '/\bvp8\b/i', $encoders_output ) ||
                preg_match( '/\bvp9\b/i', $encoders_output )
            );
            
            if ( ! $has_webm_encoder ) {
                $this->logger->info( 'WebM detection: No WebM encoders found (libvpx, vp8, vp9) in FFmpeg encoder list' );
            }
            
            // Also check for WebM muxer/format support
            // If muxer check fails, we'll still rely on encoder check
            $has_webm_muxer = false;
            try {
                $muxers_output = $driver->command( [ '-muxers' ] );
                if ( ! empty( $muxers_output ) ) {
                    $muxers_string = is_array( $muxers_output ) ? implode( "\n", $muxers_output ) : (string) $muxers_output;
                    $has_webm_muxer = (
                        strpos( $muxers_string, 'webm' ) !== false || 
                        preg_match( '/\bwebm\b/i', $muxers_string )
                    );
                } else {
                    $this->logger->info( 'WebM detection: FFmpeg muxers command returned empty output' );
                }
            } catch ( \Exception $e ) {
                // If muxer check fails, we'll rely on encoder check only
                // This is acceptable as encoder presence is the primary indicator
                $this->logger->info( 'WebM detection: Failed to check muxers - ' . $e->getMessage() );
            }
            
            // WebM support requires encoder (muxer check is secondary)
            // If we have encoder support, we consider WebM supported
            // The muxer check is a bonus validation but not strictly required
            if ( $has_webm_encoder ) {
                $this->logger->info( 'WebM detection: WebM encoder support detected' . ( $has_webm_muxer ? ' (muxer also available)' : ' (muxer check failed or unavailable)' ) );
            }
            
            return $has_webm_encoder;
            
        } catch ( \Exception $e ) {
            // If we can't check, assume false for safety
            $this->logger->info( 'WebM detection: Exception during detection - ' . $e->getMessage() );
            return false;
        } catch ( \Error $e ) {
            // Catch fatal errors
            $this->logger->info( 'WebM detection: Fatal error during detection - ' . $e->getMessage() );
            return false;
        }
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
