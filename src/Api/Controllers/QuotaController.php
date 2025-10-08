<?php
/**
 * Quota REST API controller.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api\Controllers;

use FluxMedia\Services\QuotaManager;
use FluxMedia\Utils\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Quota controller.
 *
 * @since 1.0.0
 */
class QuotaController extends BaseController {

	/**
	 * Register routes for quota endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$namespace = $this->get_namespace();

		// Quota endpoints.
		register_rest_route( $namespace, '/quota/progress', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_quota_progress' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );

		register_rest_route( $namespace, '/quota/plan', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_plan_info' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );
	}

	/**
	 * Get quota progress.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_quota_progress( WP_REST_Request $request ) {
		try {
			$logger = new Logger();
			$quota_manager = new QuotaManager( $logger );
			$progress = $quota_manager->get_quota_progress();

			return $this->create_response( $progress, 'Quota progress retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to retrieve quota progress: ' . $e->getMessage(),
				'quota_progress_error',
				500
			);
		}
	}

	/**
	 * Get plan information.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_plan_info( WP_REST_Request $request ) {
		try {
			$logger = new Logger();
			$quota_manager = new QuotaManager( $logger );
			$plan_info = $quota_manager->get_plan_info();

			return $this->create_response( $plan_info, 'Plan information retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to retrieve plan information: ' . $e->getMessage(),
				'plan_info_error',
				500
			);
		}
	}
}
