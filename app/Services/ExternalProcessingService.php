<?php
/**
 * External processing service for media processing operations.
 *
 * @package FluxMedia\App\Services
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Http\Controllers\WebhookController;
use FluxMedia\App\Services\AttachmentMetaHandler;

/**
 * External processing service implementation.
 *
 * Handles all external media processing operations via ExternalOptimizationProvider.
 * Images and videos are processed and optimized; all other file types are stored on CDN for delivery.
 *
 * @since 3.0.0
 */
class ExternalProcessingService implements ProcessingServiceInterface {

	/**
	 * External optimization provider instance.
	 *
	 * @since 3.0.0
	 * @var ExternalOptimizationProvider
	 */
	private $external_provider;

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
	 * @param ExternalOptimizationProvider $external_provider External optimization provider.
	 */
	public function __construct( ExternalOptimizationProvider $external_provider ) {
		$this->external_provider = $external_provider;
		$this->logger = \FluxMedia\FluxPlugins\Common\Logger\Logger::get_instance();
		$this->api_client = new ExternalApiClient( $this->logger );
	}

	/**
	 * Process attachment metadata update.
	 *
	 * For images, this is called after sizes are generated, so metadata contains size information.
	 * The metadata is used by submit_processing_job to build operations for all sizes.
	 *
	 * @since 3.0.0
	 * @param array $data Attachment metadata (contains sizes for images).
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified metadata.
	 */
	public function process_metadata_update( $data, $attachment_id ) {
		// Use unified process() method
		// submit_processing_job (called by process()) will fetch metadata which now includes sizes.
		$this->process( $attachment_id );

		return $data;
	}

	/**
	 * Process attached file update.
	 *
	 * @since 3.0.0
	 * @param string $file New file path for the attachment.
	 * @param int    $attachment_id Attachment ID.
	 * @return string File path (unmodified).
	 */
	public function process_file_update( $file, $attachment_id ) {
		if ( ! $file || ! wp_check_filetype( $file )['ext'] ) {
			return $file;
		}

		// Use unified process() method with file path
		// Pass file path directly since we have it and it may not be in meta yet
		$this->process( $attachment_id, $file );

		return $file;
	}

	/**
	 * Process image editor file save.
	 *
	 * @since 3.0.0
	 * @param mixed       $override   Override value from other filters (usually null).
	 * @param string      $filename   Saved filename for the edited image.
	 * @param object      $image      Image editor instance.
	 * @param string      $mime_type  MIME type of the saved image.
	 * @param int|false   $post_id    Attachment ID if available, otherwise false.
	 * @return mixed Original $override value.
	 */
	public function process_image_editor_save( $override, $filename, $image, $mime_type, $post_id ) {
		if ( empty( $post_id ) || ! $filename || ! wp_check_filetype( $filename )['ext'] ) {
			return $override;
		}

		// Use unified process() method with file path
		// Pass file path directly since we have it and it may not be in meta yet
		$this->process( (int) $post_id, $filename );

		return $override;
	}

	/**
	 * Process video via cron.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Source file path.
	 * @return void
	 */
	public function process_video_cron( $attachment_id, $file_path ) {
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		$this->submit_processing_job( $attachment_id, $file_path );
	}


	/**
	 * Process attachment conversion.
	 *
	 * Unified method for processing attachment conversion. Submits a processing job
	 * to the external service for all file types.
	 *
	 * @since 3.0.0
	 * @param int         $attachment_id Attachment ID.
	 * @param string|null $file_path     Optional file path. If null, will be retrieved from attachment meta.
	 *                                   This parameter is useful when processing is triggered before the file path
	 *                                   is stored in the attachment meta (e.g., during initial upload).
	 * @return bool True if conversion was initiated successfully, false otherwise.
	 */
	public function process( $attachment_id, $file_path = null ) {
		// Check if conversion is disabled for this attachment
		if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
			$this->logger->info( "Attachment conversion skipped: Conversion disabled for attachment {$attachment_id}" );
			return false;
		}

		// Get file path if not provided
		// Note: We retrieve from meta here because sometimes processing is triggered before
		// the file path is stored in the attachment meta (e.g., during initial upload).
		// When file_path is provided (e.g., from process_file_update), we use it directly.
		if ( empty( $file_path ) ) {
			$file_path = get_attached_file( $attachment_id );
		}

		// Submit processing job (handles errors internally)
		$this->submit_processing_job( $attachment_id, $file_path );

