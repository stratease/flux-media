<?php
/**
 * System status REST API controller.
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
 * System status controller.
 *
 * @since 1.0.0
 */
class SystemController extends BaseController {

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
	 * Register routes for system endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$namespace = $this->get_namespace();

		// Test endpoint to verify API is working.
		register_rest_route( $namespace, '/test', [
			'methods' => 'GET',
			'callback' => [ $this, 'test_endpoint' ],
			'permission_callback' => '__return_true',
		] );

		// System status endpoint.
		register_rest_route( $namespace, '/system/status', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_system_status' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );
	}

	/**
	 * Test endpoint to verify API is working.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function test_endpoint( WP_REST_Request $request ) {
		return $this->create_response( [
			'message' => 'Flux Media API is working!',
			'timestamp' => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
		], 'Test endpoint successful' );
	}

	/**
	 * Get system status.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_system_status( WP_REST_Request $request ) {
		try {
			$image_converter = $this->container->get( 'image_converter' );
			$video_converter = $this->container->get( 'video_converter' );

			$status = [
				'imageProcessor' => $image_converter->get_processor_info(),
				'videoProcessor' => $video_converter->get_processor_info(),
				'phpVersion' => PHP_VERSION,
				'memoryLimit' => ini_get( 'memory_limit' ),
				'maxExecutionTime' => (int) ini_get( 'max_execution_time' ),
				'uploadMaxFilesize' => ini_get( 'upload_max_filesize' ),
				'postMaxSize' => ini_get( 'post_max_size' ),
			];

			return $this->create_response( $status, 'System status retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to retrieve system status: ' . $e->getMessage(),
				'system_status_error',
				500
			);
		}
	}
}
