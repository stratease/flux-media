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
use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\AttachmentMetaHandler;

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
	
	 * @param ConversionTracker $conversion_tracker Conversion tracker service.
	 */
	public function __construct( Logger $logger, ImageConverter $image_converter, VideoConverter $video_converter, ConversionTracker $conversion_tracker ) {
		$this->logger = $logger;
		$this->image_converter = $image_converter;
		$this->video_converter = $video_converter;
		
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
		];

		// Get unconverted media files
		$unconverted_files = $this->get_unconverted_media( $batch_size );

		if ( empty( $unconverted_files ) ) {
			return $results;
		}

		foreach ( $unconverted_files as $attachment_id ) {
			$results['processed']++;


			try {
				$file_path = get_attached_file( $attachment_id );
				if ( ! $file_path || ! file_exists( $file_path ) ) {
					$results['errors']++;
					continue;
				}

				// Check if conversion is disabled for this attachment
				if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
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
	 * @since 3.0.0 Made public for use by Action Scheduler service.
	 * @param int $limit Maximum number of files to return.
	 * @return array Array of attachment IDs.
	 */
	public function get_unconverted_media( $limit = 10 ) {
		global $wpdb;

		// Get image attachments that haven't been converted and aren't disabled
		$image_attachments = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID 
			 FROM {$wpdb->posts} p 
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_flux_media_optimizer_converted_formats'
			 LEFT JOIN {$wpdb->postmeta} pm_disabled ON p.ID = pm_disabled.post_id AND pm_disabled.meta_key = '_flux_media_optimizer_conversion_disabled'
			 WHERE p.post_type = 'attachment' 
			 AND p.post_mime_type LIKE %s
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')
			 AND (pm_disabled.meta_value IS NULL OR pm_disabled.meta_value = '')
			 ORDER BY p.post_date DESC
			 LIMIT %d",
			'image/%',
			$limit
		) );

		// Get video attachments that haven't been converted and aren't disabled
		$video_attachments = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID 
			 FROM {$wpdb->posts} p 
			 LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_flux_media_optimizer_converted_formats'
			 LEFT JOIN {$wpdb->postmeta} pm_disabled ON p.ID = pm_disabled.post_id AND pm_disabled.meta_key = '_flux_media_optimizer_conversion_disabled'
			 WHERE p.post_type = 'attachment' 
			 AND p.post_mime_type LIKE %s
			 AND (pm.meta_value IS NULL OR pm.meta_value = '')
			 AND (pm_disabled.meta_value IS NULL OR pm_disabled.meta_value = '')
			 ORDER BY p.post_date DESC
			 LIMIT %d",
			'video/%',
			$limit
		) );

		// Combine and limit results
		$all_attachments = array_merge( $image_attachments, $video_attachments );
		return array_slice( $all_attachments, 0, $limit );
	}

	/**
	 * Process image conversion for bulk processing.
	 *
	 * Converts all WordPress image sizes (full, thumbnail, medium, large, etc.) to WebP/AVIF formats.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Source file path.
	 * @return array Conversion results.
	 */
	private function process_image_conversion( $attachment_id, $file_path ) {
		// Get all image sizes for this attachment
		$image_sizes = $this->get_all_image_paths_by_size( $attachment_id );
		
		if ( empty( $image_sizes ) ) {
			return [
				'success' => false,
				'errors' => ['No image sizes found'],
			];
		}

		// Get settings from WordPress
		$settings = [
			'webp_quality' => Settings::get_webp_quality(),
			'avif_quality' => Settings::get_avif_quality(),
			'avif_speed' => Settings::get_avif_speed(),
		];

		// Get image formats to convert
		$image_formats = Settings::get_image_formats();
		
		// Store converted files organized by size
		$all_converted_files_by_size = [];
		$all_converted_formats = [];
		$total_original_size = 0;

		// Initialize WordPress filesystem for file operations
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		
		global $wp_filesystem;
		
		if ( ! $wp_filesystem ) {
			return [
				'success' => false,
				'errors' => ['WordPress filesystem not available'],
			];
		}

		// Convert each image size
		foreach ( $image_sizes as $size_name => $size_data ) {
			$size_file_path = $size_data['file_path'];
			
			// Skip if size file doesn't exist (use WordPress filesystem)
			if ( ! $wp_filesystem->exists( $size_file_path ) ) {
				continue;
			}
			
			// Get size file info using PHP path functions
			$size_file_path_normalized = wp_normalize_path( $size_file_path );
			$size_file_dir = dirname( $size_file_path_normalized );
			$size_file_info = pathinfo( $size_file_path_normalized );
			$size_file_name = $size_file_info['filename'];
			
			// Create destination paths for this size
			$destination_paths = [];
			foreach ( $image_formats as $format ) {
				$destination_paths[ $format ] = trailingslashit( $size_file_dir ) . $size_file_name . '.' . $format;
			}
			
			// Process this size
			$results = $this->image_converter->process_image( $size_file_path, $destination_paths, $settings );
			
			if ( $results['success'] ) {
				// Get original size file size using WordPress filesystem
				$size_original_size = $wp_filesystem->exists( $size_file_path ) ? $wp_filesystem->size( $size_file_path ) : 0;
				
				// Store converted files for this size
				if ( ! isset( $all_converted_files_by_size[ $size_name ] ) ) {
					$all_converted_files_by_size[ $size_name ] = [];
				}
				
				// Record conversion and store files for each format
				foreach ( $results['converted_formats'] as $format ) {
					$converted_file_path = $results['converted_files'][ $format ] ?? '';
					if ( empty( $converted_file_path ) ) {
						continue;
					}
					
					$converted_size = $wp_filesystem->exists( $converted_file_path ) ? $wp_filesystem->size( $converted_file_path ) : 0;
					
					// Record conversion for statistics tracking (track all sizes for accurate savings calculation)
					$this->conversion_tracker->record_conversion( $attachment_id, $format, $size_original_size, $converted_size, $size_name );
					
					// Track full size original for total calculation
					if ( 'full' === $size_name ) {
						$total_original_size = $size_original_size;
					}
					
					// Store converted file
					$all_converted_files_by_size[ $size_name ][ $format ] = $converted_file_path;
					
					// Track formats
					if ( ! in_array( $format, $all_converted_formats, true ) ) {
						$all_converted_formats[] = $format;
					}
				}
			} else {
				$this->logger->warning( "Image conversion failed for attachment {$attachment_id}, size {$size_name}: " . implode( ', ', $results['errors'] ?? [] ) );
			}
		}

		// Update WordPress meta with all converted files (organized by size)
		if ( ! empty( $all_converted_files_by_size ) ) {
			// Store in size-specific meta
			AttachmentMetaHandler::set_converted_files_grouped_by_size( $attachment_id, $all_converted_files_by_size );
			
			
			AttachmentMetaHandler::set_converted_formats( $attachment_id, $all_converted_formats );
			AttachmentMetaHandler::set_conversion_date_now( $attachment_id );
			
			return [
				'success' => true,
				'converted_formats' => $all_converted_formats,
				'converted_files' => $all_converted_files_by_size,
			];
		}
		
		return [
			'success' => false,
			'errors' => ['No sizes were successfully converted'],
		];
	}

	/**
	 * Get all image paths by size for an attachment.
	 *
	 * Retrieves file paths for all WordPress image sizes including 'full' and all intermediate sizes.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of size_name => ['file_path' => path, 'width' => int, 'height' => int].
	 */
	private function get_all_image_paths_by_size( $attachment_id ) {
		$sizes = [];
		
		// Initialize WordPress filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		
		global $wp_filesystem;
		
		if ( ! $wp_filesystem ) {
			return $sizes;
		}
		
		// Add full size
		$full_file_path = get_attached_file( $attachment_id );
		if ( $full_file_path && $wp_filesystem->exists( $full_file_path ) ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			$sizes['full'] = [
				'file_path' => wp_normalize_path( $full_file_path ),
				'width' => $metadata['width'] ?? 0,
				'height' => $metadata['height'] ?? 0,
			];
		}
		
		// Get all intermediate sizes
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) && ! empty( $full_file_path ) ) {
			// Build directory path using PHP dirname function
			$file_dir = dirname( wp_normalize_path( $full_file_path ) );
			
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				// Build full path to size file using WordPress path functions
				$size_file_path = trailingslashit( $file_dir ) . $size_data['file'];
				$size_file_path = wp_normalize_path( $size_file_path );
				
				if ( $wp_filesystem->exists( $size_file_path ) ) {
					$sizes[ $size_name ] = [
						'file_path' => $size_file_path,
						'width' => $size_data['width'] ?? 0,
						'height' => $size_data['height'] ?? 0,
					];
				}
			}
		}
		
		return $sizes;
	}

	/**
	 * Process video conversion for bulk processing.
	 *
	 * @since 1.0.0
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
			'video_hybrid_approach' => Settings::is_video_hybrid_approach_enabled(),
			'video_av1_crf' => Settings::get_video_av1_crf(),
			'video_av1_cpu_used' => Settings::get_video_av1_cpu_used(),
			'video_webm_crf' => Settings::get_video_webm_crf(),
			'video_webm_speed' => Settings::get_video_webm_speed(),
		];

		// Create destination paths for requested formats
		$destination_paths = [];
		$video_formats = Settings::get_video_formats();
		
		// Ensure video_formats is an array
		if ( ! is_array( $video_formats ) ) {
			$video_formats = [];
		}
		
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
			// Videos don't have multiple sizes, so use 'full' as size_name
			foreach ( $results['converted_formats'] as $format ) {
				$converted_file_path = $results['converted_files'][ $format ] ?? '';
				$converted_size = file_exists( $converted_file_path ) ? filesize( $converted_file_path ) : 0;
				
				$this->conversion_tracker->record_conversion( $attachment_id, $format, $original_size, $converted_size, 'full' );
			}

			// Update WordPress meta
			AttachmentMetaHandler::set_converted_formats( $attachment_id, $results['converted_formats'] );
			AttachmentMetaHandler::set_conversion_date_now( $attachment_id );
		}

		return $results;
	}

}
