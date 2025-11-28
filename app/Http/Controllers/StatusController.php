<?php
/**
 * Status REST API controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\App\Services\FormatSupportDetector;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\App\Services\ProcessorTypes;

use FluxMedia\App\Services\Converter;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles system status REST API endpoints.
 *
 * @since 0.1.0
 */
class StatusController extends BaseController {

	/**
	 * Format support detector instance.
	 *
	 * @since 0.1.0
	 * @var FormatSupportDetector
	 */
	private $format_detector;

	/**
	 * Processor detector instance.
	 *
	 * @since 0.1.0
	 * @var ProcessorDetector
	 */
	private $processor_detector;

	/**

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param FormatSupportDetector $format_detector Format support detector.
	 * @param ProcessorDetector     $processor_detector Processor detector.
	
	 */
	public function __construct( FormatSupportDetector $format_detector, ProcessorDetector $processor_detector ) {
		$this->format_detector = $format_detector;
		$this->processor_detector = $processor_detector;
		parent::__construct( new \FluxMedia\App\Services\Logger() );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( 'flux-media-optimizer/v1', '/status', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

	}

	/**
	 * Get system status.
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_status( WP_REST_Request $request ) {
		try {
			$status = [
				'imageProcessor' => $this->get_image_processor_status(),
				'videoProcessor' => $this->get_video_processor_status(),
				'phpVersion' => PHP_VERSION,
				'memoryLimit' => ini_get( 'memory_limit' ),
				'maxExecutionTime' => ini_get( 'max_execution_time' ),
				'uploadMaxFilesize' => ini_get( 'upload_max_filesize' ),
				'postMaxSize' => ini_get( 'post_max_size' ),
			];

			return $this->create_success_response( $status, 'System status retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to retrieve system status: ' . $e->getMessage() );
		}
	}

	/**
	 * Get image processor status.
	 *
	 * @since 0.1.0
	 * @return array Image processor status.
	 */
	private function get_image_processor_status() {
		$available_processors = $this->processor_detector->get_available_image_processors();
		$format_support_info = $this->format_detector->get_format_support_info();

		// Build detailed processor information
		$processors = [];
		foreach ( $available_processors as $type => $processor_info ) {
			$processors[ $type ] = [
				'available' => $processor_info['available'],
				'type' => $processor_info['type'],
				'version' => $processor_info['version'],
				'webp_support' => $processor_info['webp_support'] ?? false,
				'avif_support' => $processor_info['avif_support'] ?? false,
				'animated_gif_support' => $processor_info['animated_gif_support'] ?? false,
			];
		}
		
		// Determine which processor handles each format (best available)
		$format_processors = [
			Converter::FORMAT_WEBP => $this->get_best_processor_for_format( Converter::FORMAT_WEBP, $available_processors ),
			Converter::FORMAT_AVIF => $this->get_best_processor_for_format( Converter::FORMAT_AVIF, $available_processors ),
		];
		
		return [
			'available' => ! empty( $available_processors ),
			'webp_support' => $format_support_info[ Converter::FORMAT_WEBP ]['supported'],
			'avif_support' => $format_support_info[ Converter::FORMAT_AVIF ]['supported'],
			'processors' => $processors,
			'format_processors' => $format_processors,
			'format_support_details' => $format_support_info,
		];
	}

	/**
	 * Get video processor status.
	 *
	 * @since 0.1.0
	 * @return array Video processor status.
	 */
	private function get_video_processor_status() {
		$available_processors = $this->processor_detector->get_available_video_processors();
		$format_support_info = $this->format_detector->get_format_support_info();
		
		// Build detailed processor information
		$processors = [];
		foreach ( $available_processors as $type => $processor_info ) {
			$processors[ $type ] = [
				'available' => $processor_info['available'],
				'type' => $processor_info['type'],
				'version' => $processor_info['version'],
				'av1_support' => $processor_info['av1_support'] ?? false,
				'webm_support' => $processor_info['webm_support'] ?? false,
			];
		}
		
		// Determine which processor handles each format (best available)
		$format_processors = [
			Converter::FORMAT_AV1 => $this->get_best_video_processor_for_format( Converter::FORMAT_AV1, $available_processors ),
			Converter::FORMAT_WEBM => $this->get_best_video_processor_for_format( Converter::FORMAT_WEBM, $available_processors ),
		];
		
		return [
			'available' => ! empty( $available_processors ),
			'av1_support' => $format_support_info[ Converter::FORMAT_AV1 ]['supported'],
			'webm_support' => $format_support_info[ Converter::FORMAT_WEBM ]['supported'],
			'processors' => $processors,
			'format_processors' => $format_processors,
			'format_support_details' => [
				Converter::FORMAT_AV1 => $format_support_info[ Converter::FORMAT_AV1 ],
				Converter::FORMAT_WEBM => $format_support_info[ Converter::FORMAT_WEBM ],
			],
		];
	}

	/**
	 * Get the best processor for a specific format.
	 *
	 * @since 0.1.0
	 * @param string $format Target format constant.
	 * @param array  $available_processors Available processors.
	 * @return string|null Best processor type or null if none available.
	 */
	private function get_best_processor_for_format( $format, $available_processors ) {
		// Prefer Imagick for better quality and more features
		if ( isset( $available_processors[ ProcessorTypes::IMAGE_IMAGICK ] ) ) {
			$processor_info = $available_processors[ ProcessorTypes::IMAGE_IMAGICK ];
			
			if ( Converter::FORMAT_WEBP === $format && ( $processor_info['webp_support'] ?? false ) ) {
				return ProcessorTypes::IMAGE_IMAGICK;
			}
			if ( Converter::FORMAT_AVIF === $format && ( $processor_info['avif_support'] ?? false ) ) {
				return ProcessorTypes::IMAGE_IMAGICK;
			}
		}
		
		// Fallback to GD
		if ( isset( $available_processors[ ProcessorTypes::IMAGE_GD ] ) ) {
			$processor_info = $available_processors[ ProcessorTypes::IMAGE_GD ];
			
			if ( Converter::FORMAT_WEBP === $format && ( $processor_info['webp_support'] ?? false ) ) {
				return ProcessorTypes::IMAGE_GD;
			}
			if ( Converter::FORMAT_AVIF === $format && ( $processor_info['avif_support'] ?? false ) ) {
				return ProcessorTypes::IMAGE_GD;
			}
		}

		return null;
	}

	/**
	 * Get the best video processor for a specific format.
	 *
	 * @since 0.1.0
	 * @param string $format Target format constant.
	 * @param array  $available_processors Available processors.
	 * @return string|null Best processor type or null if none available.
	 */
	private function get_best_video_processor_for_format( $format, $available_processors ) {
		// FFmpeg is the only video processor we support
		if ( isset( $available_processors[ ProcessorTypes::VIDEO_FFMPEG ] ) ) {
			$processor_info = $available_processors[ ProcessorTypes::VIDEO_FFMPEG ];
			
			if ( Converter::FORMAT_AV1 === $format && ( $processor_info['av1_support'] ?? false ) ) {
				return ProcessorTypes::VIDEO_FFMPEG;
			}
			if ( Converter::FORMAT_WEBM === $format && ( $processor_info['webm_support'] ?? false ) ) {
				return ProcessorTypes::VIDEO_FFMPEG;
			}
		}

		return null;
	}

	/**
	 * Check if user has permission to access status.
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if user has permission.
	 */
	public function check_permissions( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}
}
