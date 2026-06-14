<?php
/**
 * Base REST API controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Base controller with common functionality for all REST API controllers.
 *
 * @since 0.1.0
 */
abstract class BaseController extends WP_REST_Controller {

	/**
	 * Logger instance.
	 *
	 * @since 0.1.0
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Create a standardized error response.
	 *
	 * @since 0.1.0
	 * @param string $message Error message.
	 * @param string $error_code Error code.
	 * @param int    $http_status HTTP status code.
	 * @return WP_REST_Response Error response.
	 */
	protected function create_error_response( $message, $error_code = 'error', $http_status = 500 ) {
		// Log the error
		$this->logger->error( $message, [
			'error_code' => $error_code,
			'http_status' => $http_status,
		] );

		return new WP_REST_Response( [
			'success' => false,
			'message' => $message,
			'error_code' => $error_code,
		], $http_status );
	}

	/**
	 * Log exception details and return a generic error response to the client.
	 *
	 * @since 4.1.6
	 * @param \Exception $exception       Caught exception.
	 * @param string     $client_message  Safe message for REST clients.
	 * @param string     $error_code      Error code.
	 * @param int        $http_status     HTTP status code.
	 * @return WP_REST_Response Error response.
	 */
	protected function create_error_response_from_exception( \Exception $exception, $client_message, $error_code = 'error', $http_status = 500 ) {
		$this->logger->error(
			$client_message . ': ' . $exception->getMessage(),
			[
				'error_code' => $error_code,
				'http_status' => $http_status,
				'exception' => $exception,
			]
		);

		return $this->create_error_response( $client_message, $error_code, $http_status );
	}

	/**
	 * Create a standardized success response.
	 *
	 * @since 0.1.0
	 * @param mixed  $data Response data.
	 * @param string $message Success message.
	 * @param int    $http_status HTTP status code.
	 * @return WP_REST_Response Success response.
	 */
	protected function create_success_response( $data = null, $message = 'Success', $http_status = 200 ) {
		$response = [
			'success' => true,
			'message' => $message,
			'timestamp' => current_time( 'mysql' ),
		];

		if ( $data !== null ) {
			$response['data'] = $data;
		}

		return new WP_REST_Response( $response, $http_status );
	}
}
