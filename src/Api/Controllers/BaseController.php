<?php
/**
 * Base controller for REST API endpoints.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Base controller class with common functionality.
 *
 * @since 1.0.0
 */
abstract class BaseController {

	/**
	 * Namespace for the REST API routes.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'flux-media/v1';

	/**
	 * Check admin permission.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if user has admin permission, false otherwise.
	 */
	public function check_admin_permission( $request ) {
		// Check if user can manage options
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Verify nonce for authenticated requests
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( $nonce && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Create a consistent API response.
	 *
	 * @since 1.0.0
	 * @param mixed  $data Response data.
	 * @param string $message Response message.
	 * @param int    $status HTTP status code.
	 * @return WP_REST_Response Response object.
	 */
	protected function create_response( $data, $message = 'Success', $status = 200 ) {
		$response = [
			'success' => $status >= 200 && $status < 300,
			'data' => $data,
			'message' => $message,
			'timestamp' => current_time( 'mysql' ),
		];

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Create an error response.
	 *
	 * @since 1.0.0
	 * @param string $message Error message.
	 * @param string $code Error code.
	 * @param int    $status HTTP status code.
	 * @return WP_REST_Response Response object.
	 */
	protected function create_error_response( $message, $code = 'error', $status = 400 ) {
		$response = [
			'success' => false,
			'data' => null,
			'message' => $message,
			'code' => $code,
			'timestamp' => current_time( 'mysql' ),
		];

		return new WP_REST_Response( $response, $status );
	}

	/**
	 * Register routes for this controller.
	 *
	 * @since 1.0.0
	 */
	abstract public function register_routes();

	/**
	 * Get the namespace for this controller.
	 *
	 * @since 1.0.0
	 * @return string The namespace.
	 */
	protected function get_namespace() {
		return $this->namespace;
	}
}
