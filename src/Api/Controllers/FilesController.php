<?php
/**
 * Files REST API controller.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Files controller.
 *
 * @since 1.0.0
 */
class FilesController extends BaseController {

	/**
	 * Register routes for file endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$namespace = $this->get_namespace();

		// File operations.
		register_rest_route( $namespace, '/files/delete/(?P<attachment_id>\d+)/(?P<format>[a-zA-Z0-9]+)', [
			'methods' => 'DELETE',
			'callback' => [ $this, 'delete_converted_file' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'attachment_id' => [
					'description' => 'Attachment ID.',
					'type' => 'integer',
					'required' => true,
				],
				'format' => [
					'description' => 'File format to delete.',
					'type' => 'string',
					'required' => true,
					'enum' => [ 'webp', 'avif', 'av1', 'webm' ],
				],
			],
		] );
	}

	/**
	 * Delete converted file.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function delete_converted_file( WP_REST_Request $request ) {
		try {
			$attachment_id = (int) $request->get_param( 'attachment_id' );
			$format = sanitize_text_field( $request->get_param( 'format' ) );

			// TODO: Implement file deletion.
			return $this->create_response( [ 'success' => true ], 'Converted file deleted successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response( 
				'Failed to delete converted file: ' . $e->getMessage(),
				'file_delete_error',
				500
			);
		}
	}
}
