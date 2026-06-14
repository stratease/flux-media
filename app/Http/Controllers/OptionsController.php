<?php
/**
 * Options REST API controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\Settings;
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
		parent::__construct( Logger::get_instance() );
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

		// License routes are now handled by flux-plugins-common/v1/license endpoints
		// Removed duplicate routes to avoid conflicts
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
			return $this->create_error_response_from_exception(
				$e,
				__( 'Failed to retrieve options.', 'flux-media-optimizer' ),
				'options_retrieval_failed'
			);
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
			return $this->create_error_response_from_exception(
				$e,
				__( 'Failed to update options.', 'flux-media-optimizer' ),
				'options_update_failed'
			);
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
