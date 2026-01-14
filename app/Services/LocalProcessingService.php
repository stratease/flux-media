<?php
/**
 * Local processing service for media processing operations.
 *
 * @package FluxMedia\App\Services
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\ImageConverter;
use FluxMedia\App\Services\VideoConverter;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\BulkConverter;
use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\Settings;

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
	 * Constructor.
	 *
	 * @since 3.0.0
	 * @since 3.0.2 Removed WordPressProvider dependency to avoid circular dependencies.
	 * @param ImageConverter    $image_converter Image converter service.
	 * @param VideoConverter    $video_converter Video converter service.
	 * @param ConversionTracker $conversion_tracker Conversion tracker service.
	 * @param BulkConverter      $bulk_converter Bulk converter service.
	 * @param Logger             $logger Logger instance.
	 */
	public function __construct(
		ImageConverter $image_converter,
		VideoConverter $video_converter,
		ConversionTracker $conversion_tracker,
		BulkConverter $bulk_converter,
		Logger $logger
	) {
		$this->image_converter = $image_converter;
		$this->video_converter = $video_converter;
		$this->conversion_tracker = $conversion_tracker;
		$this->bulk_converter = $bulk_converter;
		$this->logger = $logger;
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
		// Use unified process() method
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
	 * @since 3.0.2 Updated to call video converter directly instead of WordPressProvider.
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

		// Process the video conversion directly via VideoConverter
		$this->video_converter->process_video_conversion( $attachment_id, $file_path );
	}



	/**
	 * Process attachment conversion.
	 *
	 * Unified method for processing attachment conversion. Handles both images and videos.
	 * Can be used for manual conversions, Action Scheduler tasks, or internal processing.
	 *
	 * @since 3.0.0
	 * @param int         $attachment_id Attachment ID.
	 * @param string|null $file_path     Optional file path. If null, will be retrieved from attachment meta.
	 *                                   This parameter is useful when processing is triggered before the file path
	 *                                   is stored in the attachment meta (e.g., during initial upload).
	 * @return bool True if conversion was initiated successfully, false otherwise.
	 */
	public function process( $attachment_id, $file_path = null ) {
		// Get file path if not provided
		// Note: We retrieve from meta here because sometimes processing is triggered before
		// the file path is stored in the attachment meta (e.g., during initial upload).
		// When file_path is provided (e.g., from process_file_update), we use it directly.
		if ( empty( $file_path ) ) {
			$file_path = get_attached_file( $attachment_id );
		}
		
		// Validate file path
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$this->logger->error( "Attachment conversion failed: File not found for attachment {$attachment_id}" );
			return false;
		}

		// Validate file type
		if ( ! wp_check_filetype( $file_path )['ext'] ) {
			$this->logger->warning( "Attachment conversion skipped: Invalid file type for attachment {$attachment_id}" );
			return false;
		}

		// Check if conversion is disabled for this attachment
		if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
			$this->logger->info( "Attachment conversion skipped: Conversion disabled for attachment {$attachment_id}" );
			return false;
		}

		// Process images
		if ( $this->image_converter->is_supported_image( $file_path ) ) {
			return $this->process_image( $attachment_id, $file_path );
		}

		// Process videos - always defer to cron for async processing
		if ( $this->video_converter->is_supported_video( $file_path ) ) {
			return $this->process_video( $attachment_id, $file_path );
		}

		// Unsupported file type
		$this->logger->warning( "Attachment conversion skipped: Unsupported file type for attachment {$attachment_id}" );
		return false;
	}

	/**
	 * Process image conversion.
	 *
	 * Converts all WordPress image sizes to WebP/AVIF formats. Supports incremental conversion
	 * (skips sizes already fully converted). Does not check disabled flag as explicit conversions should override.
	 * Auto-convert checks are handled in upload hooks; this method processes if called.
	 *
	 * @since 3.0.2
	 * @since 4.0.0 Removed auto-convert check (moved to upload hooks).
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path.
	 * @return bool True if conversion was initiated successfully, false otherwise.
	 */
	private function process_image( $attachment_id, $file_path ) {
		// Verify file exists before processing
		if ( ! file_exists( $file_path ) ) {
			$this->logger->warning( "Source file does not exist for attachment {$attachment_id}: {$file_path}" );
			return false;
		}

		// Check if this is an animated GIF using ImageConverter.
		$is_animated_gif = $this->image_converter->is_animated_gif( $attachment_id );

		// Ensure metadata exists and all sizes are generated.
		// Note: When called from process_metadata_update, metadata already exists.
		// Only generate if called from manual conversion or other contexts.
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $metadata ) || empty( $metadata['file'] ) ) {
			// Generate metadata if it doesn't exist (this will create all sizes).
			// This can trigger wp_update_attachment_metadata hook again, but should_skip_processing will prevent duplicate processing.
			$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
			if ( empty( $metadata ) ) {
				$this->logger->error( "Failed to generate metadata for attachment {$attachment_id}" );
				return false;
			}
			wp_update_attachment_metadata( $attachment_id, $metadata );
			// Re-fetch metadata after generation to ensure we have the latest
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}

		// Get all image sizes for this attachment (includes full + all registered sizes).
		$image_sizes = $this->get_all_image_paths_by_size( $attachment_id );
		
		// For animated GIFs, get the full-size source file path to use for all conversions.
		$full_size_source_path = null;
		if ( $is_animated_gif && isset( $image_sizes['full'] ) ) {
			$full_size_source_path = $image_sizes['full']['file_path'];
			$this->logger->info( "Using full-size animated GIF as source for all size conversions: {$full_size_source_path}" );
		}
		
		if ( empty( $image_sizes ) ) {
			$this->logger->warning( "No image sizes found for attachment {$attachment_id}" );
			return false;
		}

		// Get settings and formats
		$settings = [
			'webp_quality' => Settings::get_webp_quality(),
			'avif_quality' => Settings::get_avif_quality(),
			'avif_speed' => Settings::get_avif_speed(),
		];
		$image_formats = Settings::get_image_formats();
		
		if ( empty( $image_formats ) ) {
			$this->logger->warning( "No image formats configured for conversion. Attachment ID: {$attachment_id}" );
			return false;
		}

		// Initialize WordPress filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		
		global $wp_filesystem;
		
		if ( ! $wp_filesystem ) {
			$this->logger->error( "WordPress filesystem not available for attachment {$attachment_id}" );
			return false;
		}

		// Get existing converted files to preserve them during reconversion
		// This prevents losing existing formats (AVIF/WebP) when reconverting
		$all_converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
		if ( ! is_array( $all_converted_files_by_size ) ) {
			$all_converted_files_by_size = [];
		}
		
		// Clean up any invalid size names that don't match WordPress registered sizes
		// Get valid WordPress size names (includes 'thumbnail', 'medium', 'large', and custom sizes)
		$valid_sizes = get_intermediate_image_sizes();
		// Add 'full' to the list of valid sizes
		$valid_sizes[] = 'full';
		
		foreach ( array_keys( $all_converted_files_by_size ) as $size_name ) {
			// Only keep sizes that are valid WordPress registered sizes
			if ( ! in_array( $size_name, $valid_sizes, true ) ) {
				unset( $all_converted_files_by_size[ $size_name ] );
				$this->logger->info( "Removed invalid size entry (not a WordPress registered size): {$size_name}" );
			}
		}
		
		// Clean up formats that are no longer enabled in settings
		// Remove files, metadata, and tracking records for disabled formats
		$disabled_formats_removed = false;
		$disabled_formats_to_clean = [];
		
		foreach ( $all_converted_files_by_size as $size_name => $size_formats ) {
			if ( ! is_array( $size_formats ) ) {
				continue;
			}
			
			foreach ( $size_formats as $format => $file_path ) {
				// If this format is not in the enabled formats list, remove it
				if ( ! in_array( $format, $image_formats, true ) ) {
					// Track this format for conversion tracking cleanup
					if ( ! in_array( $format, $disabled_formats_to_clean, true ) ) {
						$disabled_formats_to_clean[] = $format;
					}
					
					// Delete the file if it exists
					if ( is_string( $file_path ) && ! empty( $file_path ) && $wp_filesystem->exists( $file_path ) ) {
						if ( $wp_filesystem->delete( $file_path ) ) {
							$this->logger->info( "Removed disabled format file: {$file_path} (format: {$format}, size: {$size_name})" );
						} else {
							$this->logger->warning( "Failed to remove disabled format file: {$file_path} (format: {$format}, size: {$size_name})" );
						}
					}
					
					// Remove from metadata structure
					unset( $all_converted_files_by_size[ $size_name ][ $format ] );
					$disabled_formats_removed = true;
				}
			}
			
			// Clean up empty size arrays
			if ( empty( $all_converted_files_by_size[ $size_name ] ) ) {
				unset( $all_converted_files_by_size[ $size_name ] );
			}
		}
		
		// Clean up conversion tracking records for disabled formats
		if ( ! empty( $disabled_formats_to_clean ) ) {
			$deleted_count = $this->conversion_tracker->delete_attachment_conversions_by_formats( $attachment_id, $disabled_formats_to_clean );
			if ( $deleted_count > 0 ) {
				$this->logger->info( "Removed {$deleted_count} conversion tracking record(s) for disabled formats: " . implode( ', ', $disabled_formats_to_clean ) );
			}
		}
		
		// Track formats - will be built from actual converted files after processing
		// This ensures we only track formats that actually exist

		// Convert each image size (full, thumbnail, medium, large, and any custom sizes)
		foreach ( $image_sizes as $size_name => $size_data ) {
			$size_file_path = $size_data['file_path'];
			$size_width = $size_data['width'] ?? null;
			$size_height = $size_data['height'] ?? null;
			
			// For animated GIFs, use the full-size source file instead of the static thumbnail.
			$source_file_path = $size_file_path;
			if ( $is_animated_gif && $size_name !== 'full' && $full_size_source_path ) {
				$source_file_path = $full_size_source_path;
				$this->logger->info( "Using full-size animated GIF source for size '{$size_name}' conversion to preserve animation" );
			}
			
			// Skip if source file doesn't exist
			if ( ! $wp_filesystem->exists( $source_file_path ) ) {
				$this->logger->warning( "Source file not found for attachment {$attachment_id}, size {$size_name}: {$source_file_path}" );
				continue;
			}
			
			// Get file path components for destination (use the size file path structure)
			$size_file_path_normalized = wp_normalize_path( $size_file_path );
			$size_file_dir = dirname( $size_file_path_normalized );
			$size_file_info = pathinfo( $size_file_path_normalized );
			$size_file_name = $size_file_info['filename'];
			
			// Create destination paths for all requested formats
			$destination_paths = [];
			foreach ( $image_formats as $format ) {
				$destination_paths[ $format ] = trailingslashit( $size_file_dir ) . $size_file_name . '.' . $format;
			}
			
			// Add resize dimensions to settings for animated GIFs if this is not the full size.
			$conversion_settings = $settings;
			if ( $is_animated_gif && $size_name !== 'full' && $size_width && $size_height ) {
				$conversion_settings['resize_width'] = $size_width;
				$conversion_settings['resize_height'] = $size_height;
				$this->logger->debug( "Adding resize dimensions for animated GIF: {$size_width}x{$size_height}" );
			}
			
			// Process this size
			$results = $this->image_converter->process_image( $source_file_path, $destination_paths, $conversion_settings );
			
			if ( ! $results['success'] ) {
				$this->logger->warning( "Image conversion failed for attachment {$attachment_id}, size {$size_name}: " . implode( ', ', $results['errors'] ?? [] ) );
				continue;
			}

			// Get file sizes for statistics tracking - use source file size for animated GIFs.
			$size_original_size = $wp_filesystem->size( $source_file_path );
			
			// Initialize size array if needed
			if ( ! isset( $all_converted_files_by_size[ $size_name ] ) ) {
				$all_converted_files_by_size[ $size_name ] = [];
			}
			
			// Store original file URL and size.
			// Get the original file URL for this size.
			$original_file_url = '';
			if ( 'full' === $size_name ) {
				$original_file_url = wp_get_attachment_url( $attachment_id );
			} else {
				// For other sizes, get the URL from metadata.
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( ! empty( $metadata['sizes'][ $size_name ]['file'] ) ) {
					$upload_dir = wp_upload_dir();
					$file_dir = dirname( $metadata['file'] );
					$original_file_url = $upload_dir['baseurl'] . '/' . $file_dir . '/' . $metadata['sizes'][ $size_name ]['file'];
				}
			}
			
			if ( $size_original_size > 0 ) {
				// Store original file details.
				// set_file_url_and_size() will generate URL automatically.
				AttachmentMetaHandler::set_file_url_and_size( $attachment_id, 'original', $size_name, $original_file_url ?: $source_file_path, $size_original_size );
				
				// Also add to local array so it's included when we save the batch.
				// Get the URL that was stored by set_file_url_and_size().
				$stored_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, 'original', $size_name );
				if ( $stored_url ) {
					$all_converted_files_by_size[ $size_name ]['original'] = [
						'url' => $stored_url,
						'filesize' => $size_original_size,
					];
				}
			}
			
			// Store converted files for each format
			foreach ( $results['converted_formats'] as $format ) {
				$converted_file_path = $results['converted_files'][ $format ] ?? '';
				if ( empty( $converted_file_path ) ) {
					continue;
				}
				
				// Get file size - ensure file exists and is readable
				$converted_size = 0;
				if ( $wp_filesystem->exists( $converted_file_path ) ) {
					$converted_size = $wp_filesystem->size( $converted_file_path );
					// Validate that we got a valid size (greater than 0)
					if ( $converted_size <= 0 ) {
						// Fallback to PHP filesize if wp_filesystem returns invalid size
						if ( file_exists( $converted_file_path ) ) {
							$converted_size = filesize( $converted_file_path );
						}
					}
				} elseif ( file_exists( $converted_file_path ) ) {
					// Fallback to PHP filesize if wp_filesystem doesn't exist but file does
					$converted_size = filesize( $converted_file_path );
				}
				
				// Record conversion for statistics tracking (track all sizes for accurate savings calculation)
				$this->conversion_tracker->record_conversion( $attachment_id, $format, $size_original_size, $converted_size, $size_name );
				
				// Store URL and size together using unified structure.
				// Only store if we have a valid file size (greater than 0)
				if ( $converted_size > 0 ) {
					// set_file_url_and_size() will generate URL automatically.
					AttachmentMetaHandler::set_file_url_and_size( $attachment_id, $format, $size_name, $converted_file_path, $converted_size );
					
					// Also store in local array for batch update.
					// Get the URL that was stored by set_file_url_and_size().
					$stored_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size_name );
					if ( $stored_url ) {
						$all_converted_files_by_size[ $size_name ][ $format ] = [
							'url' => $stored_url,
							'filesize' => $converted_size,
						];
					}
				}
			}
		}
		
		// Build final formats list - only include formats that actually exist in converted files
		$final_formats = [];
		foreach ( $all_converted_files_by_size as $size_formats ) {
			if ( ! is_array( $size_formats ) ) {
				continue;
			}
			foreach ( array_keys( $size_formats ) as $format ) {
				// Only include formats that are enabled AND exist in converted files
				if ( in_array( $format, $image_formats, true ) && ! in_array( $format, $final_formats, true ) ) {
					$final_formats[] = $format;
				}
			}
		}

		// Update WordPress meta with all converted files (organized by size)
		// Update even if we only removed disabled formats (not just when new conversions happened)
		if ( ! empty( $all_converted_files_by_size ) || $disabled_formats_removed ) {
			AttachmentMetaHandler::set_converted_files_grouped_by_size( $attachment_id, $all_converted_files_by_size );
			
			// Extract all URLs and store in dedicated meta field for efficient lookup
			// Store ALL URLs (local and external) in META_KEY_FILE_URLS
			$all_urls = [];
			foreach ( $all_converted_files_by_size as $size_data ) {
				if ( ! is_array( $size_data ) ) {
					continue;
				}
				foreach ( $size_data as $format => $file_data ) {
					if ( is_array( $file_data ) && isset( $file_data['url'] ) && is_string( $file_data['url'] ) && ! empty( $file_data['url'] ) ) {
						// Store all URLs (local and external).
						$all_urls[] = $file_data['url'];
					}
				}
			}
			// Store all URLs in dedicated meta field for efficient lookup
			if ( ! empty( $all_urls ) ) {
				AttachmentMetaHandler::set_file_urls( $attachment_id, array_unique( $all_urls ) );
			}
			
			// Update formats list - only include formats that actually exist
			AttachmentMetaHandler::set_converted_formats( $attachment_id, $final_formats );
			
			// Only update conversion date if we actually converted something (not just cleaned up)
			if ( ! $disabled_formats_removed || ! empty( $all_converted_files_by_size ) ) {
				AttachmentMetaHandler::set_conversion_date_now( $attachment_id );
			}
		} else {
			$this->logger->error( "Image conversion failed for attachment {$attachment_id}: No sizes were successfully converted" );
			return false;
		}

		return true;
	}

	/**
	 * Enqueue video processing via WordPress cron.
	 *
	 * Schedules a single-event cron job to process video conversion asynchronously.
	 *
	 * @since 3.0.2
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Source file path.
	 * @return void
	 */
	private function enqueue_video_processing( $attachment_id, $file_path ) {
		// Check if a cron job is already scheduled for this attachment
		$cron_hook = 'flux_media_optimizer_process_video';
		$cron_args = [ $attachment_id, $file_path ];
		
		// Check if this exact cron job is already scheduled
		$scheduled = wp_next_scheduled( $cron_hook, $cron_args );
		
		if ( ! $scheduled ) {
			// Schedule immediate processing (next cron run)
			wp_schedule_single_event( time(), $cron_hook, $cron_args );
		}
	}

	/**
	 * Process video conversion asynchronously.
	 *
	 * Enqueues video processing via cron to avoid blocking uploads.
	 * Auto-convert checks are handled in upload hooks; this method processes if called.
	 *
	 * @since 3.0.0
	 * @since 3.0.2 Simplified to always enqueue videos for async processing via cron.
	 * @since 4.0.0 Removed auto-convert check (moved to upload hooks).
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     File path.
	 * @return bool True if conversion was queued successfully, false otherwise.
	 */
	private function process_video( $attachment_id, $file_path ) {
		// Always enqueue video processing via cron for async processing
		// This prevents blocking during upload or manual conversion
		$this->enqueue_video_processing( $attachment_id, $file_path );
		return true;
	}

	/**
	 * Get all image paths by size for an attachment.
	 *
	 * Retrieves file paths for all WordPress image sizes including 'full' and all intermediate sizes.
	 * Uses WordPress filesystem API for file operations.
	 *
	 * @since 3.0.2
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
			// Get valid WordPress size names (includes 'thumbnail', 'medium', 'large', and custom sizes)
			$valid_sizes = get_intermediate_image_sizes();
			// Add 'full' to the list of valid sizes
			$valid_sizes[] = 'full';
			
			// Build directory path using PHP dirname function
			$file_dir = dirname( wp_normalize_path( $full_file_path ) );
			
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				// Only process sizes that are valid WordPress registered sizes
				if ( ! in_array( $size_name, $valid_sizes, true ) ) {
					continue;
				}
				
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
	 * Delete attachment from local service.
	 *
	 * Handles deletion of local converted files and clears all conversion-related meta data.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if deletion was successful or not needed, false on error.
	 */
	public function delete_attachment( $attachment_id ) {
		// Get converted files by size
		$converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
		
		if ( empty( $converted_files_by_size ) ) {
			// No converted files, nothing to delete
			$this->logger->debug( "No converted files found for attachment {$attachment_id}, skipping local deletion" );
			// Still clear meta in case there's stale data
			AttachmentMetaHandler::clear_all_attachment_meta( $attachment_id );
			return true;
		}

		// Initialize WordPress filesystem
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		
		global $wp_filesystem;
		
		$deleted_count = 0;
		$total_count = 0;

		// Delete local files from size-specific structure
		$upload_dir = wp_upload_dir();
		$base_url = $upload_dir['baseurl'];
		$base_dir = $upload_dir['basedir'];
		
		foreach ( $converted_files_by_size as $size_name => $size_formats ) {
			if ( ! is_array( $size_formats ) ) {
				continue;
			}
			foreach ( $size_formats as $format => $data ) {
				// Extract URL/path from unified structure
				$url_or_path = null;
				if ( is_array( $data ) && isset( $data['url'] ) ) {
					$url_or_path = $data['url'];
				} elseif ( is_string( $data ) ) {
					$url_or_path = $data;
				}
				
				// Skip if invalid
				if ( ! is_string( $url_or_path ) || empty( $url_or_path ) ) {
					continue;
				}
				
				// Convert URL to file path if it's a local WordPress upload URL
				$file_path = $url_or_path;
				if ( AttachmentMetaHandler::is_file_url( $url_or_path ) ) {
					// Check if it's a local WordPress upload URL
					if ( strpos( $url_or_path, $base_url ) === 0 ) {
						// Convert URL to file path
						$relative_path = str_replace( $base_url, '', $url_or_path );
						$relative_path = ltrim( $relative_path, '/' );
						$file_path = $base_dir . '/' . $relative_path;
					} else {
						// It's a CDN/external URL, skip deletion (only remove from meta)
						continue;
					}
				}
				
				// Validate file path exists before attempting deletion
				if ( empty( $file_path ) || ! is_string( $file_path ) ) {
					continue;
				}
				
				$total_count++;
				if ( $wp_filesystem && $wp_filesystem->exists( $file_path ) && $wp_filesystem->delete( $file_path ) ) {
					$deleted_count++;
					$this->logger->info( "Deleted converted file: {$file_path} (size: {$size_name}, format: {$format})" );
				} else {
					$this->logger->warning( "Failed to delete converted file: {$file_path} (size: {$size_name}, format: {$format})" );
				}
			}
		}

		// Clear all meta data (includes conversion tracking)
		AttachmentMetaHandler::clear_all_attachment_meta( $attachment_id );

		$this->logger->info( "Deleted {$deleted_count}/{$total_count} converted files for attachment {$attachment_id}" );
		return true;
	}
}

