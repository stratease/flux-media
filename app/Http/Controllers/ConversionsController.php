<?php
/**
 * Conversions REST API controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\ConversionTracker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles conversion-related REST API endpoints.
 *
 * @since 0.1.0
 */
class ConversionsController extends BaseController {

	/**
	 * Conversion tracker instance.
	 *
	 * @since 0.1.0
	 * @var ConversionTracker
	 */
	private $conversion_tracker;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param ConversionTracker $conversion_tracker Conversion tracker instance.
	 */
	public function __construct( ConversionTracker $conversion_tracker ) {
		$this->conversion_tracker = $conversion_tracker;
		parent::__construct( Logger::get_instance() );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( 'flux-media-optimizer/v1', '/conversions/stats', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_conversion_stats' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );
	}

	/**
	 * Get conversion statistics.
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_conversion_stats( WP_REST_Request $request ) {
		try {
			// Get conversion statistics from database
			$conversion_stats = $this->conversion_tracker->get_conversion_stats();
			$savings_stats = $this->conversion_tracker->get_savings_stats();

			$stats = [
				'total_conversions' => (int) $conversion_stats['total_conversions'],
				'successful_conversions' => (int) $conversion_stats['total_conversions'], // All recorded conversions are successful
				'failed_conversions' => 0, // Failed conversions are not recorded
				'total_original_bytes' => $savings_stats['total_original_bytes'],
				'total_converted_bytes' => $savings_stats['total_converted_bytes'],
				'total_savings_bytes' => $savings_stats['total_savings_bytes'],
				'total_savings_percentage' => $savings_stats['total_savings_percentage'],
				'formats' => [
					'webp' => $conversion_stats['by_type']['webp'] ?? 0,
					'avif' => $conversion_stats['by_type']['avif'] ?? 0,
					'av1' => $conversion_stats['by_type']['av1'] ?? 0,
					'webm' => $conversion_stats['by_type']['webm'] ?? 0,
				],
				'savings_by_type' => $savings_stats['by_type'],
				'recent_savings' => $savings_stats['recent'],
			];

			return $this->create_success_response( $stats, 'Conversion statistics retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response_from_exception(
				$e,
				__( 'Failed to retrieve conversion statistics.', 'flux-media-optimizer' ),
				'conversion_stats_failed'
			);
		}
	}

	/**
	 * Check if user has permission to access conversions.
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if user has permission.
	 */
	public function check_permissions( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}
}
