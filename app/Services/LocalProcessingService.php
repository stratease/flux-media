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
		$attachment_id = (int) $attachment_id;

		if ( ! get_post( $attachment_id ) ) {
			$this->logger->warning( "Video processing cron skipped: attachment {$attachment_id} no longer exists" );
			delete_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED );
			return;
		}

		if ( ! file_exists( $file_path ) ) {
			$message = "Video processing cron failed: file not found for attachment {$attachment_id}: {$file_path}";
			$this->logger->warning( $message );
			delete_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED );
			AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
			return;
		}

		if ( ! $this->video_converter->is_supported_video( $file_path ) ) {
			$message = "Video processing cron failed: unsupported video format for attachment {$attachment_id}";
			$this->logger->warning( $message );
			delete_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED );
			AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
			return;
		}

		try {
			$results = $this->video_converter->process_video_conversion( $attachment_id, $file_path );
		} catch ( \Throwable $e ) {
			delete_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED );
			AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $e->getMessage() );
			$this->logger->error( "Video processing cron threw for attachment {$attachment_id}: " . $e->getMessage() );
			return;
		}

		delete_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED );

		$ok = is_array( $results ) && ! empty( $results['success'] );
		if ( ! $ok ) {
			$errors = is_array( $results ) ? implode( ', ', $results['errors'] ?? [] ) : '';
			AttachmentMetaHandler::mark_conversion_failed(
				$attachment_id,
				$errors ?: ( AttachmentMetaHandler::get_conversion_error( $attachment_id ) ?: 'Video conversion failed.' )
			);
			return;
		}

		AttachmentMetaHandler::mark_conversion_succeeded( $attachment_id );
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
			$this->fail_image_conversion(
				$attachment_id,
				"Attachment conversion failed: File not found for attachment {$attachment_id}"
			);
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

		$optimization_source_path = $this->image_converter->get_optimization_source_path( $attachment_id );
		if ( empty( $optimization_source_path ) || ! file_exists( $optimization_source_path ) ) {
			$optimization_source_path = $file_path;
		}

		// Process images using the best available source (HEIC original when core converted to JPEG).
		if ( $this->image_converter->is_supported_image( $optimization_source_path ) ) {
			return $this->process_image( $attachment_id, $optimization_source_path );
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
	 * File artifacts and conversion metadata/statistics are published together after every
	 * requested size/format succeeds. Partial failures roll back staged files without writing
	 * new meta or tracker rows for the failed attempt.
	 *
	 * @since 3.0.2
	 * @since 4.0.0 Removed auto-convert check (moved to upload hooks).
	 * @since 4.3.0 Defer original meta and ConversionTracker writes until artifact commit succeeds.
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

		// Check if this is a multi-frame source (animated GIF, HEIF sequence, etc.).
		$is_multi_frame = $this->image_converter->is_multi_frame_source( $attachment_id );

		// Ensure metadata exists and all sizes are generated.
		// Note: When called from process_metadata_update, metadata already exists.
		// Only generate if called from manual conversion or other contexts.
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $metadata ) || empty( $metadata['file'] ) ) {
			$metadata_source_path = get_attached_file( $attachment_id );
			if ( empty( $metadata_source_path ) || ! file_exists( $metadata_source_path ) ) {
				$metadata_source_path = $file_path;
			}

			// Generate metadata if it doesn't exist (this will create all sizes).
			// This can trigger wp_update_attachment_metadata hook again, but should_skip_processing will prevent duplicate processing.
			$metadata = wp_generate_attachment_metadata( $attachment_id, $metadata_source_path );
			if ( empty( $metadata ) ) {
				$this->fail_image_conversion(
					$attachment_id,
					"Failed to generate metadata for attachment {$attachment_id}"
				);
				return false;
			}
			wp_update_attachment_metadata( $attachment_id, $metadata );
			// Re-fetch metadata after generation to ensure we have the latest
			$metadata = wp_get_attachment_metadata( $attachment_id );
		}

		// WordPress leaves filesize-only meta when the image editor cannot decode HEIC (e.g. old libheif).
		if ( empty( $metadata['file'] ) && empty( $metadata['width'] ) ) {
			$decode_error = $this->describe_source_decode_failure( $file_path );
			$this->fail_image_conversion( $attachment_id, $decode_error );
			return false;
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( in_array( $extension, [ 'heic', 'heif', 'heics', 'heifs' ], true ) ) {
			$heif_probe = new HeifCapabilityProbe();
			if ( ! $heif_probe->can_decode_file( $file_path )
				&& ! $this->image_converter->is_multi_frame_source( $attachment_id ) ) {
				$this->fail_image_conversion(
					$attachment_id,
					$this->describe_source_decode_failure( $file_path )
				);
				return false;
			}
		}

		// Get all image sizes for this attachment (includes full + all registered sizes).
		$image_sizes = $this->get_all_image_paths_by_size( $attachment_id );
		
		// Multi-frame sources use the full-size file for all conversions to preserve animation.
		$full_size_source_path = null;
		if ( $is_multi_frame && isset( $image_sizes['full'] ) ) {
			$full_size_source_path = $image_sizes['full']['file_path'];
			$this->logger->info( "Using full-size multi-frame source for all size conversions: {$full_size_source_path}" );
		}
		
		if ( empty( $image_sizes ) ) {
			$this->logger->warning( "No image sizes found for attachment {$attachment_id}" );
			return false;
		}

		// Get settings and formats
		$settings = Settings::get_image_conversion_settings();
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

		$artifact_tx = new ConversionArtifactTransaction();
		$sizes_converted = 0;
		// Queue tracker rows until staged files commit so failed attempts leave prior stats intact.
		$pending_tracker_records = [];

		// Convert each image size (full, thumbnail, medium, large, and any custom sizes)
		foreach ( $image_sizes as $size_name => $size_data ) {
			$size_file_path = $size_data['file_path'];
			$size_width = $size_data['width'] ?? null;
			$size_height = $size_data['height'] ?? null;
			
			// For multi-frame sources, use the full-size source file instead of static thumbnails.
			$source_file_path = $size_file_path;
			if ( $is_multi_frame && $size_name !== 'full' && $full_size_source_path ) {
				$source_file_path = $full_size_source_path;
				$this->logger->info( "Using full-size multi-frame source for size '{$size_name}' conversion to preserve animation" );
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
			
			// Create destination paths for all requested formats beside the validated size path.
			$uploads_root = UploadPathGuard::get_uploads_basedir();
			if ( false === $uploads_root ) {
				$this->logger->warning(
					"Uploads directory unavailable; skipping destinations for attachment {$attachment_id}, size {$size_name}"
				);
				continue;
			}
			$destination_paths = [];
			foreach ( $image_formats as $format ) {
				$destination = trailingslashit( $size_file_dir ) . $size_file_name . '.' . $format;
				if ( ! UploadPathGuard::is_destination_within( $destination, $uploads_root ) ) {
					$this->logger->warning(
						"Skipping destination outside uploads for attachment {$attachment_id}, size {$size_name}, format {$format}: {$destination}"
					);
					continue;
				}
				$destination_paths[ $format ] = $destination;
			}

			if ( empty( $destination_paths ) ) {
				$this->logger->warning(
					"No safe destination paths for attachment {$attachment_id}, size {$size_name}"
				);
				continue;
			}
			
			// Add resize dimensions for multi-frame sources when generating intermediate sizes.
			$conversion_settings = $settings;
			if ( $is_multi_frame && $size_name !== 'full' && $size_width && $size_height ) {
				$conversion_settings['resize_width'] = $size_width;
				$conversion_settings['resize_height'] = $size_height;
				$this->logger->debug( "Adding resize dimensions for multi-frame source: {$size_width}x{$size_height}" );
			}
			
			// Process this size
			// Stage beside final destinations so prior known-good files stay until commit.
			$staging_paths = [];
			foreach ( $destination_paths as $format => $final_path ) {
				$staging_paths[ $format ] = $artifact_tx->stage( $final_path );
			}

			$results = $this->image_converter->process_image( $source_file_path, $staging_paths, $conversion_settings );

			if ( ! $results['success'] ) {
				$error_detail = implode( ', ', $results['errors'] ?? [] );
				$artifact_tx->rollback();
				$this->fail_image_conversion(
					$attachment_id,
					"Image conversion failed for attachment {$attachment_id}, size {$size_name}: {$error_detail}"
				);
				$this->logger->error(
					"Atomic image conversion rolled back for attachment {$attachment_id}",
					[
						'attachment_id' => $attachment_id,
						'size'          => $size_name,
						'error'         => $error_detail,
					]
				);
				return false;
			}

			// All requested formats for this size must succeed (all-or-nothing).
			foreach ( array_keys( $destination_paths ) as $required_format ) {
				if ( empty( $results['converted_files'][ $required_format ] ) ) {
					$artifact_tx->rollback();
					$this->fail_image_conversion(
						$attachment_id,
						"Image conversion incomplete for attachment {$attachment_id}, size {$size_name}, format {$required_format}"
					);
					$this->logger->error(
						"Atomic image conversion missing format; rolled back",
						[
							'attachment_id' => $attachment_id,
							'size'          => $size_name,
							'format'        => $required_format,
						]
					);
					return false;
				}
			}

			// Get file sizes for statistics tracking - use source file size for multi-frame sources.
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
				// Accumulate original details in memory; persist only after commit.
				$all_converted_files_by_size[ $size_name ]['original'] = [
					'url_or_path' => $original_file_url ?: $source_file_path,
					'filesize'    => $size_original_size,
				];
			}

			// Accumulate converted files in memory; persist meta/stats only after commit.
			foreach ( $results['converted_formats'] as $format ) {
				$staging_path = $results['converted_files'][ $format ] ?? '';
				$final_path   = $destination_paths[ $format ] ?? '';
				if ( empty( $staging_path ) || empty( $final_path ) ) {
					continue;
				}

				$converted_size = 0;
				if ( $wp_filesystem->exists( $staging_path ) ) {
					$converted_size = (int) $wp_filesystem->size( $staging_path );
				}
				if ( $converted_size <= 0 && file_exists( $staging_path ) ) {
					$converted_size = (int) filesize( $staging_path );
				}

				if ( $converted_size <= 0 ) {
					$artifact_tx->rollback();
					$this->fail_image_conversion(
						$attachment_id,
						"Image conversion produced empty file for attachment {$attachment_id}, size {$size_name}, format {$format}"
					);
					return false;
				}

				$pending_tracker_records[] = [
					'format'         => $format,
					'original_size'  => $size_original_size,
					'converted_size' => $converted_size,
					'size_name'      => $size_name,
				];

				$all_converted_files_by_size[ $size_name ][ $format ] = [
					'path'     => $final_path,
					'filesize' => $converted_size,
				];
			}

			$sizes_converted++;
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

		if ( empty( $all_converted_files_by_size ) && ! $disabled_formats_removed ) {
			$artifact_tx->rollback();
			$this->fail_image_conversion(
				$attachment_id,
				"Image conversion failed for attachment {$attachment_id}: No sizes were successfully converted"
			);
			return false;
		}

		// Publish staged files only after every requested size/format succeeded.
		if ( $artifact_tx->has_staged() && ! $artifact_tx->commit() ) {
			$artifact_tx->rollback();
			$this->fail_image_conversion(
				$attachment_id,
				"Image conversion failed for attachment {$attachment_id}: Unable to publish staged artifacts"
			);
			return false;
		}

		// Persist queued conversion statistics only after files are published.
		foreach ( $pending_tracker_records as $record ) {
			$this->conversion_tracker->record_conversion(
				$attachment_id,
				$record['format'],
				$record['original_size'],
				$record['converted_size'],
				$record['size_name']
			);
		}

		// Rewrite meta URLs against final (committed) destinations, including originals.
		foreach ( $all_converted_files_by_size as $size_name => $size_formats ) {
			if ( ! is_array( $size_formats ) ) {
				continue;
			}
			foreach ( $size_formats as $format => $file_data ) {
				if ( ! is_array( $file_data ) ) {
					continue;
				}

				if ( 'original' === $format ) {
					$url_or_path = $file_data['url_or_path'] ?? '';
					$filesize    = (int) ( $file_data['filesize'] ?? 0 );
					if ( '' === $url_or_path || $filesize <= 0 ) {
						continue;
					}
					AttachmentMetaHandler::set_file_url_and_size( $attachment_id, 'original', $size_name, $url_or_path, $filesize );
					$stored_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, 'original', $size_name );
					if ( $stored_url ) {
						$all_converted_files_by_size[ $size_name ]['original'] = [
							'url'      => $stored_url,
							'filesize' => $filesize,
						];
					}
					continue;
				}

				$final_path = $file_data['path'] ?? '';
				if ( empty( $final_path ) || ! file_exists( $final_path ) ) {
					continue;
				}
				$filesize = (int) ( $file_data['filesize'] ?? 0 );
				AttachmentMetaHandler::set_file_url_and_size( $attachment_id, $format, $size_name, $final_path, $filesize );
				$stored_url = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size_name );
				if ( $stored_url ) {
					$all_converted_files_by_size[ $size_name ][ $format ] = [
						'url'      => $stored_url,
						'filesize' => $filesize,
					];
				}
			}
		}

		AttachmentMetaHandler::set_converted_files_grouped_by_size( $attachment_id, $all_converted_files_by_size );

		$all_urls = [];
		foreach ( $all_converted_files_by_size as $size_data ) {
			if ( ! is_array( $size_data ) ) {
				continue;
			}
			foreach ( $size_data as $format => $file_data ) {
				if ( is_array( $file_data ) && isset( $file_data['url'] ) && is_string( $file_data['url'] ) && ! empty( $file_data['url'] ) ) {
					$all_urls[] = $file_data['url'];
				}
			}
		}
		if ( ! empty( $all_urls ) ) {
			AttachmentMetaHandler::set_file_urls( $attachment_id, array_unique( $all_urls ) );
		}

		AttachmentMetaHandler::set_converted_formats( $attachment_id, $final_formats );
		AttachmentMetaHandler::set_conversion_date_now( $attachment_id );
		AttachmentMetaHandler::mark_conversion_succeeded( $attachment_id );

		return true;
	}

	/**
	 * Persist a conversion failure for Media Library status and logging.
	 *
	 * @since 4.3.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $message       Error message.
	 * @return void
	 */
	private function fail_image_conversion( $attachment_id, $message ) {
		$this->logger->error( $message );
		AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
	}

	/**
	 * Build a decode-failure message for an undecodable HEIC/HEIF source.
	 *
	 * @since 4.3.0
	 * @param string $file_path Source path.
	 * @return string
	 */
	private function describe_source_decode_failure( $file_path ) {
		$detail = 'Imagick could not decode the HEIC/HEIF source. Modern iOS gain-map HEIC requires libheif 1.18.2 or newer.';

		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) && file_exists( $file_path ) ) {
			try {
				new \Imagick( $file_path );
			} catch ( \Exception $e ) {
				$detail = $e->getMessage();
			}
		}

		return "HEIC/HEIF decode failed for {$file_path}: {$detail}";
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
		// Mark deferred so orchestrator/retry treat enqueue as in-flight, not success.
		update_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED, '1' );
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
	 * @since 4.3.0 Skip size paths that resolve outside the uploads directory.
	 * @param int $attachment_id Attachment ID.
	 * @return array Array of size_name => ['file_path' => path, 'width' => int, 'height' => int].
	 */
	private function get_all_image_paths_by_size( $attachment_id ) {
		$sizes = [];

		// Initialize WordPress filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return $sizes;
		}

		$uploads_root = UploadPathGuard::get_uploads_basedir();
		if ( false === $uploads_root ) {
			$this->logger->warning(
				"Uploads directory unavailable while resolving image size paths for attachment {$attachment_id}"
			);
			return $sizes;
		}

		// Add full size using the optimization source (HEIC original when applicable).
		$attached_file_path = get_attached_file( $attachment_id );
		$optimization_source_path = $this->image_converter->get_optimization_source_path( $attachment_id );
		if ( empty( $optimization_source_path ) || ! $wp_filesystem->exists( $optimization_source_path ) ) {
			$optimization_source_path = $attached_file_path;
		}

		if (
			$optimization_source_path
			&& $wp_filesystem->exists( $optimization_source_path )
			&& UploadPathGuard::is_existing_path_within( $optimization_source_path, $uploads_root )
		) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			$sizes['full'] = [
				'file_path' => wp_normalize_path( $optimization_source_path ),
				'width' => $metadata['width'] ?? 0,
				'height' => $metadata['height'] ?? 0,
			];
		} elseif ( $optimization_source_path ) {
			$this->logger->warning(
				"Skipping optimization source outside uploads for attachment {$attachment_id}: {$optimization_source_path}"
			);
		}

		// Get all intermediate sizes relative to the attached working copy.
		$full_file_path = $attached_file_path;
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! empty( $metadata['sizes'] ) && ! empty( $full_file_path ) ) {
			// Get valid WordPress size names (includes 'thumbnail', 'medium', 'large', and custom sizes).
			$valid_sizes = get_intermediate_image_sizes();
			// Add 'full' to the list of valid sizes.
			$valid_sizes[] = 'full';

			// Build directory path using PHP dirname function.
			$file_dir = dirname( wp_normalize_path( $full_file_path ) );

			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				// Only process sizes that are valid WordPress registered sizes.
				if ( ! in_array( $size_name, $valid_sizes, true ) ) {
					continue;
				}

				if ( empty( $size_data['file'] ) || ! is_string( $size_data['file'] ) ) {
					continue;
				}

				// Only allow basename filenames; reject path segments and traversal.
				$file_name = wp_basename( $size_data['file'] );
				if ( $file_name !== $size_data['file'] || $file_name === '' || false !== strpos( $file_name, '..' ) ) {
					$this->logger->warning(
						"Skipping unsafe size filename for attachment {$attachment_id}, size {$size_name}: {$size_data['file']}"
					);
					continue;
				}

				$safe_name = sanitize_file_name( $file_name );
				if ( $safe_name === '' || $safe_name !== $file_name ) {
					$this->logger->warning(
						"Skipping unsanitized size filename for attachment {$attachment_id}, size {$size_name}: {$size_data['file']}"
					);
					continue;
				}

				// Build full path to size file using WordPress path functions.
				$size_file_path = trailingslashit( $file_dir ) . $safe_name;
				$size_file_path = wp_normalize_path( $size_file_path );

				if ( ! $wp_filesystem->exists( $size_file_path ) ) {
					continue;
				}

				if ( ! UploadPathGuard::is_existing_path_within( $size_file_path, $uploads_root ) ) {
					$this->logger->warning(
						"Skipping size path outside uploads for attachment {$attachment_id}, size {$size_name}: {$size_file_path}"
					);
					continue;
				}

				$sizes[ $size_name ] = [
					'file_path' => $size_file_path,
					'width' => $size_data['width'] ?? 0,
					'height' => $size_data['height'] ?? 0,
				];
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
	 * @since 4.3.0 Refuse to delete paths that resolve outside the uploads directory.
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if deletion was successful or not needed, false on error.
	 */
	public function delete_attachment( $attachment_id ) {
		// Get converted files by size.
		$converted_files_by_size = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );

		if ( empty( $converted_files_by_size ) ) {
			// No converted files, nothing to delete.
			$this->logger->debug( "No converted files found for attachment {$attachment_id}, skipping local deletion" );
			// Still clear meta in case there's stale data.
			AttachmentMetaHandler::clear_all_attachment_meta( $attachment_id );
			return true;
		}

		// Initialize WordPress filesystem.
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		global $wp_filesystem;

		$deleted_count = 0;
		$total_count = 0;

		// Delete local files from size-specific structure.
		$base_url = UploadPathGuard::get_uploads_baseurl();
		$base_dir = UploadPathGuard::get_uploads_basedir();
		if ( false === $base_url || false === $base_dir ) {
			$this->logger->warning(
				"Uploads directory unavailable while deleting converted files for attachment {$attachment_id}"
			);
			AttachmentMetaHandler::clear_all_attachment_meta( $attachment_id );
			return true;
		}

		foreach ( $converted_files_by_size as $size_name => $size_formats ) {
			if ( ! is_array( $size_formats ) ) {
				continue;
			}
			foreach ( $size_formats as $format => $data ) {
				// Extract URL/path from unified structure.
				$url_or_path = null;
				if ( is_array( $data ) && isset( $data['url'] ) ) {
					$url_or_path = $data['url'];
				} elseif ( is_string( $data ) ) {
					$url_or_path = $data;
				}

				// Skip if invalid.
				if ( ! is_string( $url_or_path ) || empty( $url_or_path ) ) {
					continue;
				}

				$file_path = null;
				if ( AttachmentMetaHandler::is_file_url( $url_or_path ) ) {
					$resolved = UploadPathGuard::local_upload_url_to_path( $url_or_path, $base_url, $base_dir );
					if ( false === $resolved ) {
						// CDN/external URL or unsafe local URL — skip deletion (meta cleared below).
						continue;
					}
					$file_path = $resolved;
				} else {
					$file_path = wp_normalize_path( $url_or_path );
					if ( ! UploadPathGuard::is_existing_path_within( $file_path, $base_dir ) ) {
						$this->logger->warning(
							"Refusing to delete path outside uploads for attachment {$attachment_id}: {$file_path}"
						);
						continue;
					}
				}

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

		// Clear all meta data (includes conversion tracking).
		AttachmentMetaHandler::clear_all_attachment_meta( $attachment_id );

		$this->logger->info( "Deleted {$deleted_count}/{$total_count} converted files for attachment {$attachment_id}" );
		return true;
	}
}