		// Return true if file is valid (job submission attempted)
		// Note: submit_processing_job handles errors internally and updates state accordingly
		return true;
	}

	/**
	 * Update external job state for an attachment.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $state         Job state ('queued', 'processing', 'completed', 'failed').
	 * @return void
	 */
	private function update_job_state( $attachment_id, $state ) {
		AttachmentMetaHandler::set_external_job_state( $attachment_id, $state );
	}

	/**
	 * Submit a processing job to the external service.
	 *
	 * For images and videos: builds operations array with formats and sizes (if applicable) for processing and optimization.
	 * For all other file types: sends simple operation with 'full' key_name for CDN storage (no processing, just storage).
	 *
	 * Note: Conversion disabled, request-level duplicate processing, and external job state checks are handled
	 * by WordPressProvider::should_skip_processing() before this method is called.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path.
	 * @return void
	 */
	private function submit_processing_job( $attachment_id, $file_path ) {
		$resolver              = new AttachmentSourcePathResolver( $this->logger );
		$optimization_path     = $resolver->get_optimization_source_path( $attachment_id );
		$original_file_path    = null;

		if ( ! empty( $optimization_path ) && file_exists( $optimization_path ) ) {
			$original_file_path = $optimization_path;
		}

		// Determine the original file path with priority:
		// 1. Optimization source (HEIC/HEIF original when applicable)
		// 2. Use $file_path if it's a valid local file (first upload scenario)
		// 3. Fall back to get_attached_file() (WordPress stored path)
		// 4. Fall back to constructing from metadata (_wp_attached_file)
		if ( empty( $original_file_path ) ) {
			// On first upload, this is the actual file that was just uploaded.
			if ( ! empty( $file_path ) &&
			     strpos( $file_path, 'http://' ) !== 0 &&
			     strpos( $file_path, 'https://' ) !== 0 &&
			     file_exists( $file_path ) ) {
				$original_file_path = $file_path;
			}

			// If $file_path is not valid, try get_attached_file().
			if ( empty( $original_file_path ) ) {
				$original_file_path = get_attached_file( $attachment_id );

				// Validate it's a local file and exists.
				if ( ! empty( $original_file_path ) &&
				     ( strpos( $original_file_path, 'http://' ) === 0 || strpos( $original_file_path, 'https://' ) === 0 || ! file_exists( $original_file_path ) ) ) {
					$original_file_path = null;
				}
			}

			// If still empty, try constructing from metadata.
			if ( empty( $original_file_path ) ) {
				$upload_dir = wp_upload_dir();
				$attached_file_meta = get_post_meta( $attachment_id, '_wp_attached_file', true );

				if ( ! empty( $attached_file_meta ) ) {
					$constructed_path = $upload_dir['basedir'] . '/' . $attached_file_meta;
					if ( file_exists( $constructed_path ) ) {
						$original_file_path = $constructed_path;
					}
				}
			}
		}
		
		// Validate that we have a local file path, not a CDN URL.
		if ( empty( $original_file_path ) || strpos( $original_file_path, 'http://' ) === 0 || strpos( $original_file_path, 'https://' ) === 0 ) {
			$message = "Cannot submit job for attachment {$attachment_id}: Invalid file path (CDN URL or empty). Passed path: {$file_path}, Resolved path: {$original_file_path}";
			$this->logger->error( $message );
			AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
			return;
		}
		
		// Ensure the file exists locally before submitting.
		if ( ! file_exists( $original_file_path ) ) {
			$message = "Cannot submit job for attachment {$attachment_id}: Original file does not exist at path: {$original_file_path}";
			$this->logger->error( $message );
			AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
			return;
		}
		
		// Use the validated original file path for submission.
		$file_path = $original_file_path;

		// Get mimetype for file type detection.
		$mimetype = wp_check_filetype( $file_path )['type'] ?? '';
		if ( empty( $mimetype ) ) {
			$mimetype = get_post_mime_type( $attachment_id );
		}

		$operations = ExternalOperationsBuilder::build_for_attachment( $attachment_id );
		// Update state to 'queued' before submission.
		$this->update_job_state( $attachment_id, 'queued' );

		// Submit job to external service.
		$result = $this->api_client->submit_job( $attachment_id, $operations, $mimetype );
		
		if ( ! $result['success'] ) {
			$message = "Failed to submit job for attachment {$attachment_id}: " . ( $result['error'] ?? 'Unknown error' );
			AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
			$this->logger->error( $message );
			return;
		}

		// Update state to 'processing' on successful submission.
		$this->update_job_state( $attachment_id, 'processing' );
		$this->logger->debug( "Job submitted successfully for attachment {$attachment_id}" );
	}

	/**
	 * Delete attachment from external service.
	 *
	 * Notifies the external service to delete files associated with an attachment
	 * and clears all conversion-related meta data.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if deletion was successful or not needed, false on error.
	 */
	public function delete_attachment( $attachment_id ) {
		// Check if we have file URLs - if not, nothing to delete from external service
		$file_urls = AttachmentMetaHandler::get_file_urls( $attachment_id );
		if ( empty( $file_urls ) ) {
			// No file URLs, nothing to delete
			$this->logger->debug( "No file URLs found for attachment {$attachment_id}, skipping external deletion" );
			// Still clear meta in case there's stale data
			AttachmentMetaHandler::clear_all_attachment_meta( $attachment_id );
			return true;
		}

		// Delete from external service via API client
		$result = $this->api_client->delete_attachment( $attachment_id );
		
		if ( ! $result['success'] ) {
			$this->logger->warning( "Failed to delete attachment {$attachment_id} from external service: " . ( $result['message'] ?? 'Unknown error' ) );
			return false;
		}

		AttachmentMetaHandler::clear_all_attachment_meta( $attachment_id );

		$this->logger->debug( "Attachment {$attachment_id} deleted from external service successfully" );
		return true;
	}
}

