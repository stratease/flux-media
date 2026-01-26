<?php
/**
 * External API client for Flux Media Optimizer external service.
 *
 * @package FluxMedia
 * @since 3.0.0
 * @since 4.0.0 Refactored to use shared ExternalApiClient for shared endpoints.
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Http\Controllers\WebhookController;
use FluxMedia\FluxPlugins\Common\Account\AccountIdService;
use FluxMedia\FluxPlugins\Common\Api\ExternalApiClient as SharedExternalApiClient;

/**
 * Handles communication with external CDN and processing service.
 *
 * @since 3.0.0
 */
class ExternalApiClient {

	/**
	 * Logger instance.
	 *
	 * @since 3.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Shared external API client instance.
	 *
	 * @since 4.0.0
	 * @var SharedExternalApiClient
	 */
	private $shared_api_client;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 * @since 4.0.0 Initialize shared API client (uses constants internally).
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		// Shared API client will use constants internally (FLUX_PLUGINS_COMMON_* or FLUX_MEDIA_OPTIMIZER_* for backward compatibility).
		$this->shared_api_client = new SharedExternalApiClient( $logger );
	}


	/**
	 * Submit a job to the external service.
	 *
	 * Plugin-specific endpoint wrapper using shared API client.
	 *
	 * @since 3.0.0
	 * @since 4.0.0 Use shared API client's post() method.
	 * @param int    $attachment_id Attachment ID.
	 * @param array  $operations    Array of operations to perform.
	 * @param string $mimetype     MIME type of the file.
	 * @return array Response array with 'success', 'status', or 'error'.
	 */
	public function submit_job( $attachment_id, $operations = [], $mimetype = '' ) {
		// Check compatibility before making API request (plugin-specific endpoint).
		$validator = \FluxMedia\FluxPlugins\Common\Services\CompatibilityService::get_validator();
		if ( $validator !== null ) {
			$validator->check_compatibility();
			if ( $validator->should_block_operations() ) {
				$this->logger->warning( "Job submission blocked for attachment {$attachment_id}: Compatibility check indicates operations are disabled" );
				return [
					'success' => false,
					'error' => 'compatibility_check_failed',
					'message' => 'Compatibility check failed. Please update the plugin or check compatibility status.',
				];
			}
		}

		$account_id = AccountIdService::get_instance()->get_account_id();

		if ( empty( $account_id ) ) {
			return [
				'success' => false,
				'error' => 'Account ID not found',
			];
		}

		// Get original file URL from attachment ID (not CDN URL).
		// Use get_attached_file() to get the file path, then convert to URL.
		// This bypasses any wp_get_attachment_url filters that might return CDN URLs.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return [
				'success' => false,
				'error' => 'Could not get attachment file path',
			];
		}

		// Convert file path to URL using WordPress upload directory.
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'];
		$base_url = $upload_dir['baseurl'];

		// Replace the base directory path with the base URL.
		if ( strpos( $file_path, $base_dir ) === 0 ) {
			$pull_file_url = str_replace( $base_dir, $base_url, $file_path );
			// Normalize path separators for URLs.
			$pull_file_url = str_replace( '\\', '/', $pull_file_url );
		} else {
			// Fallback: try wp_get_attachment_url but this might return CDN URL.
			$pull_file_url = wp_get_attachment_url( $attachment_id );
		}

		if ( ! $pull_file_url ) {
			return [
				'success' => false,
				'error' => 'Could not get attachment URL',
			];
		}

		// Generate webhook URL.
		$webhook_url = WebhookController::get_webhook_url();

		// Parse FLUX_MEDIA_OPTIMIZER_PULL_FILE_URL_DOMAIN for dev testing purposes into both pull_file_url and webhook_url for consistent integration domain.
		if ( defined( 'FLUX_MEDIA_OPTIMIZER_PULL_FILE_URL_DOMAIN' ) ) {
			$parsed_url = wp_parse_url( $pull_file_url );
			if ( $parsed_url && isset( $parsed_url['path'] ) ) {
				$new_domain = rtrim( FLUX_MEDIA_OPTIMIZER_PULL_FILE_URL_DOMAIN, '/' );
				$path = $parsed_url['path'];
				$query = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
				$pull_file_url = $new_domain . $path . $query;
				// Parse webhook url domain.
				$parsed_webhook = wp_parse_url( $webhook_url );
				if ( $parsed_webhook && isset( $parsed_webhook['path'] ) ) {
					$webhook_path = $parsed_webhook['path'];
					$webhook_query = isset( $parsed_webhook['query'] ) ? '?' . $parsed_webhook['query'] : '';
					$webhook_url = $new_domain . $webhook_path . $webhook_query;
				}
			}
		}

		// Use shared API client's post() method for plugin-specific endpoint.
		$response = $this->shared_api_client->post(
			'api/v1/' . FLUX_MEDIA_OPTIMIZER_API_NAMESPACE . '/upload/init',
			[
				'account_id'    => $account_id,
				'attachment_id' => (string) $attachment_id,
				'pull_file_url' => esc_url_raw( $pull_file_url ),
				'webhook_url'   => esc_url_raw( $webhook_url ),
				'mimetype'      => sanitize_text_field( $mimetype ),
				'operations'    => $operations,
			]
		);

		if ( ! $response['success'] ) {
			return $response;
		}

		$data = $response['data'];

		if ( isset( $data['error'] ) ) {
			$this->logger->error( "External service error: {$data['error']}" );
			return [
				'success' => false,
				'error' => $data['error'],
			];
		}

		if ( ! isset( $data['status'] ) ) {
			$this->logger->error( "Invalid response from external service: " . wp_json_encode( $data ) );
			return [
				'success' => false,
				'error' => 'Invalid response from external service',
			];
		}

		$this->logger->debug( "Job submitted successfully for attachment {$attachment_id}" );

		return [
			'success' => true,
			'status' => sanitize_text_field( $data['status'] ),
		];
	}


	/**
	 * Check plugin compatibility with external service.
	 *
	 * Wrapper for shared API client's check_compatibility() method.
	 *
	 * @since 3.0.0
	 * @since 4.0.0 Delegate to shared API client.
	 * @param string $plugin_identifier Plugin identifier (e.g., 'flux-media-optimizer').
	 * @param string $plugin_version   Current plugin version.
	 * @return \FluxPlugins\Common\Compatibility\CompatibilityResponse|array Response object or array with 'success' and error info on failure.
	 */
	public function check_compatibility( $plugin_identifier, $plugin_version ) {
		// Delegate to shared API client.
		return $this->shared_api_client->check_compatibility( $plugin_identifier, $plugin_version );
	}

	/**
	 * Delete attachment from external service.
	 *
	 * Plugin-specific endpoint wrapper using shared API client.
	 * Do not check compatibility before making API request. We want to avoid orphan data
	 * when files are being deleted, even if this fails we at least try.
	 *
	 * @since 3.0.0
	 * @since 4.0.0 Use shared API client's post() method.
	 * @param int $attachment_id Attachment ID.
	 * @return array Response array with 'success' and optional 'error' or 'message'.
	 */
	public function delete_attachment( $attachment_id ) {
		$account_id = AccountIdService::get_instance()->get_account_id();

		if ( empty( $account_id ) ) {
			$this->logger->error( "Attachment deletion failed for attachment {$attachment_id}: Account ID not found" );
			return [
				'success' => false,
				'error' => 'account_id_required',
				'message' => 'Account ID not found',
			];
		}

		$this->logger->debug( "Deleting attachment {$attachment_id} from external service for account " . AccountIdService::get_instance()->obfuscate_account_id() );

		// Use shared API client's post() method for plugin-specific endpoint.
		$response = $this->shared_api_client->post(
			'api/v1/' . FLUX_MEDIA_OPTIMIZER_API_NAMESPACE . '/upload/delete',
			[
				'account_id'    => $account_id,
				'attachment_id' => (string) $attachment_id,
			]
		);

		if ( ! $response['success'] ) {
			// Log as warning since deletion failure shouldn't block WordPress deletion.
			$this->logger->warning( "Attachment deletion returned error: {$response['message']}", [ 'response' => $response ] );
			return $response;
		}

		$data = $response['data'];

		$this->logger->debug( "Attachment {$attachment_id} deleted successfully from external service" );

		return [
			'success' => true,
			'message' => isset( $data['message'] ) ? $data['message'] : 'Attachment deleted successfully',
		];
	}
}

