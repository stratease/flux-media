<?php
/**
 * External optimization provider for CDN and remote processing.
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\Settings;
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
		
		// Schedule retry cron for failed jobs.
		if ( ! wp_next_scheduled( 'flux_media_optimizer_retry_failed_jobs' ) ) {
			wp_schedule_event( time(), 'hourly', 'flux_media_optimizer_retry_failed_jobs' );
		}
		add_action( 'flux_media_optimizer_retry_failed_jobs', [ $this, 'retry_failed_jobs' ] );
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
		return in_array( $state, [ 'queued', 'processing' ], true );
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

		$formats = [];
		if ( $is_image ) {
			$formats = Settings::get_image_formats();
		} elseif ( $is_video ) {
			$formats = Settings::get_video_formats();
		}

		$operations = [];

		if ( $is_image ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );

			$full_operation = [
				'formats'  => $formats,
				'key_name' => 'full',
			];

			if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
				$full_operation['resize'] = [
					'width'  => (int) $metadata['width'],
					'height' => (int) $metadata['height'],
				];
			}

			$operations[] = $full_operation;

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					$operation = [
						'formats'  => $formats,
						'key_name' => $size_name,
					];

					if ( isset( $size_data['width'] ) && isset( $size_data['height'] ) ) {
						$operation['resize'] = [
							'width'  => (int) $size_data['width'],
							'height' => (int) $size_data['height'],
						];
					}

					$operations[] = $operation;
				}
			}
		} else {
			// Videos only have full size.
			$operations[] = [
				'formats'  => $formats,
				'key_name' => 'full',
			];
		}

		// Submit job again.
		$result = $this->api_client->submit_job( $attachment_id, $operations, $mimetype );

		if ( ! $result['success'] ) {
			// Update job state to failed.
			AttachmentMetaHandler::set_external_job_state( $attachment_id, 'failed' );
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
	 * @return void
	 */
	public function retry_failed_jobs() {
		// Get all attachments with failed job state.
		// Note: This queries all attachments - if performance becomes an issue, consider adding a meta query optimization.
		$args = [
			'post_type' => 'attachment',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
					'value' => 'failed',
					'compare' => '=',
				],
			],
		];
		
		$query = new \WP_Query( $args );
		
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post ) {
				$this->retry_failed_job( $post->ID );
			}
		}
	}

	/**
	 * Add admin notice for job status.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $message       Notice message.
	 * @return void
	 */
	private function add_admin_notice( $attachment_id, $message ) {
		// Store notice in transient for display on attachment screen.
		$notices = get_transient( 'flux_media_optimizer_notices' ) ?: [];
		$notices[ $attachment_id ] = [
			'message' => $message,
			'time' => time(),
		];
		set_transient( 'flux_media_optimizer_notices', $notices, 3600 );
	}
}

