<?php
/**
 * Action Scheduler service for Flux Media Optimizer.
 *
 * Manages Action Scheduler initialization and provides methods for scheduling
 * bulk conversion actions. Action Scheduler is excluded from Strauss namespacing
 * because it's a WordPress plugin/library that uses global functions.
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\Settings;

/**
 * Service for managing Action Scheduler integration.
 *
 * @since 3.0.0
 */
class ActionSchedulerService {

	/**
	 * Logger instance.
	 *
	 * @since 3.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Service locator instance.
	 *
	 * @since 3.0.0
	 * @var MediaProcessingServiceLocator
	 */
	private $service_locator;

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
	 * @param Logger                        $logger Logger instance.
	 * @param MediaProcessingServiceLocator $service_locator Service locator instance.
	 * @param BulkConverter                 $bulk_converter Bulk converter instance.
	 */
	public function __construct( Logger $logger, MediaProcessingServiceLocator $service_locator, BulkConverter $bulk_converter ) {
		$this->logger = $logger;
		$this->service_locator = $service_locator;
		$this->bulk_converter = $bulk_converter;
	}

	/**
	 * Initialize Action Scheduler.
	 *
	 * Verifies Action Scheduler is loaded and registers our action hooks.
	 * This method is called on the 'init' hook after Action Scheduler has initialized
	 * (Action Scheduler initializes on 'init' priority 1, this service on priority 10).
	 *
	 * @since 3.0.0
	 * @since 3.0.3 Action Scheduler service initialization moved to 'init' hook.
	 * @since 3.0.4 Removed redundant Action Scheduler initialization check since service
	 *              is registered after Action Scheduler initializes.
	 * @return void
	 */
	public function init() {
		// Verify Action Scheduler is loaded and ready
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->logger->error( 'Action Scheduler library not found. Please run "composer install" to install dependencies.' );
			return;
		}

		// Register action hooks
		// Action Scheduler is already initialized by this point (init priority 1 vs our priority 10)
		$this->register_action_hooks();
	}

	/**
	 * Register Action Scheduler action hooks.
	 *
	 * @since 3.0.0
	 * @since 4.3.0 Retry hook registration moved to ConversionRetryService::init().
	 * @return void
	 */
	private function register_action_hooks() {
		// Register bulk discovery action (handler checks if bulk conversion is enabled)
		add_action( 'flux_media_optimizer_bulk_discovery', [ $this, 'handle_bulk_discovery_action' ], 10, 1 );

		// Register single attachment conversion action
		add_action( 'flux_media_optimizer_convert_attachment', [ $this, 'handle_convert_attachment_action' ], 10, 1 );
	}

	/**
	 * Ensure bulk discovery action is scheduled.
	 *
	 * Checks if bulk conversion is enabled and schedules a recurring discovery action
	 * if not already scheduled. The discovery action runs every 20 minutes to check for
	 * unconverted attachments and schedule them for processing.
	 *
	 * @since 3.0.0
	 * @since 4.0.0 Revised to check if enabled and if already scheduled before scheduling.
	 * @return int|false Action ID or timestamp if already scheduled, false on failure.
	 */
	public function ensure_bulk_discovery_scheduled() {
		// Check if bulk conversion is enabled.
		if ( ! Settings::is_bulk_conversion_enabled() ) {
			return false;
		}

		// Check if discovery action is already scheduled.
		$next_scheduled = as_next_scheduled_action( 'flux_media_optimizer_bulk_discovery', [], ActionSchedulerGroups::MEDIA_OPTIMIZER );
		
		if ( $next_scheduled ) {
			// Already scheduled, return timestamp (indicates it's scheduled).
			return $next_scheduled;
		}

		// Schedule recurring discovery action (every 20 minutes).
		// Use unique group to ensure only one discovery action exists.
		$action_id = as_schedule_recurring_action(
			time(),
			20 * MINUTE_IN_SECONDS,
			'flux_media_optimizer_bulk_discovery',
			[],
			ActionSchedulerGroups::MEDIA_OPTIMIZER
		);

		if ( $action_id ) {
			$this->logger->debug( "Scheduled bulk conversion discovery action (ID: {$action_id}, interval: 20 minutes)" );
		} else {
			$this->logger->error( 'Failed to schedule bulk conversion discovery action' );
		}

		return $action_id;
	}

	/**
	 * Schedule single attachment conversion action.
	 *
	 * @since 3.0.0
	 * @since 3.0.4 Removed redundant Action Scheduler initialization check since
	 *              this method is only called after Action Scheduler is initialized.
	 * @since 4.0.0 Added $time parameter to allow scheduling with specific time.
	 * @param int $attachment_id Attachment ID to convert.
	 * @param int $time          Unix timestamp when the action should run.
	 * @return int|false Action ID on success, false on failure.
	 */
	public function schedule_attachment_conversion( $attachment_id, $time ) {
		// Check if action is already scheduled for this attachment.
		$next_scheduled = as_next_scheduled_action( 'flux_media_optimizer_convert_attachment', [ 'attachment_id' => $attachment_id ], ActionSchedulerGroups::MEDIA_OPTIMIZER );
		
		if ( $next_scheduled ) {
			// Already scheduled, return timestamp (indicates it's scheduled).
			return $next_scheduled;
		}

		// Schedule single action at specified time.
		$action_id = as_schedule_single_action(
			$time,
			'flux_media_optimizer_convert_attachment',
			[ 'attachment_id' => $attachment_id ],
			ActionSchedulerGroups::MEDIA_OPTIMIZER
		);

		if ( $action_id ) {
			$this->logger->debug( "Scheduled attachment conversion action (ID: {$action_id}, attachment: {$attachment_id}, time: {$time})" );
		} else {
			$this->logger->error( "Failed to schedule attachment conversion action for attachment {$attachment_id}" );
		}

		return $action_id;
	}

	/**
	 * Cancel scheduled attachment conversion action.
	 *
	 * @since 3.0.0
	 * @since 3.0.4 Removed redundant Action Scheduler initialization check since
	 *              this method is only called after Action Scheduler is initialized.
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function cancel_attachment_conversion( $attachment_id ) {
		as_unschedule_action( 'flux_media_optimizer_convert_attachment', [ 'attachment_id' => $attachment_id ], ActionSchedulerGroups::MEDIA_OPTIMIZER );
		$this->logger->debug( "Cancelled scheduled conversion action for attachment {$attachment_id}" );
	}

	/**
	 * Handle bulk discovery action.
	 *
	 * Delegates to BulkConverter to handle bulk discovery logic.
	 *
	 * @since 3.0.0
	 * @since 4.0.0 Delegated to BulkConverter::handle_bulk_discovery().
	 * @param array $args Action arguments (unused, kept for compatibility).
	 * @return void
	 */
	public function handle_bulk_discovery_action( $args = [] ) {
		// Delegate to BulkConverter for bulk discovery logic.
		$this->bulk_converter->handle_bulk_discovery( [ $this, 'schedule_attachment_conversion' ] );
	}

	/**
	 * Handle single attachment conversion action.
	 *
	 * Delegates to BulkConverter to process a single attachment.
	 *
	 * @since 3.0.0
	 * @since 4.0.0 Delegated to BulkConverter::process_attachment().
	 * @param int $attachment_id Attachment ID to convert.
	 * @return void
	 */
	public function handle_convert_attachment_action( $attachment_id ) {
		if ( ! $attachment_id ) {
			$this->logger->error( 'Attachment conversion action: Invalid attachment ID' );
			return;
		}

		// Delegate to BulkConverter for processing logic.
		$this->bulk_converter->process_attachment( $attachment_id );
	}
}
