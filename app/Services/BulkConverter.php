<?php
/**
 * Bulk conversion service for processing existing media.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\App\Services\Logger;
use FluxMedia\App\Services\Settings;

/**
 * Service for bulk conversion of existing media files.
 *
 * @since 0.1.0
 */
class BulkConverter {

	/**
	 * Logger instance.
	 *
	 * @since 0.1.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Image converter instance.
	 *
	 * @since 0.1.0
	 * @var ImageConverter
	 */
	private $image_converter;

	/**
	 * Video converter instance.
	 *
	 * @since 0.1.0
	 * @var VideoConverter
	 */
	private $video_converter;

	/**
	 * Quota manager instance.
	 *
	 * @since 0.1.0
	 * @var QuotaManager
	 */
	private $quota_manager;

	/**
	 * Conversion tracker instance.
	 *
	 * @since 0.1.0
	 * @var ConversionTracker
	 */
	private $conversion_tracker;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 * @param Logger            $logger Logger instance.
	 * @param ImageConverter    $image_converter Image converter service.
	 * @param VideoConverter    $video_converter Video converter service.
	 * @param QuotaManager      $quota_manager Quota manager service.
	 * @param ConversionTracker $conversion_tracker Conversion tracker service.
	 */
	public function __construct( Logger $logger, ImageConverter $image_converter, VideoConverter $video_converter, QuotaManager $quota_manager, ConversionTracker $conversion_tracker ) {
		$this->logger = $logger;
		$this->image_converter = $image_converter;
		$this->video_converter = $video_converter;
		$this->quota_manager = $quota_manager;
		$this->conversion_tracker = $conversion_tracker;
	}

	/**
	 * Process bulk conversion of existing media.
	 *
	 * @since 0.1.0
	 * @param int $batch_size Number of files to process per batch.
	 * @return array Processing results.
	 */
	public function process_bulk_conversion( $batch_size = 10 ) {
		$results = [
			'processed' => 0,
			'converted' => 0,
			'errors' => 0,
			'quota_exceeded' => false,
		];

		// Get unconverted media files
		$unconverted_files = $this->get_unconverted_media( $batch_size );

		if ( empty( $unconverted_files ) ) {
			return $results;
		}

		foreach ( $unconverted_files as $attachment_id ) {
			$results['processed']++;

			// Check quota before processing
			if ( ! $this->quota_manager->can_convert( 'image' ) && ! $this->quota_manager->can_convert( 'video' ) ) {
				$results['quota_exceeded'] = true;
				$this->logger->warning( 'Quota exceeded during bulk conversion. Stopping processing.' );
				break;
			}

			try {
				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$results['errors']++;
					continue;
				}

				// Check if conversion is disabled for this attachment
				if ( get_post_meta( $attachment_id, '_flux_media_conversion_disabled', true ) ) {
					continue;
				}

				// Determine file type and process accordingly
				if ( $this->image_converter->is_supported_image( $file_path ) ) {
					$conversion_result = $this->process_image_conversion( $attachment_id, $file_path );
				} elseif ( $this->video_converter->is_supported_video( $file_path ) ) {
					$conversion_result = $this->process_video_conversion( $attachment_id, $file_path );
				} else {
					continue; // Skip unsupported files
				}

				if ( $conversion_result['success'] ) {
					$results['converted']++;
				} else {
					$results['errors']++;
					$this->logger->error( "Bulk conversion failed for attachment {$attachment_id}: " . implode( ', ', $conversion_result['errors'] ?? [] ) );
				}

			} catch ( \Exception $e ) {
				$results['errors']++;
				$this->logger->error( "Bulk conversion exception for attachment {$attachment_id}: " . $e->getMessage() );
			}
		}

		// Bulk conversion completed

