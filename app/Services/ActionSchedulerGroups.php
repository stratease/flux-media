<?php
/**
 * Action Scheduler group constants for Flux Media Optimizer.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Central Action Scheduler group identity used by every schedule/lookup/cancel call.
 *
 * @since 4.3.0
 */
final class ActionSchedulerGroups {

	/**
	 * Group for all Media Optimizer Action Scheduler actions.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const MEDIA_OPTIMIZER = 'flux-media-optimizer';
}
