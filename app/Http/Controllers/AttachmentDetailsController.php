<?php
/**
 * Attachment details REST API controller.
 *
 * @package FluxMedia\App\Http\Controllers
 * @since 4.3.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\App\Services\AttachmentDetailsPresenter;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Serves async attachment optimization panel payloads.
 *
 * @since 4.3.0
 */
class AttachmentDetailsController extends BaseController {

	/**
	 * Attachment details presenter.
	 *
	 * @since 4.3.0
	 * @var AttachmentDetailsPresenter
	 */
	private $presenter;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param AttachmentDetailsPresenter $presenter Presenter instance.
	 */
	public function __construct( AttachmentDetailsPresenter $presenter ) {
		$this->presenter = $presenter;
		parent::__construct( Logger::get_instance() );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'flux-media-optimizer/v1',
			'/attachments/(?P<id>\d+)/details',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_details' ],
					'permission_callback' => [ $this, 'check_permissions' ],
					'args'                => [
						'id' => [
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);
	}

	/**
	 * Get attachment details payload.
	 *
	 * @since 4.3.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_details( WP_REST_Request $request ) {
		$attachment_id = (int) $request->get_param( 'id' );
		$post          = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error(
				'attachment_not_found',
				__( 'Attachment not found.', 'flux-media-optimizer' ),
				[ 'status' => 404 ]
			);
		}

		try {
			$payload = $this->presenter->present( $attachment_id );
			return $this->create_success_response( $payload, 'Attachment details retrieved successfully' );
		} catch ( \Exception $e ) {
			return $this->create_error_response_from_exception(
				$e,
				__( 'Failed to retrieve attachment details.', 'flux-media-optimizer' ),
				'attachment_details_failed'
			);
		}
	}

	/**
	 * Check whether the current user may view attachment optimization details.
	 *
	 * Uses WordPress meta capability for attachment edits (editors and above).
	 *
	 * @since 4.3.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_permissions( WP_REST_Request $request ) {
		$attachment_id = (int) $request->get_param( 'id' );
		if ( $attachment_id <= 0 ) {
			return new WP_Error(
				'invalid_attachment',
				__( 'Invalid attachment ID.', 'flux-media-optimizer' ),
				[ 'status' => 400 ]
			);
		}

		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error(
				'attachment_not_found',
				__( 'Attachment not found.', 'flux-media-optimizer' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to edit this attachment.', 'flux-media-optimizer' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
	}
}
