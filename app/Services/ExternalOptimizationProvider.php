<?php
/**
 * External optimization provider for CDN and remote processing.
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Http\Controllers\WebhookController;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\AttachmentMetaHandler;

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
	 * @var LoggerInterface
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
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
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
		// Handle media uploads.
		add_action( 'add_attachment', [ $this, 'handle_media_upload' ] );
		
		// Webhook endpoint is registered via WebhookController in Plugin class.
		
		// Schedule retry cron for failed jobs.
		if ( ! wp_next_scheduled( 'flux_media_optimizer_retry_failed_jobs' ) ) {
			wp_schedule_event( time(), 'hourly', 'flux_media_optimizer_retry_failed_jobs' );
		}
		add_action( 'flux_media_optimizer_retry_failed_jobs', [ $this, 'retry_failed_jobs' ] );
	}

	/**
	 * Handle media upload and submit to external service.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function handle_media_upload( $attachment_id ) {
		// Check if conversion is disabled for this attachment.
		if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
			return;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! wp_check_filetype( $file_path )['ext'] ) {
			return;
		}

		// Determine file type.
		$image_converter = new ImageConverter( $this->logger );
		$video_converter = new VideoConverter( $this->logger );

		$is_image = $image_converter->is_supported_image( $file_path );
		$is_video = $video_converter->is_supported_video( $file_path );

		if ( ! $is_image && ! $is_video ) {
			return;
		}

		// Check if auto-conversion is enabled.
		if ( $is_image && ! Settings::is_image_auto_convert_enabled() ) {
			return;
		}
		if ( $is_video && ! Settings::is_video_auto_convert_enabled() ) {
			return;
		}

		// Get formats to process.
		$formats = $is_image ? Settings::get_image_formats() : Settings::get_video_formats();
		
		// Get mimetype.
		$mimetype = get_post_mime_type( $attachment_id );
		if ( ! $mimetype ) {
			$mimetype = wp_check_filetype( $file_path )['type'] ?? '';
		}
		
		// Build operations array.
		$operations = [];
		
		if ( $is_image ) {
			// Get all image sizes with metadata.
			$metadata = wp_get_attachment_metadata( $attachment_id );
			
			// Always include full size operation.
			$full_operation = [
				'formats'  => $formats,
				'key_name' => 'full',
			];
			
			// Add resize dimensions for full size if available.
			if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
				$full_operation['resize'] = [
					'width'  => (int) $metadata['width'],
					'height' => (int) $metadata['height'],
				];
			}
			
			$operations[] = $full_operation;
			
			// Add operations for each WordPress image size.
			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					$operation = [
						'formats'  => $formats,
						'key_name' => $size_name,
					];
					
					// Add resize dimensions if available.
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
		
		// Submit job to external service.
		$result = $this->api_client->submit_job( $attachment_id, $operations, $mimetype );

		if ( ! $result['success'] ) {
			$this->logger->error( "Failed to submit job for attachment {$attachment_id}: " . ( $result['error'] ?? 'Unknown error' ) );
			AttachmentMetaHandler::set_external_job_state( $attachment_id, 'failed' );
			$this->add_admin_notice( $attachment_id, $result['error'] ?? 'Failed to submit job to external service' );
			return;
		}

		// Store job state in meta.
		AttachmentMetaHandler::set_external_job_state( $attachment_id, $result['status'] );
		$this->logger->debug( "Job submitted successfully for attachment {$attachment_id}" );
			}


	/**
	 * Get CDN URL for an attachment.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $format        Format (webp, avif, av1, webm).
	 * @param string $size          Size name.
	 * @return string|null CDN URL or null if not available.
	 */
	public function get_cdn_url( $attachment_id, $format, $size = 'full' ) {
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

		// Rebuild operations array from stored formats and sizes.
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return false;
		}

		// Determine file type.
		$image_converter = new ImageConverter( $this->logger );
		$video_converter = new VideoConverter( $this->logger );

		$is_image = $image_converter->is_supported_image( $file_path );
		$is_video = $video_converter->is_supported_video( $file_path );

		if ( ! $is_image && ! $is_video ) {
			return false;
		}

		// Get webhook URL and mimetype.
		$webhook_url = WebhookController::get_webhook_url();
		$mimetype = get_post_mime_type( $attachment_id );
		if ( ! $mimetype ) {
			$mimetype = wp_check_filetype( $file_path )['type'] ?? '';
		}

		// Rebuild operations array.
		$operations = [];
		$formats = $job['formats'] ?? [];

		if ( $is_image ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			
			// Always include full size operation.
			$full_operation = [
				'formats'  => $formats,
				'key_name' => 'full',
			];
			
			// Add resize dimensions for full size if available.
			if ( isset( $metadata['width'] ) && isset( $metadata['height'] ) ) {
				$full_operation['resize'] = [
					'width'  => (int) $metadata['width'],
					'height' => (int) $metadata['height'],
				];
			}
			
			$operations[] = $full_operation;
			
			// Add operations for each stored size.
			$sizes = $job['sizes'] ?? [];
			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $sizes as $size_name ) {
					if ( 'full' === $size_name ) {
						continue; // Already added.
					}
					
					$operation = [
						'formats'  => $formats,
						'key_name' => $size_name,
					];
					
					// Add resize dimensions if available.
					if ( isset( $metadata['sizes'][ $size_name ]['width'] ) && isset( $metadata['sizes'][ $size_name ]['height'] ) ) {
						$operation['resize'] = [
							'width'  => (int) $metadata['sizes'][ $size_name ]['width'],
							'height' => (int) $metadata['sizes'][ $size_name ]['height'],
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

