<?php
/**
 * Webhook REST API controller for Flux Media Optimizer plugin.
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Http\Controllers;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\Logger;
use FluxMedia\App\Services\Settings;
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
		parent::__construct( new Logger() );
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
			'permission_callback' => [ $this, 'verify_webhook' ],
		] );
	}

	/**
	 * Verify webhook request.
	 *
	 * @since 3.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if request is valid.
	 */
	public function verify_webhook( WP_REST_Request $request ) {
		// Basic verification - can be enhanced with signature checking.
		return true;
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
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		// Validate account_id from request.
		$request_account_id = sanitize_text_field( $request->get_param( 'account_id' ) );
		$stored_account_id = Settings::get_account_id();

		if ( empty( $request_account_id ) ) {
			return $this->create_error_response( 'Missing account_id', 'missing_account_id', 400 );
		}

		if ( empty( $stored_account_id ) ) {
			return $this->create_error_response( 'Account ID not configured', 'account_id_not_configured', 500 );
		}

		if ( $request_account_id !== $stored_account_id ) {
			$this->logger->warning( "Webhook account_id mismatch. Request: {$request_account_id}, Stored: {$stored_account_id}" );
			return $this->create_error_response( 'Invalid account_id', 'invalid_account_id', 403 );
		}

		// Get attachment_id from request.
		$attachment_id_param = $request->get_param( 'attachment_id' );
		$attachment_id = ! empty( $attachment_id_param ) ? (int) $attachment_id_param : null;

		if ( empty( $attachment_id ) ) {
			return $this->create_error_response( 'Missing attachment_id', 'missing_attachment_id', 400 );
		}

		// Get cdn_urls from request.
		$cdn_urls = $request->get_param( 'cdn_urls' );

		// Determine status: if cdn_urls provided, status is 'completed', otherwise 'failed'.
		$status = ! empty( $cdn_urls ) && is_array( $cdn_urls ) ? 'completed' : 'failed';

		// Update job state in post meta using AttachmentMetaHandler.
		$state_updated = AttachmentMetaHandler::set_external_job_state( $attachment_id, $status );
		if ( ! $state_updated ) {
			$this->logger->error( "Failed to update job state for attachment {$attachment_id} to status: {$status}" );
			return $this->create_error_response( 'Failed to update job state', 'state_update_failed', 500 );
		}

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

				// Extract all CDN URLs for efficient lookup.
				$cdn_urls_list = [];
				foreach ( $converted_files_by_size as $size_formats ) {
					if ( ! is_array( $size_formats ) ) {
						continue;
					}
					foreach ( $size_formats as $format_data ) {
						if ( is_array( $format_data ) && isset( $format_data['url'] ) && is_string( $format_data['url'] ) ) {
							// Only add CDN URLs (those starting with http:// or https://).
							if ( strpos( $format_data['url'], 'http://' ) === 0 || strpos( $format_data['url'], 'https://' ) === 0 ) {
								$cdn_urls_list[] = $format_data['url'];
							}
						}
					}
				}
				// Store CDN URLs in dedicated meta field for efficient lookup.
				if ( ! empty( $cdn_urls_list ) ) {
					AttachmentMetaHandler::set_cdn_urls( $attachment_id, $cdn_urls_list );
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
		} else {
			// Note: We don't clear converted_files_by_size here because:
			// 1. There might be valid local conversions that should be preserved
			// 2. The failed status is already set, which prevents reprocessing
			// 3. Users can manually retry conversion if needed

			$this->logger->error( "Job failed for attachment {$attachment_id}. No CDN URLs provided in webhook." );
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

