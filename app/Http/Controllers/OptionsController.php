<?php
/**
 * Options REST API controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\ExternalApiClient;
use FluxMedia\App\Services\LicenseValidationCache;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles options/settings REST API endpoints.
 *
 * @since 0.1.0
 */
class OptionsController extends BaseController {

	/**
	 * Settings instance.
	 *
	 * @since 0.1.0
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		parent::__construct( new \FluxMedia\App\Services\Logger() );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 0.1.0
	 * @since 2.0.5 Added sanitization and validation callbacks.
	 * @since 3.0.0 Added separate license activation and validation endpoints.
	 */
	public function register_routes() {
		register_rest_route( 'flux-media-optimizer/v1', '/options', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_options' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
			[
				'methods' => 'POST',
				'callback' => [ $this, 'update_options' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args' => [
					'options' => [
						'required' => true,
						'type' => 'object',
						'description' => 'Options to update',
						'sanitize_callback' => [ $this, 'sanitize_options' ],
						'validate_callback' => [ $this, 'validate_options' ],
					],
				],
			],
		] );

		register_rest_route( 'flux-media-optimizer/v1', '/license', [
			[
				'methods' => 'GET',
				'callback' => [ $this, 'get_license' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );

		register_rest_route( 'flux-media-optimizer/v1', '/license/activate', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'activate_license' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args' => [
					'license_key' => [
						'required' => true,
						'type' => 'string',
						'description' => 'License key to activate',
					],
				],
			],
		] );

		register_rest_route( 'flux-media-optimizer/v1', '/license/validate', [
			[
				'methods' => 'POST',
				'callback' => [ $this, 'validate_license' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			],
		] );
	}

	/**
	 * Sanitize options before validation.
	 *
	 * @since 2.0.5
	 * @param array           $options Options array.
	 * @param WP_REST_Request $request Request object.
	 * @return array Sanitized options array.
	 */
	public function sanitize_options( $options, $request ) {
		// Settings::update() will handle sanitization, but we ensure it's an array
		if ( ! is_array( $options ) ) {
			return [];
		}
		return $options;
	}

