<?php
/**
 * Media-aware conversion retry delay policy.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Contract for attempt-based retry delays that can vary by media type.
 *
 * @since 4.3.0
 */
interface RetryDelayPolicy {

	/**
	 * Delay in seconds before the given attempt should run.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @param int $attempt       1-based attempt number.
	 * @return int Seconds to wait, or 0 when the attempt is out of range.
	 */
	public function get_delay_seconds( int $attachment_id, int $attempt ): int;
}
