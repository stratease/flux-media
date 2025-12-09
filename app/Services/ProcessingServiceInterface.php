<?php
/**
 * Processing service interface for media processing operations.
 *
 * @package FluxMedia\App\Services
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

/**
 * Interface for media processing services.
 *
 * Defines common methods for both local and external processing services.
 *
 * @since 3.0.0
 */
interface ProcessingServiceInterface {

	/**
	 * Process attachment metadata update.
	 *
	 * Handles all media uploads after metadata is generated.
	 * For images: waits for sizes to be available in metadata.
	 * For non-images: processes immediately.
	 *
	 * @since 3.0.0
	 * @param array $data Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified metadata.
	 */
	public function process_metadata_update( $data, $attachment_id );

	/**
	 * Process attached file update.
	 *
	 * @since 3.0.0
	 * @param string $file New file path for the attachment.
	 * @param int    $attachment_id Attachment ID.
	 * @return string File path (unmodified).
	 */
	public function process_file_update( $file, $attachment_id );

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
	public function process_image_editor_save( $override, $filename, $image, $mime_type, $post_id );

	/**
	 * Process video via cron.
	 *
	 * @since 3.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Source file path.
	 * @return void
	 */
	public function process_video_cron( $attachment_id, $file_path );

	/**
	 * Process bulk conversion via cron.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function process_bulk_conversion_cron();

	/**
	 * Process manual conversion for an attachment.
	 *
	 * Handles manual conversion requests (e.g., from AJAX or admin actions).
	 * For images: processes synchronously.
	 * For videos: enqueues for async processing.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if conversion was initiated successfully, false otherwise.
	 */
	public function process_manual_conversion( $attachment_id );
}