	/**
	 * Validate options structure.
	 *
	 * @since 2.0.5
	 * @param array           $options Options array.
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_options( $options, $request ) {
		if ( ! is_array( $options ) ) {
			return new \WP_Error(
				'invalid_options',
				__( 'Options must be an object/array.', 'flux-media-optimizer' ),
				[ 'status' => 400 ]
			);
		}

		// Additional validation can be added here if needed
		// Settings::update() already validates known keys and sanitizes values

		return true;
	}

	/**
	 * Get all options.
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_options( WP_REST_Request $request ) {
		try {
			$options = $this->settings->get_all();
			// License fields are handled by separate /license endpoint.
			return $this->create_success_response( $options, 'Options retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to retrieve options: ' . $e->getMessage() );
		}
	}

	/**
	 * Update options.
	 *
	 * @since 0.1.0
	 * @since 3.0.0 Removed license handling - now handled by separate endpoints.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_options( WP_REST_Request $request ) {
		try {
			$options = $request->get_param( 'options' );
			
			if ( ! is_array( $options ) ) {
				return $this->create_error_response( 'Invalid options format', 'invalid_options', 400 );
			}

			// Remove license_key if present - it should be handled via separate endpoint.
			unset( $options['license_key'] );

			// Update options.
			if ( ! empty( $options ) ) {
				$this->settings->update( $options );
			}

			// Get updated options (license fields are handled by separate /license endpoint).
			$updated_options = $this->settings->get_all();
			
			return $this->create_success_response( $updated_options, 'Options updated successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to update options: ' . $e->getMessage() );
		}
	}

	/**
	 * Get license information.
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_license( WP_REST_Request $request ) {
		try {
			$license_data = [
				'license_key' => Settings::get_license_key(),
				'license_last_valid_date' => Settings::get_license_last_valid_date(),
				'license_is_valid' => Settings::is_license_valid(),
			];
			
			return $this->create_success_response( $license_data, 'License information retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to retrieve license information: ' . $e->getMessage() );
		}
	}

	/**
	 * Activate license key.
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function activate_license( WP_REST_Request $request ) {
		try {
			$license_key = $request->get_param( 'license_key' );
			
			if ( empty( $license_key ) ) {
				return $this->create_error_response( 'License key is required', 'license_key_required', 400 );
			}

			$api_client = new ExternalApiClient( $this->logger );
			$activation_result = $api_client->activate_license( $license_key );

			// Save the license key regardless of activation result.
			Settings::set_license_key( $license_key );

			// Format error message based on debug mode if activation failed.
			if ( ! $activation_result['success'] ) {
				// Clear validation date on failure.
				Settings::set_license_last_valid_date( null );
				$is_debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
				
				$error_code = $activation_result['error'] ?? 'unknown_error';
				$error_message = $activation_result['message'] ?? 'License activation failed';
				$status_code = $activation_result['status_code'] ?? null;
				
				if ( $is_debug ) {
					// Debug mode: Include detailed error information with status code and full response
					$response_details = wp_json_encode( $activation_result, JSON_PRETTY_PRINT );
					if ( $status_code ) {
						$formatted_message = sprintf(
							'%s %s error: %s. Response: %s',
							$status_code,
							$error_code,
							$error_message,
							$response_details
						);
					} else {
						$formatted_message = sprintf(
							'%s error: %s. Response: %s',
							$error_code,
							$error_message,
							$response_details
						);
					}
				} else {
					// Non-debug mode: Simple user-friendly message
					$user_messages = [
						'network_error' => __( 'Network error', 'flux-media-optimizer' ),
						'license_invalid' => __( 'Invalid license key', 'flux-media-optimizer' ),
						'license_expired' => __( 'License has expired', 'flux-media-optimizer' ),
						'license_inactive' => __( 'License is inactive', 'flux-media-optimizer' ),
						'activation_failed' => __( 'License activation failed', 'flux-media-optimizer' ),
						'validation_failed' => __( 'Invalid request', 'flux-media-optimizer' ),
						'account_id_required' => __( 'Account ID not found', 'flux-media-optimizer' ),
						'internal_error' => __( 'Server error', 'flux-media-optimizer' ),
						'unknown_error' => __( 'Unknown error', 'flux-media-optimizer' ),
					];
					
					$formatted_message = $user_messages[ $error_code ] ?? __( 'License activation failed', 'flux-media-optimizer' );
				}
				
				// Return error response with formatted message
				// Use 400 status code for client errors (license issues)
				$http_status = in_array( $error_code, [ 'license_invalid', 'license_expired', 'license_inactive', 'validation_failed' ], true ) ? 400 : 500;
				
				return $this->create_error_response( 
					substr( $formatted_message, 0, 600 ),
					$error_code,
					$http_status
				);
			}

			// Activation successful (status 200) - mark as valid and save validation date.
			// A successful activation means the license is valid, regardless of the 'valid' field in the response.
			Settings::set_license_last_valid_date( current_time( 'mysql', true ) );

			$response_data = [
				'license_key' => Settings::get_license_key(),
				'license_last_valid_date' => Settings::get_license_last_valid_date(),
				'license_is_valid' => Settings::is_license_valid(),
			];

			return $this->create_success_response( $response_data, 'License activated successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to activate license: ' . $e->getMessage() );
		}
	}

	/**
	 * Validate license key.
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function validate_license( WP_REST_Request $request ) {
		try {
			$license_key = Settings::get_license_key();
			
			if ( empty( $license_key ) ) {
				return $this->create_error_response( 'No license key found', 'license_key_not_found', 400 );
			}

			// Use LicenseValidationCache to validate and update cache.
			// This will handle validation and date updates internally.
			$license_cache = new LicenseValidationCache( $this->logger );


			$response_data = [
				'license_key' => Settings::get_license_key(),
				'license_last_valid_date' => Settings::get_license_last_valid_date(),
				'license_is_valid' => $license_cache->is_license_valid(),
			];

			return $this->create_success_response( $response_data, 'License validation completed' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 'Failed to validate license: ' . $e->getMessage() );
		}
	}

	/**
	 * Check if user has permission to access options.
	 *
	 * @since 0.1.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if user has permission.
	 */
	public function check_permissions( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}
}
