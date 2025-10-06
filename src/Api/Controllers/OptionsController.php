<?php
/**
 * Options REST API controller.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Options controller.
 *
 * @since 1.0.0
 */
class OptionsController extends BaseController {

	/**
	 * Register routes for options endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$namespace = $this->get_namespace();

		// Plugin options endpoints.
		register_rest_route( $namespace, '/options', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_options' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );

		register_rest_route( $namespace, '/options', [
			'methods' => 'POST',
			'callback' => [ $this, 'update_options' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'options' => [
					'description' => 'Options to update.',
					'type' => 'object',
					'required' => true,
				],
			],
		] );
	}

	/**
	 * Get plugin options.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_options( WP_REST_Request $request ) {
		try {
			$options = \FluxMedia\Core\Options::get_all();
			return $this->create_response( $options, 'Options retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to retrieve options: ' . $e->getMessage(),
				'options_retrieve_error',
				500
			);
		}
	}

	/**
	 * Update plugin options.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function update_options( WP_REST_Request $request ) {
		try {
			$options = $request->get_json_params();

			// Log the received options for debugging
			error_log( 'Flux Media: Received options: ' . print_r( $options, true ) );

			if ( empty( $options ) ) {
				return $this->create_error_response( 'No options provided', 'invalid_options', 400 );
			}

			// Validate and sanitize options
			$sanitized_options = $this->sanitize_options( $options );

			$result = \FluxMedia\Core\Options::update( $sanitized_options );

			if ( $result ) {
				return $this->create_response( [ 'success' => true ], 'Options updated successfully' );
			} else {
				return $this->create_error_response( 'Failed to update options', 'update_failed', 500 );
			}
		} catch ( \Exception $e ) {
			error_log( 'Flux Media: Error updating options: ' . $e->getMessage() );
			return $this->create_error_response( 
				'Failed to update options: ' . $e->getMessage(),
				'update_failed',
				500
			);
		}
	}

	/**
	 * Sanitize options data.
	 *
	 * @since 1.0.0
	 * @param array $options Raw options data.
	 * @return array Sanitized options data.
	 */
	private function sanitize_options( $options ) {
		$sanitized = [];

		// Define allowed options and their sanitization methods
		$allowed_options = [
			'autoConvert' => 'boolval',
			'quality' => 'intval',
			'webpEnabled' => 'boolval',
			'avifEnabled' => 'boolval',
			'hybridApproach' => 'boolval',
			'av1Enabled' => 'boolval',
			'webmEnabled' => 'boolval',
			'licenseKey' => 'sanitize_text_field',
			'image_webp_quality' => 'intval',
			'image_webp_lossless' => 'boolval',
			'image_avif_quality' => 'intval',
			'image_avif_speed' => 'intval',
			'image_auto_convert' => 'boolval',
			'image_formats' => 'array',
			'hybrid_approach' => 'boolval',
			'video_av1_crf' => 'intval',
			'video_av1_preset' => 'sanitize_text_field',
			'video_webm_crf' => 'intval',
			'video_webm_preset' => 'sanitize_text_field',
			'video_auto_convert' => 'boolval',
			'video_formats' => 'array',
			'async_processing' => 'boolval',
			'cleanup_temp_files' => 'boolval',
			'log_level' => 'sanitize_text_field',
			'max_file_size' => 'intval',
			'conversion_timeout' => 'intval',
			'license_key' => 'sanitize_text_field',
			'license_status' => 'sanitize_text_field',
			'cdn_enabled' => 'boolval',
			'cdn_provider' => 'sanitize_text_field',
			'cdn_api_key' => 'sanitize_text_field',
			'cdn_endpoint' => 'sanitize_text_field',
			'external_conversion_enabled' => 'boolval',
			'external_conversion_provider' => 'sanitize_text_field',
			'external_conversion_api_key' => 'sanitize_text_field',
			'external_conversion_endpoint' => 'sanitize_text_field',
			'enable_logging' => 'boolval',
		];

		foreach ( $options as $key => $value ) {
			if ( ! isset( $allowed_options[ $key ] ) ) {
				continue; // Skip unknown options
			}

			$sanitizer = $allowed_options[ $key ];

			switch ( $sanitizer ) {
				case 'boolval':
					$sanitized[ $key ] = (bool) $value;
					break;
				case 'intval':
					$sanitized[ $key ] = (int) $value;
					break;
				case 'array':
					$sanitized[ $key ] = is_array( $value ) ? $value : [];
					break;
				case 'sanitize_text_field':
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
				default:
					$sanitized[ $key ] = $value;
					break;
			}
		}

		return $sanitized;
	}
}
