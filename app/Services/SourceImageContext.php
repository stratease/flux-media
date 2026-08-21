<?php
/**
 * Value object describing a source image file for the conversion pipeline.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Immutable context for a source image used during processor selection and conversion.
 *
 * @since 4.3.0
 */
class SourceImageContext {

	/**
	 * Absolute path to the source file.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $path;

	/**
	 * Lowercase file extension without dot.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $extension;

	/**
	 * Detected MIME type, if known.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $mime;

	/**
	 * Whether the source contains multiple frames.
	 *
	 * @since 4.3.0
	 * @var bool
	 */
	private $is_multi_frame;

	/**
	 * Number of frames when multi-frame; 1 for static sources.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	private $frame_count;

	/**
	 * Whether Imagick is required to decode this source format.
	 *
	 * @since 4.3.0
	 * @var bool
	 */
	private $requires_imagick;

	/**
	 * Normalized source format constant from Converter.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $source_format;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param string $path             Source file path.
	 * @param string $extension        File extension.
	 * @param string $mime             MIME type.
	 * @param bool   $is_multi_frame   Multi-frame flag.
	 * @param int    $frame_count      Frame count.
	 * @param bool   $requires_imagick Imagick required flag.
	 * @param string $source_format    Source format identifier.
	 */
	public function __construct(
		$path,
		$extension,
		$mime,
		$is_multi_frame,
		$frame_count,
		$requires_imagick,
		$source_format
	) {
		$this->path             = $path;
		$this->extension        = $extension;
		$this->mime             = $mime;
		$this->is_multi_frame   = $is_multi_frame;
		$this->frame_count      = $frame_count;
		$this->requires_imagick = $requires_imagick;
		$this->source_format    = $source_format;
	}

	/**
	 * Get the source file path.
	 *
	 * @since 4.3.0
	 * @return string
	 */
	public function get_path() {
		return $this->path;
	}

	/**
	 * Get the file extension.
	 *
	 * @since 4.3.0
	 * @return string
	 */
	public function get_extension() {
		return $this->extension;
	}

	/**
	 * Get the MIME type.
	 *
	 * @since 4.3.0
	 * @return string
	 */
	public function get_mime() {
		return $this->mime;
	}

	/**
	 * Whether the source has multiple frames.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function is_multi_frame() {
		return $this->is_multi_frame;
	}

	/**
	 * Get the detected frame count.
	 *
	 * @since 4.3.0
	 * @return int
	 */
	public function get_frame_count() {
		return $this->frame_count;
	}

	/**
	 * Whether Imagick is required for this source.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function requires_imagick() {
		return $this->requires_imagick;
	}

	/**
	 * Get the normalized source format.
	 *
	 * @since 4.3.0
	 * @return string
	 */
	public function get_source_format() {
		return $this->source_format;
	}
}
