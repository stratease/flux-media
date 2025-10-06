<?php
/**
 * Conversions REST API controller.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api\Controllers;

use FluxMedia\Core\Container;
use FluxMedia\Services\ConversionTracker;
use FluxMedia\Services\QuotaManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Conversions controller.
 *
 * @since 1.0.0
 */
class ConversionsController extends BaseController {

	/**
	 * Container instance.
	 *
	 * @since 1.0.0
	 * @var Container
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Container $container Container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register routes for conversion endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$namespace = $this->get_namespace();

		// Conversion statistics endpoints.
		register_rest_route( $namespace, '/conversions/stats', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_conversion_stats' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'format' => [
					'description' => 'Filter by format.',
					'type' => 'string',
				],
				'status' => [
					'description' => 'Filter by status.',
					'type' => 'string',
				],
				'dateFrom' => [
					'description' => 'Filter from date.',
					'type' => 'string',
					'format' => 'date',
				],
				'dateTo' => [
					'description' => 'Filter to date.',
					'type' => 'string',
					'format' => 'date',
				],
			],
		] );

		register_rest_route( $namespace, '/conversions/recent', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_recent_conversions' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'limit' => [
					'description' => 'Maximum number of recent conversions to return.',
					'type' => 'integer',
					'default' => 10,
					'minimum' => 1,
					'maximum' => 100,
				],
			],
		] );

		// Conversion job endpoints.
		register_rest_route( $namespace, '/conversions/start', [
			'methods' => 'POST',
			'callback' => [ $this, 'start_conversion' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'attachmentId' => [
					'description' => 'Attachment ID to convert.',
					'type' => 'integer',
					'required' => true,
				],
				'format' => [
					'description' => 'Target format for conversion.',
					'type' => 'string',
					'required' => true,
					'enum' => [ 'webp', 'avif', 'av1', 'webm' ],
				],
			],
		] );

		register_rest_route( $namespace, '/conversions/cancel/(?P<job_id>[a-zA-Z0-9-]+)', [
			'methods' => 'POST',
			'callback' => [ $this, 'cancel_conversion' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'job_id' => [
					'description' => 'Job ID to cancel.',
					'type' => 'string',
					'required' => true,
				],
			],
		] );

		// Bulk operations.
		register_rest_route( $namespace, '/conversions/bulk', [
			'methods' => 'POST',
			'callback' => [ $this, 'bulk_convert' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'formats' => [
					'description' => 'Formats to convert to.',
					'type' => 'array',
					'required' => true,
				],
			],
		] );
	}

	/**
	 * Get conversion statistics.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_conversion_stats( WP_REST_Request $request ) {
		try {
			$conversion_tracker = $this->container->get( 'conversion_tracker' );

			$filters = [
				'format' => $request->get_param( 'format' ),
				'status' => $request->get_param( 'status' ),
				'dateFrom' => $request->get_param( 'dateFrom' ),
				'dateTo' => $request->get_param( 'dateTo' ),
			];

			// Remove empty filters.
			$filters = array_filter( $filters );

			$stats = $conversion_tracker->get_statistics( $filters );

			return $this->create_response( $stats, 'Conversion statistics retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to retrieve conversion statistics: ' . $e->getMessage(),
				'conversion_stats_error',
				500
			);
		}
	}

	/**
	 * Get recent conversions.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_recent_conversions( WP_REST_Request $request ) {
		try {
			$conversion_tracker = $this->container->get( 'conversion_tracker' );
			$limit = $request->get_param( 'limit' );

			$conversions = $conversion_tracker->get_recent_conversions( $limit );

			// Convert to array format for JSON response.
			$conversions_array = array_map( function( $conversion ) {
				return [
					'id' => $conversion->id,
					'attachmentId' => $conversion->attachment_id,
					'originalPath' => $conversion->original_path,
					'convertedPath' => $conversion->converted_path,
					'format' => $conversion->format,
					'status' => $conversion->status,
					'sizeReduction' => $conversion->size_reduction,
					'processingTime' => $conversion->processing_time,
					'errorMessage' => $conversion->error_message,
					'createdAt' => $conversion->created_at,
				];
			}, $conversions );

			return $this->create_response( $conversions_array, 'Recent conversions retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to retrieve recent conversions: ' . $e->getMessage(),
				'recent_conversions_error',
				500
			);
		}
	}

	/**
	 * Start conversion job.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function start_conversion( WP_REST_Request $request ) {
		try {
			$params = $request->get_json_params();
			$attachment_id = (int) $params['attachmentId'];
			$format = sanitize_text_field( $params['format'] );

			// Check quota before starting conversion.
			$quota_manager = new QuotaManager();
			$media_type = in_array( $format, [ 'webp', 'avif' ], true ) ? 'image' : 'video';

			if ( ! $quota_manager->can_convert( $media_type ) ) {
				return $this->create_error_response( 
					'Monthly quota exceeded for ' . $media_type . ' conversions',
					'quota_exceeded',
					429
				);
			}

			// TODO: Implement actual conversion job creation.
			$job = [
				'id' => wp_generate_uuid4(),
				'attachmentId' => $attachment_id,
				'format' => $format,
				'status' => 'pending',
				'progress' => 0,
				'createdAt' => current_time( 'mysql' ),
			];

			return $this->create_response( $job, 'Conversion job started successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to start conversion: ' . $e->getMessage(),
				'conversion_start_error',
				500
			);
		}
	}

	/**
	 * Cancel conversion job.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function cancel_conversion( WP_REST_Request $request ) {
		try {
			$job_id = $request->get_param( 'job_id' );

			// TODO: Implement actual job cancellation.
			return $this->create_response( [ 'success' => true ], 'Conversion job cancelled successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to cancel conversion: ' . $e->getMessage(),
				'conversion_cancel_error',
				500
			);
		}
	}

	/**
	 * Bulk convert media.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function bulk_convert( WP_REST_Request $request ) {
		try {
			$params = $request->get_json_params();
			$formats = $params['formats'] ?? [];

			// TODO: Implement bulk conversion.
			$job_id = wp_generate_uuid4();

			return $this->create_response( [ 'jobId' => $job_id ], 'Bulk conversion job started successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to start bulk conversion: ' . $e->getMessage(),
				'bulk_conversion_error',
				500
			);
		}
	}
}
