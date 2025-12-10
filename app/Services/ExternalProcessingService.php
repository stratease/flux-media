<?php
/**
 * External processing service for media processing operations.
 *
 * @package FluxMedia\App\Services
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Http\Controllers\WebhookController;

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
	 * @param ExternalOptimizationProvider $external_provider External optimization provider.
	 */
	public function __construct( ExternalOptimizationProvider $external_provider ) {
		$this->external_provider = $external_provider;
		$this->logger = new Logger();
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
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return $data;
		}

		// Submit processing job - submit_processing_job will fetch metadata which now includes sizes.
		$this->submit_processing_job( $attachment_id, $file_path );

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

		$this->submit_processing_job( $attachment_id, $file );

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
		if ( empty( $post_id ) ) {
			return $override;
		}

		if ( ! $filename || ! wp_check_filetype( $filename )['ext'] ) {
			return $override;
		}

		$this->submit_processing_job( (int) $post_id, $filename );

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
	 * Process bulk conversion via cron.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function process_bulk_conversion_cron() {
		// Bulk conversion for external service is handled via the external service's own mechanisms.
		// This method exists for interface compliance.
		return; // TODO
	}

	/**
	 * Process manual conversion for an attachment.
	 *
	 * Handles manual conversion requests (e.g., from AJAX or admin actions).
	 * Submits a processing job to the external service for all file types.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if conversion was initiated successfully, false otherwise.
	 */
	public function process_manual_conversion( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! wp_check_filetype( $file_path )['ext'] ) {
			return false;
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
		// Determine the original file path with priority:
		// 1. Use $file_path if it's a valid local file (first upload scenario)
		// 2. Fall back to get_attached_file() (WordPress stored path)
		// 3. Fall back to constructing from metadata (_wp_attached_file)
		$original_file_path = null;
		
		// First, check if the passed $file_path is a valid local file.
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
		
		// Validate that we have a local file path, not a CDN URL.
		if ( empty( $original_file_path ) || strpos( $original_file_path, 'http://' ) === 0 || strpos( $original_file_path, 'https://' ) === 0 ) {
			$this->logger->error( "Cannot submit job for attachment {$attachment_id}: Invalid file path (CDN URL or empty). Passed path: {$file_path}, Resolved path: {$original_file_path}" );
			$this->update_job_state( $attachment_id, 'failed' );
			return;
		}
		
		// Ensure the file exists locally before submitting.
		if ( ! file_exists( $original_file_path ) ) {
			$this->logger->error( "Cannot submit job for attachment {$attachment_id}: Original file does not exist at path: {$original_file_path}" );
			$this->update_job_state( $attachment_id, 'failed' );
			return;
		}
		
		// Use the validated original file path for submission.
		$file_path = $original_file_path;
		
		// Update state to 'queued' before submission.
		$this->update_job_state( $attachment_id, 'queued' );

		// Get mimetype for file type detection.
		$mimetype = get_post_mime_type( $attachment_id );
		if ( ! $mimetype ) {
			$mimetype = wp_check_filetype( $file_path )['type'] ?? '';
		}
		
		// Determine file type based on MIME type.
		// All standard image MIME types start with 'image/' (e.g., image/jpeg, image/png, image/webp, image/avif).
		// All standard video MIME types start with 'video/' (e.g., video/mp4, video/webm, video/ogg).
		$is_image = ! empty( $mimetype ) && strpos( $mimetype, 'image/' ) === 0;
		$is_video = ! empty( $mimetype ) && strpos( $mimetype, 'video/' ) === 0;

		// Check if auto-conversion is enabled.
		if ( $is_image && ! Settings::is_image_auto_convert_enabled() ) {
			return;
		}
		if ( $is_video && ! Settings::is_video_auto_convert_enabled() ) {
			return;
		}

		// Get formats to process.
		$formats = [];
		if ( $is_image ) {
			$formats = Settings::get_image_formats();
		} elseif ( $is_video ) {
			$formats = Settings::get_video_formats();
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
		} elseif($is_video) {
			// Videos only have full size.
			$operations[] = [
				'formats'  => $formats,
				'key_name' => 'full',
			];
		} else {
			// For all other file types, send simple CDN storage operation.
			// The 'full' key_name is reserved and the original file is automatically preserved under it.
			$operations[] = [
				'key_name' => 'full',
			];
		}

		// Submit job to external service.
		$result = $this->api_client->submit_job( $attachment_id, $operations, $mimetype );
		
		if ( ! $result['success'] ) {
			// Update state to 'failed' on submission error.
			$this->update_job_state( $attachment_id, 'failed' );
			$this->logger->error( "Failed to submit job for attachment {$attachment_id}: " . ( $result['error'] ?? 'Unknown error' ) );
			return;
		}

		// Update state to 'processing' on successful submission.
		$this->update_job_state( $attachment_id, 'processing' );
		$this->logger->debug( "Job submitted successfully for attachment {$attachment_id}" );
	}
}

