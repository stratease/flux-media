<?php
/**
 * External optimization provider for CDN and remote processing.
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * Handles external service integration for CDN and remote processing.
 *
 * Images and videos are processed and optimized; all other file types are stored on CDN for delivery.
 *
 * @since 3.0.0
 */
class ExternalOptimizationProvider {

	/**
	 * Logger instance.
	 *
	 * @since 3.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * External API client instance.
	 *
	 * @since 3.0.0
	 * @var ExternalApiClient
	 */
	private $api_client;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
		$this->api_client = new ExternalApiClient( $logger );
	}

	/**
	 * Initialize the provider and register hooks.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function init() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function register_hooks() {
		// Webhook endpoint is registered via WebhookController in Plugin class.
		// Failed-job retries are handled by CleanupService (daily cleanup + legacy hook delegate).
	}

	/**
	 * Get file URL for a converted file.
	 *
	 * Returns the stored URL (local or external) for a converted file.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, av1, webm).
	 * @param string $size          Size name.
	 * @return string|null File URL or null if not available.
	 */
	public function get_file_url( $attachment_id, $format, $size = 'full' ) {
		return AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size );
	}

	/**
	 * Check if a job is currently processing.
	 *
	 * @since 3.0.0
	 * @since 3.0.0 Updated to use AttachmentMetaHandler instead of external_jobs table.
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if job is queued or processing.
	 */
	public function is_job_processing( $attachment_id ) {
		$state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
		return AttachmentMetaHandler::is_in_flight_job_state( $state );
	}

	/**
	 * Get job status for an attachment.
	 *
	 * Returns job state from meta.
	 *
	 * @since 3.0.0
	 * @since 3.0.0 Updated to use AttachmentMetaHandler instead of external_jobs table. Removed backward compatibility - now returns string|null.
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Job state ('queued', 'processing', 'completed', 'failed') or null if not found.
	 */
	public function get_job_status( $attachment_id ) {
		return AttachmentMetaHandler::get_external_job_state( $attachment_id );
	}

	/**
	 * Retry a failed job.
	 *
	 * @since 3.0.0
	 * @since 3.0.0 Updated to use AttachmentMetaHandler instead of external_jobs table. Retry count tracking removed.
	 * @since 4.1.5 Rebuild operations from Settings and attachment metadata (aligned with ExternalProcessingService); fixed undefined job payload.
	 * @param int $attachment_id Attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public function retry_failed_job( $attachment_id ) {
		$state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
		if ( $state !== 'failed' ) {
			return false;
		}

		// Note: Retry count tracking removed - meta-based system doesn't track retry counts.
		// If retry count tracking is needed in the future, it can be added as separate meta.

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return false;
		}

		$mimetype = get_post_mime_type( $attachment_id );
		if ( ! $mimetype ) {
			$mimetype = wp_check_filetype( $file_path )['type'] ?? '';
		}

		$is_image = ! empty( $mimetype ) && strpos( $mimetype, 'image/' ) === 0;
		$is_video = ! empty( $mimetype ) && strpos( $mimetype, 'video/' ) === 0;

		if ( ! $is_image && ! $is_video ) {
			return false;
		}

		$operations = ExternalOperationsBuilder::build_for_attachment( $attachment_id );

		// Submit job again.
		$result = $this->api_client->submit_job( $attachment_id, $operations, $mimetype );

		if ( ! $result['success'] ) {
			AttachmentMetaHandler::mark_conversion_failed(
				$attachment_id,
				$result['error'] ?? 'External job retry submission failed.'
			);
			return false;
		}

		// Update job state.
		AttachmentMetaHandler::set_external_job_state( $attachment_id, $result['status'] );

		return true;
	}

	/**
	 * Retry all failed jobs (called by cron).
	 *
	 * @since 3.0.0
	 * @since 3.0.0 Updated to use AttachmentMetaHandler and WP_Query instead of external_jobs table.
	 * @since 4.2.0 Deprecated for direct cron use; CleanupService owns bounded retry orchestration.
	 * @return void
	 */
	public function retry_failed_jobs() {
		// Retained for backward compatibility if called directly; CleanupService is the orchestrator.
		$query = new \WP_Query(
			[
				'post_type' => 'attachment',
				'post_status' => 'any',
				'posts_per_page' => CleanupService::get_cleanup_batch_size(),
				'meta_query' => [
					[
						'key' => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
						'value' => 'failed',
						'compare' => '=',
					],
				],
			]
		);

		if ( ! $query->have_posts() ) {
			return;
		}

		foreach ( $query->posts as $post ) {
			$this->retry_failed_job( $post->ID );
		}
	}
}

