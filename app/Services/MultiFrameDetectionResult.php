<?php
/**
 * Result object for multi-frame image detection.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Immutable multi-frame detection result.
 *
 * @since 4.3.0
 */
class MultiFrameDetectionResult {

	/**
	 * Whether multiple frames were detected.
	 *
	 * @since 4.3.0
	 * @var bool
	 */
	private $is_multi_frame;

	/**
	 * Detected frame count.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	private $frame_count;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param bool $is_multi_frame Multi-frame flag.
	 * @param int  $frame_count    Frame count.
	 */
	public function __construct( $is_multi_frame, $frame_count ) {
		$this->is_multi_frame = $is_multi_frame;
		$this->frame_count    = max( 0, (int) $frame_count );
	}

	/**
	 * Whether the source is multi-frame.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function is_multi_frame() {
		return $this->is_multi_frame;
	}

	/**
	 * Get the frame count.
	 *
	 * @since 4.3.0
	 * @return int
	 */
	public function get_frame_count() {
		return $this->frame_count;
	}
}
