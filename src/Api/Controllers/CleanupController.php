<?php
/**
 * Cleanup REST API controller.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api\Controllers;

use FluxMedia\Core\Container;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Cleanup controller.
 *
 * @since 1.0.0
 */
class CleanupController extends BaseController {

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
	 * Register routes for cleanup endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$namespace = $this->get_namespace();

		// Cleanup endpoints.
		register_rest_route( $namespace, '/cleanup/temp-files', [
			'methods' => 'POST',
			'callback' => [ $this, 'cleanup_temp_files' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );

		register_rest_route( $namespace, '/cleanup/old-records', [
			'methods' => 'POST',
			'callback' => [ $this, 'cleanup_old_records' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'days' => [
					'description' => 'Number of days to keep records.',
					'type' => 'integer',
					'default' => 30,
					'minimum' => 1,
				],
			],
		] );
	}

	/**
	 * Cleanup temporary files.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function cleanup_temp_files( WP_REST_Request $request ) {
		try {
			// TODO: Implement temp file cleanup.
			$deleted_count = 0;

			return $this->create_response( [ 'deletedCount' => $deleted_count ], 'Temporary files cleaned up successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to cleanup temporary files: ' . $e->getMessage(),
				'cleanup_temp_files_error',
				500
			);
		}
	}

	/**
	 * Cleanup old records.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function cleanup_old_records( WP_REST_Request $request ) {
		try {
			$params = $request->get_json_params();
			$days = (int) ( $params['days'] ?? 30 );

			$conversion_tracker = $this->container->get( 'conversion_tracker' );
			$deleted_count = $conversion_tracker->cleanup_old_records( $days );

			return $this->create_response( [ 'deletedCount' => $deleted_count ], 'Old records cleaned up successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to cleanup old records: ' . $e->getMessage(),
				'cleanup_old_records_error',
				500
			);
		}
	}
}
