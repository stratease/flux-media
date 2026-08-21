<?php
/**
 * Builds external SaaS job operation payloads from attachment metadata.
 *
 * @package FluxMedia
 * @since 4.2.1
 */

namespace FluxMedia\App\Services;

/**
 * Single source of truth for external processing operation arrays.
 *
 * @since 4.2.1
 */
class ExternalOperationsBuilder {

	/**
	 * Build operations for an attachment submit or retry.
	 *
	 * @since 4.2.1
	 * @param int $attachment_id Attachment post ID.
	 * @return array[] Operation definitions for ExternalApiClient::submit_job().
	 */
	public static function build_for_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return [];
		}

		$mimetype = get_post_mime_type( $attachment_id );
		if ( ! $mimetype ) {
			$file_path = AttachmentSourcePathResolver::get_optimization_source_path_for_attachment( $attachment_id );
			if ( ! $file_path ) {
				$file_path = get_attached_file( $attachment_id );
			}
			if ( $file_path ) {
				$mimetype = wp_check_filetype( $file_path )['type'] ?? '';
			}
		}

		$is_image = ! empty( $mimetype ) && strpos( $mimetype, 'image/' ) === 0;
		$is_video = ! empty( $mimetype ) && strpos( $mimetype, 'video/' ) === 0;

		if ( $is_image ) {
			return self::build_image_operations( $attachment_id );
		}

		if ( $is_video ) {
			return self::build_video_operations();
		}

		return [
			[
				'key_name' => 'full',
			],
		];
	}

	/**
	 * Build image conversion operations including full and registered sizes.
	 *
	 * Does not emit a `multi_frame` operation flag. The external service detects
	 * multi-frame sources from the single pull-file URL.
	 *
	 * @since 4.2.1
	 * @since 4.3.0 Dropped client `multi_frame` payload; SaaS detects animation from pull source.
	 * @param int $attachment_id Attachment post ID.
	 * @return array[]
	 */
	private static function build_image_operations( $attachment_id ) {
		$formats    = Settings::get_image_formats();
		$metadata   = wp_get_attachment_metadata( $attachment_id );
		$operations = [];

		$full_operation = [
			'formats'  => $formats,
			'key_name' => 'full',
		];

		if ( isset( $metadata['width'], $metadata['height'] ) ) {
			$full_operation['resize'] = [
				'width'  => (int) $metadata['width'],
				'height' => (int) $metadata['height'],
			];
		}

		$operations[] = $full_operation;

		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return $operations;
		}

		foreach ( $metadata['sizes'] as $size_name => $size_data ) {
			$operation = [
				'formats'  => $formats,
				'key_name' => $size_name,
			];

			if ( isset( $size_data['width'], $size_data['height'] ) ) {
				$operation['resize'] = [
					'width'  => (int) $size_data['width'],
					'height' => (int) $size_data['height'],
				];
			}

			$operations[] = $operation;
		}

		return $operations;
	}

	/**
	 * Build video conversion operations (full size only).
	 *
	 * @since 4.2.1
	 * @return array[]
	 */
	private static function build_video_operations() {
		return [
			[
				'formats'  => Settings::get_video_formats(),
				'key_name' => 'full',
			],
		];
	}
}
