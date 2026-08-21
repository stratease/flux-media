<?php
/**
 * Conversion request payload for the shared orchestrator.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Immutable conversion request describing trigger and attachment context.
 *
 * @since 4.3.0
 */
final class ConversionRequest {

	/**
	 * Trigger: automatic upload processing.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const TRIGGER_UPLOAD = 'upload';

	/**
	 * Trigger: manual Convert / Re-convert.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const TRIGGER_MANUAL = 'manual';

	/**
	 * Trigger: bulk Action Scheduler conversion.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const TRIGGER_BULK = 'bulk';

	/**
	 * Trigger: automatic retry.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const TRIGGER_RETRY = 'retry';

	/**
	 * Attachment ID.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	private $attachment_id;

	/**
	 * Trigger name.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $trigger;

	/**
	 * Optional source file path.
	 *
	 * @since 4.3.0
	 * @var string|null
	 */
	private $file_path;

	/**
	 * Whether to skip auto-convert setting checks (manual paths).
	 *
	 * @since 4.3.0
	 * @var bool
	 */
	private $skip_auto_convert;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param int         $attachment_id     Attachment ID.
	 * @param string      $trigger           One of the TRIGGER_* constants.
	 * @param string|null $file_path         Optional file path.
	 * @param bool        $skip_auto_convert Skip auto-convert gates.
	 */
	public function __construct( int $attachment_id, string $trigger, ?string $file_path = null, bool $skip_auto_convert = false ) {
		$this->attachment_id     = $attachment_id;
		$this->trigger           = $trigger;
		$this->file_path         = $file_path;
		$this->skip_auto_convert = $skip_auto_convert;
	}

	/**
	 * Attachment ID.
	 *
	 * @since 4.3.0
	 * @return int
	 */
	public function get_attachment_id(): int {
		return $this->attachment_id;
	}

	/**
	 * Trigger name.
	 *
	 * @since 4.3.0
	 * @return string
	 */
	public function get_trigger(): string {
		return $this->trigger;
	}

	/**
	 * Optional file path.
	 *
	 * @since 4.3.0
	 * @return string|null
	 */
	public function get_file_path(): ?string {
		return $this->file_path;
	}

	/**
	 * Whether auto-convert checks should be skipped.
	 *
	 * @since 4.3.0
	 * @return bool
	 */
	public function should_skip_auto_convert(): bool {
		return $this->skip_auto_convert;
	}
}
