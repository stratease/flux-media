<?php
/**
 * Webhook REST API controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\WebhookAuthService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Handles webhook endpoints for external service callbacks.
 *
 * @since 3.0.0
 */
class WebhookController extends BaseController {

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 */
	public function __construct() {
		parent::__construct( Logger::get_instance() );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 3.0.0
	 */
	public function register_routes() {
		register_rest_route( 'flux-media-optimizer/v1', '/webhook', [
			'methods' => 'POST',
			'callback' => [ $this, 'handle_webhook' ],
			'permission_callback' => [ WebhookAuthService::class, 'verify_request' ],
		] );
	}

	/**
	 * Handle webhook callback from external service.
	 *
	 * Expected request format:
	 * {
	 *   "account_id": "uuid",
	 *   "attachment_id": "12345",
	 *   "cdn_urls": {
	 *     "full": {
	 *       "original": { "url": "...", "filesize": 123 },
	 *       "webp": { "url": "...", "filesize": 456 }
	 *     },
	 *     "thumbnail": {
	 *       "webp": { "url": "...", "filesize": 789 }
	 *     }
	 *   }
	 * }
	 *
	 * @since 3.0.0
	 * @since 4.1.6 Security checks run in verify_webhook before this callback executes.
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		// Get attachment_id from request (validated in verify_webhook).
		$attachment_id_param = $request->get_param( 'attachment_id' );
		$attachment_id = ! empty( $attachment_id_param ) ? (int) $attachment_id_param : 0;

		// Get cdn_urls from request.
		$cdn_urls = $request->get_param( 'cdn_urls' );

		// Determine status: if cdn_urls provided, status is 'completed', otherwise 'failed'.
		$status = WebhookAuthService::resolve_incoming_status( $cdn_urls );

		if ( 'failed' === $status ) {
			AttachmentMetaHandler::mark_conversion_failed(
				$attachment_id,
				'External processing failed: no CDN URLs provided in webhook.'
			);
			$this->logger->error( "Job failed for attachment {$attachment_id}. No CDN URLs provided in webhook." );

			return new WP_REST_Response(
				[
					'success' => true,
					'message' => 'Webhook received; job marked failed.',
				],
				200
			);
		}

		// Update job state in post meta using AttachmentMetaHandler.
		AttachmentMetaHandler::set_external_job_state( $attachment_id, $status );
		AttachmentMetaHandler::clear_conversion_failure( $attachment_id );

		// Handle successful processing.
		if ( $status === 'completed' ) {
			// Structure: {key_name: {format: {url, filesize}}}
			// Extract URLs and file sizes separately.
			$converted_files_by_size = [];

			foreach ( $cdn_urls as $size_name => $format_data_array ) {
				if ( ! is_array( $format_data_array ) ) {
					continue;
				}

				$converted_files_by_size[ $size_name ] = [];

				foreach ( $format_data_array as $format => $data ) {
					// Handle structure (object with url/filesize).
					if ( is_array( $data ) && ! empty( $data['url'] ) && is_string( $data['url'] ) ) {
						// Structure: {url, filesize}.
						$url = esc_url_raw( $data['url'] );
						$filesize = isset( $data['filesize'] ) ? (int) $data['filesize'] : 0;

						// Store URL and size together using unified structure.
						AttachmentMetaHandler::set_file_url_and_size( $attachment_id, sanitize_text_field( $format ), $size_name, $url, $filesize );

						// Also store in local array for batch update.
						$converted_files_by_size[ $size_name ][ sanitize_text_field( $format ) ] = [
							'url' => $url,
							'filesize' => $filesize,
						];
					}
				}
			}

			// Store CDN URLs in attachment meta.
			if ( ! empty( $converted_files_by_size ) ) {
				AttachmentMetaHandler::set_converted_files_grouped_by_size( $attachment_id, $converted_files_by_size );

				// Extract all URLs for efficient lookup.
				// Store ALL URLs (local and external) in META_KEY_FILE_URLS.
				$all_urls = [];
				foreach ( $converted_files_by_size as $size_formats ) {
					if ( ! is_array( $size_formats ) ) {
						continue;
					}
					foreach ( $size_formats as $format_data ) {
						if ( is_array( $format_data ) && isset( $format_data['url'] ) && is_string( $format_data['url'] ) && ! empty( $format_data['url'] ) ) {
							// Store all URLs (external service always provides URLs).
							$all_urls[] = $format_data['url'];
						}
					}
				}
				// Store all URLs in dedicated meta field for efficient lookup.
				if ( ! empty( $all_urls ) ) {
					AttachmentMetaHandler::set_file_urls( $attachment_id, array_unique( $all_urls ) );
				}

				// Extract formats list (including "original" format).
				$all_formats = [];
				foreach ( $converted_files_by_size as $size_formats ) {
					$all_formats = array_merge( $all_formats, array_keys( $size_formats ) );
				}
				$all_formats = array_unique( $all_formats );
				AttachmentMetaHandler::set_converted_formats( $attachment_id, $all_formats );
				AttachmentMetaHandler::set_conversion_date_now( $attachment_id );

				// Update ConversionTracker with file sizes.
				$conversion_tracker = new ConversionTracker( $this->logger );
				$converted_files_by_size_meta = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
				if ( ! empty( $converted_files_by_size_meta ) ) {
					foreach ( $converted_files_by_size_meta as $size_name => $size_formats ) {
						if ( ! is_array( $size_formats ) ) {
							continue;
						}

						// Get original file size for this size.
						$original_size = AttachmentMetaHandler::get_converted_file_size( $attachment_id, 'original', $size_name );
						if ( $original_size === null && $size_name !== 'full' ) {
							// Fallback to full size original.
							$original_size = AttachmentMetaHandler::get_converted_file_size( $attachment_id, 'original', 'full' );
						}

						foreach ( $size_formats as $format => $data ) {
							// Skip original format and invalid data.
							if ( $format === 'original' || ! is_array( $data ) || ! isset( $data['filesize'] ) ) {
								continue;
							}

							$filesize = (int) $data['filesize'];
							if ( $filesize > 0 && $original_size > 0 ) {
								$conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $filesize, $size_name );
							}
						}
					}
				}

				$this->logger->info( "Stored CDN URLs for attachment {$attachment_id} with sizes: " . implode( ', ', array_keys( $converted_files_by_size ) ) );
			}

			$this->logger->info( "Job completed successfully for attachment {$attachment_id}" );
		}

		return $this->create_success_response( null, 'Webhook processed successfully', 200 );
	}

	/**
	 * Get webhook URL for external service.
	 *
	 * @since 3.0.0
	 * @return string Webhook URL.
	 */
	public static function get_webhook_url() {
		return rest_url( 'flux-media-optimizer/v1/webhook' );
	}
}
