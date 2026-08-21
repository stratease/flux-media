<?php
/**
 * Unified conversion retry service via Action Scheduler.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * Schedules and executes bounded automatic retries for failed conversions.
 *
 * Uses the processor currently selected by MediaProcessingServiceLocator so
 * retries follow live license and external-processing settings.
 *
 * @since 4.3.0
 */
class ConversionRetryService {

	/**
	 * Action Scheduler hook for a single attachment retry.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	const HOOK = 'flux_media_optimizer_retry_attachment';

	/**
	 * Logger instance.
	 *
	 * @since 4.3.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Service locator for current processor resolution.
	 *
	 * @since 4.3.0
	 * @var MediaProcessingServiceLocator
	 */
	private $service_locator;

	/**
	 * Retry delay policy.
	 *
	 * @since 4.3.0
	 * @var RetryDelayPolicy
	 */
	private $delay_policy;

	/**
	 * Shared conversion orchestrator.
	 *
	 * @since 4.3.0
	 * @var ConversionOrchestrator|null
	 */
	private $orchestrator;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param Logger                        $logger          Logger instance.
	 * @param MediaProcessingServiceLocator $service_locator Service locator.
	 * @param RetryDelayPolicy|null         $delay_policy    Optional delay policy.
	 * @param ConversionOrchestrator|null   $orchestrator    Optional orchestrator.
	 */
	public function __construct(
		Logger $logger,
		MediaProcessingServiceLocator $service_locator,
		?RetryDelayPolicy $delay_policy = null,
		?ConversionOrchestrator $orchestrator = null
	) {
		$this->logger          = $logger;
		$this->service_locator = $service_locator;
		$this->delay_policy    = null !== $delay_policy ? $delay_policy : new MediaAwareRetryDelayPolicy();
		$this->orchestrator    = $orchestrator;
	}

	/**
	 * Inject orchestrator after construction when wiring order requires it.
	 *
	 * @since 4.3.0
	 * @param ConversionOrchestrator $orchestrator Orchestrator.
	 * @return void
	 */
	public function set_orchestrator( ConversionOrchestrator $orchestrator ) {
		$this->orchestrator = $orchestrator;
	}

	/**
	 * Register failure listener and Action Scheduler handler.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function init() {
		add_action( AttachmentMetaHandler::ACTION_CONVERSION_FAILED, [ $this, 'schedule_retry' ], 10, 1 );
		add_action( self::HOOK, [ $this, 'handle_retry' ], 10, 1 );
	}

	/**
	 * Image-oriented delay for BC callers that lack an attachment context.
	 *
	 * @since 4.3.0
	 * @param int $attempt Next attempt number (1–3).
	 * @return int Delay in seconds, or 0 when attempt is out of range.
	 */
	public function get_retry_delay( $attempt ) {
		return $this->get_retry_delay_for_attachment( 0, (int) $attempt );
	}

	/**
	 * Media-aware delay for the given attachment and attempt.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @param int $attempt       Next attempt number (1–3).
	 * @return int Delay in seconds, or 0 when attempt is out of range.
	 */
	public function get_retry_delay_for_attachment( $attachment_id, $attempt ) {
		return $this->delay_policy->get_delay_seconds( (int) $attachment_id, (int) $attempt );
	}

	/**
	 * Maximum automatic retries after the initial failure.
	 *
	 * @since 4.3.0
	 * @return int
	 */
	public static function get_failed_job_retry_limit() {
		if ( defined( 'FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT' ) ) {
			return max( 0, (int) FLUX_MEDIA_OPTIMIZER_FAILED_JOB_RETRY_LIMIT );
		}

		return 3;
	}

	/**
	 * Whether another automatic retry is allowed.
	 *
	 * @since 4.3.0
	 * @param int      $retry_count Current completed retry attempts.
	 * @param int|null $limit       Maximum retries.
	 * @return bool
	 */
	public static function is_retry_eligible( $retry_count, $limit = null ) {
		$retry_count = (int) $retry_count;
		$limit       = null !== $limit ? (int) $limit : self::get_failed_job_retry_limit();

		if ( $limit <= 0 ) {
			return false;
		}

		return $retry_count < $limit;
	}