		return $results;
	}

	/**
	 * Get unconverted media files.
	 *
	 * @since 0.1.0
	 * @param int $limit Maximum number of files to return.
	 * @return array Array of attachment IDs.
	 */
	private function get_unconverted_media( $limit = 10 ) {
		global $wpdb;

		// Get image attachments that haven't been converted and aren't disabled
		$image_attachments = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID 
			 FROM {$wpdb->posts} p 
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_flux_media_converted_formats'
			 LEFT JOIN {$wpdb->postmeta} pm_disabled ON p.ID = pm_disabled.post_id AND pm_disabled.meta_key = '_flux_media_conversion_disabled'
			 WHERE p.post_type = 'attachment' 
			 AND p.post_mime_type LIKE 'image/%'
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')
			 AND (pm_disabled.meta_value IS NULL OR pm_disabled.meta_value = '')
			 ORDER BY p.post_date DESC
			 LIMIT %d",
			$limit
		) );

		// Get video attachments that haven't been converted and aren't disabled
		$video_attachments = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID 
			 FROM {$wpdb->posts} p 
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_flux_media_converted_formats'
			 LEFT JOIN {$wpdb->postmeta} pm_disabled ON p.ID = pm_disabled.post_id AND pm_disabled.meta_key = '_flux_media_conversion_disabled'
			 WHERE p.post_type = 'attachment' 
			 AND p.post_mime_type LIKE 'video/%'
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')
			 AND (pm_disabled.meta_value IS NULL OR pm_disabled.meta_value = '')
			 ORDER BY p.post_date DESC
			 LIMIT %d",
			$limit
		) );

		// Combine and limit results
		$all_attachments = array_merge( $image_attachments, $video_attachments );
		return array_slice( $all_attachments, 0, $limit );
	}

	/**
	 * Process image conversion for bulk processing.
	 *
	 * @since 0.1.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Source file path.
	 * @return array Conversion results.
	 */
	private function process_image_conversion( $attachment_id, $file_path ) {
		// Get upload directory info
		$file_info = pathinfo( $file_path );
		$file_dir = $file_info['dirname'];
		$file_name = $file_info['filename'];

		// Get settings from WordPress
		$settings = [
			'hybrid_approach' => Settings::is_hybrid_approach_enabled(),
			'webp_quality' => Settings::get_webp_quality(),
			'avif_quality' => Settings::get_avif_quality(),
		];

		// Create destination paths for requested formats
		$destination_paths = [];
		$image_formats = Settings::get_image_formats();
		
		foreach ( $image_formats as $format ) {
			$destination_paths[ $format ] = $file_dir . '/' . $file_name . '.' . $format;
		}

		// Process the image
		$results = $this->image_converter->process_image( $file_path, $destination_paths, $settings );

		// Handle results
		if ( $results['success'] ) {
			// Get original file size
			$original_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;

			// Record conversion with file size data for each format
			// Quota tracking is handled automatically in record_conversion()
			foreach ( $results['converted_formats'] as $format ) {
				$converted_file_path = $results['converted_files'][ $format ] ?? '';
				$converted_size = file_exists( $converted_file_path ) ? filesize( $converted_file_path ) : 0;
				
				$this->conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $converted_size );
			}

			// Update WordPress meta
			update_post_meta( $attachment_id, '_flux_media_converted_formats', $results['converted_formats'] );
			update_post_meta( $attachment_id, '_flux_media_conversion_date', current_time( 'mysql' ) );
			update_post_meta( $attachment_id, '_flux_media_converted_files', $results['converted_files'] );
		}

		return $results;
	}

	/**
	 * Process video conversion for bulk processing.
	 *
	 * @since 0.1.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Source file path.
	 * @return array Conversion results.
	 */
	private function process_video_conversion( $attachment_id, $file_path ) {
		// Get upload directory info
		$file_info = pathinfo( $file_path );
		$file_dir = $file_info['dirname'];
		$file_name = $file_info['filename'];

		// Get settings from WordPress
		$settings = [
			'video_av1_crf' => Settings::get_video_av1_crf(),
			'video_webm_crf' => Settings::get_video_webm_crf(),
		];

		// Create destination paths for requested formats
		$destination_paths = [];
		$video_formats = Settings::get_video_formats();
		
		foreach ( $video_formats as $format ) {
			$destination_paths[ $format ] = $file_dir . '/' . $file_name . '.' . $format;
		}

		// Process the video
		$results = $this->video_converter->process_video( $file_path, $destination_paths, $settings );

		// Handle results
		if ( $results['success'] ) {
			// Get original file size
			$original_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;

			// Record conversion with file size data for each format
			// Quota tracking is handled automatically in record_conversion()
			foreach ( $results['converted_formats'] as $format ) {
				$converted_file_path = $results['converted_files'][ $format ] ?? '';
				$converted_size = file_exists( $converted_file_path ) ? filesize( $converted_file_path ) : 0;
				
				$this->conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $converted_size );
			}

			// Update WordPress meta
			update_post_meta( $attachment_id, '_flux_media_converted_formats', $results['converted_formats'] );
			update_post_meta( $attachment_id, '_flux_media_conversion_date', current_time( 'mysql' ) );
			update_post_meta( $attachment_id, '_flux_media_converted_files', $results['converted_files'] );
		}

		return $results;
	}

}
