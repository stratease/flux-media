<?php
/**
 * Local processing service for media processing operations.
 *
 * @package FluxMedia\App\Services
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\ImageConverter;
use FluxMedia\App\Services\VideoConverter;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\BulkConverter;
use FluxMedia\App\Services\AttachmentMetaHandler;

/**
 * Local processing service implementation.
 *
 * Handles all local media processing operations using ImageConverter and VideoConverter.
 *
 * @since 3.0.0
 */
class LocalProcessingService implements ProcessingServiceInterface {

	/**
	 * Logger instance.
	 *
	 * @since 3.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Image converter instance.
	 *
	 * @since 3.0.0
	 * @var ImageConverter
	 */
	private $image_converter;

	/**
	 * Video converter instance.
	 *
	 * @since 3.0.0
	 * @var VideoConverter
	 */
	private $video_converter;

	/**
	 * Conversion tracker instance.
	 *
	 * @since 3.0.0
	 * @var ConversionTracker
	 */
	private $conversion_tracker;

	/**
	 * Bulk converter instance.
	 *
	 * @since 3.0.0
	 * @var BulkConverter
	 */
	private $bulk_converter;

	/**
	 * WordPress provider instance (for accessing private methods).
	 *
	 * @since 3.0.0
	 * @var WordPressProvider
	 */
	private $wordpress_provider;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 * @param ImageConverter    $image_converter Image converter service.
	 * @param VideoConverter    $video_converter Video converter service.
	 * @param ConversionTracker $conversion_tracker Conversion tracker service.
	 * @param BulkConverter      $bulk_converter Bulk converter service.
	 * @param Logger             $logger Logger instance.
	 * @param WordPressProvider  $wordpress_provider WordPress provider instance.
	 */
	public function __construct(
		ImageConverter $image_converter,
		VideoConverter $video_converter,
		ConversionTracker $conversion_tracker,
		BulkConverter $bulk_converter,
		Logger $logger,
		WordPressProvider $wordpress_provider
	) {
		$this->image_converter = $image_converter;
		$this->video_converter = $video_converter;
		$this->conversion_tracker = $conversion_tracker;
		$this->bulk_converter = $bulk_converter;
		$this->logger = $logger;
		$this->wordpress_provider = $wordpress_provider;
	}

	/**
	 * Process attachment metadata update.
	 *
	 * @since 3.0.0
	 * @param array $data Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified metadata.
	 */
	public function process_metadata_update( $data, $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return $data;
		}
		
		$filetype = wp_check_filetype( $file_path );
		if ( empty( $filetype['ext'] ) ) {
			return $data;
		}

		// Process based on file type
		if ( $this->image_converter->is_supported_image( $file_path ) ) {
			if ( Settings::is_image_auto_convert_enabled() ) {
				$this->wordpress_provider->process_image_conversion( $attachment_id, $file_path );
			}
		} elseif ( $this->video_converter->is_supported_video( $file_path ) ) {
			if ( Settings::is_video_auto_convert_enabled() ) {
				$this->wordpress_provider->enqueue_video_processing( $attachment_id, $file_path );
			}
		}

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

		// Process based on file type
		if ( $this->image_converter->is_supported_image( $file ) ) {
			if ( Settings::is_image_auto_convert_enabled() ) {
				$this->wordpress_provider->process_image_conversion( $attachment_id, $file );
			}
		} elseif ( $this->video_converter->is_supported_video( $file ) ) {
			if ( Settings::is_video_auto_convert_enabled() ) {
				$this->wordpress_provider->enqueue_video_processing( $attachment_id, $file );
			}
		}

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

		// Only process supported images
		if ( $this->image_converter->is_supported_image( $filename ) ) {
			$this->wordpress_provider->process_image_conversion( (int) $post_id, $filename );
		}

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
		// Verify attachment still exists
		if ( ! get_post( $attachment_id ) ) {
			$this->logger->warning( "Video processing cron skipped: attachment {$attachment_id} no longer exists" );
			return;
		}

		// Verify file still exists
		if ( ! file_exists( $file_path ) ) {
			$this->logger->warning( "Video processing cron skipped: file not found for attachment {$attachment_id}: {$file_path}" );
			return;
		}

		// Verify it's still a supported video
		if ( ! $this->video_converter->is_supported_video( $file_path ) ) {
			$this->logger->warning( "Video processing cron skipped: unsupported video format for attachment {$attachment_id}" );
			return;
		}

		// Process the video conversion
		$this->wordpress_provider->process_video_conversion( $attachment_id, $file_path );
	}

	/**
	 * Process bulk conversion via cron.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function process_bulk_conversion_cron() {
		// Check if bulk conversion is enabled
		if ( ! Settings::is_bulk_conversion_enabled() ) {
			return;
		}

		// Check if auto-conversion is enabled
		if ( ! Settings::is_image_auto_convert_enabled() && ! Settings::is_video_auto_convert_enabled() ) {
			return;
		}

		// Process bulk conversion with small batch size for cron
		$results = $this->bulk_converter->process_bulk_conversion( 5 );

		$this->logger->info( 'Bulk conversion cron completed. Processed: ' . $results['processed'] . ', Converted: ' . $results['converted'] . ', Errors: ' . $results['errors'] );
	}

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
	public function process_manual_conversion( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! wp_check_filetype( $file_path )['ext'] ) {
			return false;
		}

		// Determine if it's an image or video
		if ( $this->image_converter->is_supported_image( $file_path ) ) {
			// Process image conversion synchronously
			$this->wordpress_provider->process_image_conversion( $attachment_id, $file_path );
			return true;
		} elseif ( $this->video_converter->is_supported_video( $file_path ) ) {
			// Enqueue video processing for async processing
			$this->wordpress_provider->enqueue_video_processing( $attachment_id, $file_path );
			return true;
		}

		return false;
	}
}