	/**
	 * Schedule the next automatic retry when eligible.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return int|false Action ID / existing timestamp, or false when not scheduled.
	 */
	public function schedule_retry( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return false;
		}

		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->logger->error(
				"Cannot schedule retry for attachment {$attachment_id}: Action Scheduler unavailable."
			);
			return false;
		}

		$retry_count = AttachmentMetaHandler::get_retry_count( $attachment_id );
		$limit       = self::get_failed_job_retry_limit();

		if ( ! self::is_retry_eligible( $retry_count, $limit ) ) {
			$this->logger->info(
				"Retry exhausted for attachment {$attachment_id} ({$retry_count}/{$limit})."
			);
			return false;
		}

		$args  = [ 'attachment_id' => $attachment_id ];
		$group = ActionSchedulerGroups::MEDIA_OPTIMIZER;

		$next_scheduled = as_next_scheduled_action( self::HOOK, $args, $group );
		if ( $next_scheduled ) {
			return $next_scheduled;
		}

		$next_attempt = $retry_count + 1;
		$delay        = $this->get_retry_delay_for_attachment( $attachment_id, $next_attempt );
		if ( $delay <= 0 ) {
			return false;
		}

		$timestamp = time() + $delay;
		$action_id = as_schedule_single_action( $timestamp, self::HOOK, $args, $group );

		if ( $action_id ) {
			$this->logger->info(
				"Scheduled conversion retry {$next_attempt}/{$limit} for attachment {$attachment_id} in {$delay}s (action {$action_id})."
			);
		} else {
			$this->logger->error(
				"Failed to schedule conversion retry for attachment {$attachment_id}."
			);
		}

		return $action_id;
	}

	/**
	 * Execute one automatic retry using the currently configured processor.
	 *
	 * Charges the retry budget for submitted, deferred, completed, or failed work.
	 * Skipped orchestrator outcomes leave the retry count unchanged.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function handle_retry( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			$this->logger->error( 'Conversion retry action: invalid attachment ID.' );
			return;
		}

		$retry_count = AttachmentMetaHandler::get_retry_count( $attachment_id );
		$limit       = self::get_failed_job_retry_limit();

		if ( ! self::is_retry_eligible( $retry_count, $limit ) ) {
			$this->logger->info(
				"Skipping conversion retry for attachment {$attachment_id}: retries exhausted ({$retry_count}/{$limit})."
			);
			return;
		}

		$job_state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
		if ( AttachmentMetaHandler::is_in_flight_job_state( $job_state ) ) {
			$this->logger->info(
				"Skipping conversion retry for attachment {$attachment_id}: job still in flight ({$job_state})."
			);
			return;
		}

		if ( get_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED, true ) ) {
			$this->logger->info(
				"Skipping conversion retry for attachment {$attachment_id}: video work still deferred."
			);
			return;
		}

		if ( $this->orchestrator ) {
			$result = $this->orchestrator->dispatch(
				new ConversionRequest( $attachment_id, ConversionRequest::TRIGGER_RETRY )
			);

			if ( $result->is_skipped() ) {
				$this->logger->info(
					"Conversion retry skipped for attachment {$attachment_id}: {$result->get_message()}"
				);
				return;
			}

			$attempt = AttachmentMetaHandler::increment_retry_count( $attachment_id );
			$this->logger->info(
				"Conversion retry {$attempt}/{$limit} outcome {$result->get_outcome()} for attachment {$attachment_id}."
			);

			if ( $result->is_in_flight() ) {
				return;
			}

			if ( $result->is_completed() ) {
				return;
			}

			if ( $result->is_failed() ) {
				$this->logger->warning(
					"Conversion retry {$attempt} failed for attachment {$attachment_id}."
				);
				return;
			}

			return;
		}

		// Fallback without orchestrator (tests / partial wiring).
		$attempt = AttachmentMetaHandler::increment_retry_count( $attachment_id );
		$this->logger->info(
			"Running conversion retry {$attempt}/{$limit} for attachment {$attachment_id}."
		);

		$processor = $this->service_locator->get_processor();
		$success   = false;
		try {
			$success = (bool) $processor->process( $attachment_id );
		} catch ( \Throwable $e ) {
			AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $e->getMessage() );
			return;
		}

		$job_state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
		if ( $success && AttachmentMetaHandler::is_in_flight_job_state( $job_state ) ) {
			return;
		}

		if ( $success && 'failed' !== $job_state && '' === AttachmentMetaHandler::get_conversion_error( $attachment_id ) ) {
			AttachmentMetaHandler::mark_conversion_succeeded( $attachment_id );
			return;
		}

		if ( 'failed' !== $job_state ) {
			$error_message = AttachmentMetaHandler::get_conversion_error( $attachment_id );
			if ( '' === (string) $error_message ) {
				$error_message = 'Conversion retry failed.';
			}
			AttachmentMetaHandler::mark_conversion_failed(
				$attachment_id,
				$error_message
			);
			return;
		}

		$this->schedule_retry( $attachment_id );
	}

	/**
	 * Reset retry cycle for a manual Re-convert.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function reset_cycle( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return;
		}

		if ( function_exists( 'as_unschedule_action' ) ) {
			as_unschedule_action(
				self::HOOK,
				[ 'attachment_id' => $attachment_id ],
				ActionSchedulerGroups::MEDIA_OPTIMIZER
			);
		}

		AttachmentMetaHandler::reset_retry_count( $attachment_id );
		AttachmentMetaHandler::clear_conversion_failure( $attachment_id );
		AttachmentMetaHandler::delete_external_job_lifecycle_meta( $attachment_id );
		delete_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED );

		$this->logger->info( "Reset conversion retry cycle for attachment {$attachment_id}." );
	}
}
