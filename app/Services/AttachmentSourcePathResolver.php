<?php
/**
 * Resolves the best on-disk source path for image optimization.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * Chooses between WordPress attached file and original upload path for optimization.
 *
 * Prefers the original HEIC/HEIF upload (via wp_get_original_image_path) when core has
 * converted the attached file to JPEG, so fidelity and multi-frame sequences are preserved.
 *
 * @since 4.3.0
 */
class AttachmentSourcePathResolver {

	/**
	 * Logger instance.
	 *
	 * @since 4.3.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Source format registry.
	 *
	 * @since 4.3.0
	 * @var SourceFormatRegistry
	 */
	private $source_format_registry;

	/**
	 * Multi-frame detector.
	 *
	 * @since 4.3.0
	 * @var MultiFrameDetector
	 */
	private $multi_frame_detector;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param Logger                  $logger                  Logger instance.
	 * @param SourceFormatRegistry|null $source_format_registry Optional format registry.
	 * @param MultiFrameDetector|null   $multi_frame_detector   Optional multi-frame detector.
	 */
	public function __construct(
		Logger $logger,
		SourceFormatRegistry $source_format_registry = null,
		MultiFrameDetector $multi_frame_detector = null
	) {
		$this->logger                 = $logger;
		$this->source_format_registry = $source_format_registry ?? new SourceFormatRegistry();
		$this->multi_frame_detector   = $multi_frame_detector ?? new MultiFrameDetector( $logger );
	}

	/**
	 * Get the WordPress attached file path (working copy).
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	public function get_attached_path( $attachment_id ) {
		if ( ! function_exists( 'get_attached_file' ) ) {
			return false;
		}

		$attached = get_attached_file( $attachment_id );

		return ( is_string( $attached ) && $attached !== '' ) ? $attached : false;
	}

	/**
	 * Get the path that should be used as the optimization input source.
	 *
	 * Defaults to the attached file. Uses wp_get_original_image_path() when the original
	 * upload is HEIC/HEIF or carries multi-frame animation that core flattened.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return string|false Absolute path or false when unavailable.
	 */
	public function get_optimization_source_path( $attachment_id ) {
		$attached = $this->get_attached_path( $attachment_id );
		if ( ! $attached ) {
			return false;
		}

		if ( ! file_exists( $attached ) ) {
			return false;
		}

		$original = $this->get_original_upload_path( $attachment_id, $attached );
		if ( ! $original || $original === $attached ) {
			return $attached;
		}

		if ( ! file_exists( $original ) ) {
			$this->logger->debug(
				"Original upload missing for attachment {$attachment_id}, using attached file: {$attached}"
			);
			return $attached;
		}

		if ( $this->should_prefer_original_for_heic( $attachment_id, $original ) ) {
			$this->logger->debug(
				"Using original HEIC/HEIF source for attachment {$attachment_id}: {$original}"
			);
			return $original;
		}

		if ( $this->original_has_multi_frame_animation( $attached, $original ) ) {
			$this->logger->debug(
				"Using original multi-frame source for attachment {$attachment_id}: {$original}"
			);
			return $original;
		}

		return $attached;
	}

	/**
	 * Static convenience wrapper for optimization source resolution.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	public static function get_optimization_source_path_for_attachment( $attachment_id ) {
		if ( ! function_exists( 'get_attached_file' ) ) {
			return false;
		}

		$logger   = Logger::get_instance();
		$resolver = new self( $logger );

		return $resolver->get_optimization_source_path( $attachment_id );
	}

	/**
	 * Resolve wp_get_original_image_path when available.
	 *
	 * @since 4.3.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $attached      Attached file path.
	 * @return string|false
	 */
	private function get_original_upload_path( $attachment_id, $attached ) {
		if ( ! function_exists( 'wp_get_original_image_path' ) ) {
			return $attached;
		}

		$original = wp_get_original_image_path( $attachment_id );

		return ( is_string( $original ) && $original !== '' ) ? $original : false;
	}

	/**
	 * Whether the original upload should be used for HEIC/HEIF fidelity.
	 *
	 * @since 4.3.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $original      Original upload path.
	 * @return bool
	 */
	private function should_prefer_original_for_heic( $attachment_id, $original ) {
		$original_extension = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		if ( $this->source_format_registry->requires_imagick( $original_extension ) ) {
			return true;
		}

		if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
			return false;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $metadata['original_image'] ) ) {
			return false;
		}

		$original_image_extension = strtolower(
			pathinfo( (string) $metadata['original_image'], PATHINFO_EXTENSION )
		);

		return $this->source_format_registry->requires_imagick( $original_image_extension );
	}

	/**
	 * Whether the original file carries animation lost on the attached working copy.
	 *
	 * @since 4.3.0
	 * @param string $attached Attached file path.
	 * @param string $original Original upload path.
	 * @return bool
	 */
	private function original_has_multi_frame_animation( $attached, $original ) {
		$attached_is_multi_frame = $this->multi_frame_detector->is_multi_frame( $attached );
		if ( $attached_is_multi_frame ) {
			return false;
		}

		return $this->multi_frame_detector->is_multi_frame( $original );
	}
}
