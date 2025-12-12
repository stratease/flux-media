<?php
/**
 * Action Scheduler service for Flux Media Optimizer.
 *
 * Manages Action Scheduler initialization and provides methods for scheduling
 * bulk conversion actions. Uses Strauss-prefixed Action Scheduler to avoid
 * namespace collisions.
 *
 * @package FluxMedia
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

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
	 * Loads the Strauss-prefixed Action Scheduler library and ensures
	 * Action Scheduler is ready to use. Action Scheduler functions are global,
	 * so we just need to ensure the library is loaded.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function init() {
		// Check if Action Scheduler is already loaded (e.g., by WooCommerce or another plugin)
		if ( function_exists( 'as_schedule_single_action' ) ) {
			// Action Scheduler already loaded, just register our hooks
			$this->register_action_hooks();
			return;
		}

		// Load Action Scheduler from vendor-prefixed directory
		// Action Scheduler will be prefixed by Strauss but functions remain global
		$action_scheduler_file = FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor-prefixed/woocommerce/action-scheduler/action-scheduler.php';
		
		if ( file_exists( $action_scheduler_file ) ) {
			require_once $action_scheduler_file;
		} else {
			// Fallback: try vendor directory (if not yet prefixed)
			$action_scheduler_file_fallback = FLUX_MEDIA_OPTIMIZER_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
			if ( file_exists( $action_scheduler_file_fallback ) ) {
				require_once $action_scheduler_file_fallback;
			} else {
				$this->logger->error( 'Action Scheduler library not found. Please run "composer install" and then "composer run prefix-namespaces" to install and prefix Action Scheduler.' );
				return;
			}
		}

		// Verify Action Scheduler functions are available
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->logger->error( 'Action Scheduler functions not available after loading. Check Action Scheduler installation.' );
			return;
		}

		// Register action hooks
		$this->register_action_hooks();
	}

	/**
	 * Register Action Scheduler action hooks.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	private function register_action_hooks() {
		// Register bulk discovery action
		add_action( 'flux_media_optimizer_bulk_discovery', [ $this, 'handle_bulk_discovery_action' ], 10, 1 );
		
		// Register single attachment conversion action
		add_action( 'flux_media_optimizer_convert_attachment', [ $this, 'handle_convert_attachment_action' ], 10, 1 );
	}

	/**
	 * Schedule bulk conversion discovery action.
	 *
	 * This action will discover unconverted attachments and schedule
	 * individual conversion actions for each.
	 *
	 * @since 3.0.0
	 * @param int $batch_size Number of attachments to schedule per discovery run.
	 * @return int|false Action ID on success, false on failure.
	 */
	public function schedule_bulk_discovery( $batch_size = 50 ) {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			$this->logger->error( 'Action Scheduler functions not available. Action Scheduler may not be initialized.' );
			return false;
		}

		// Check if Action Scheduler data store is initialized
		// Action Scheduler must be fully initialized before calling its functions
		if ( ! did_action( 'action_scheduler_init' ) ) {
			// Action Scheduler not initialized yet, defer scheduling
			// Use a closure to capture batch_size
			$service = $this;
			add_action( 'action_scheduler_init', function() use ( $service, $batch_size ) {
				$service->schedule_bulk_discovery( $batch_size );
			}, 20 );
			return false;
		}

		// Check if discovery action is already scheduled
		$next_scheduled = as_next_scheduled_action( 'flux_media_optimizer_bulk_discovery', [ 'batch_size' => $batch_size ] );
		
		if ( $next_scheduled ) {
			// Already scheduled, return timestamp (indicates it's scheduled)
			return $next_scheduled;
		}

		// Schedule recurring discovery action (hourly)
		// Use unique group to ensure only one discovery action exists
		$action_id = as_schedule_recurring_action(
			time(),
			HOUR_IN_SECONDS,
			'flux_media_optimizer_bulk_discovery',
			[ 'batch_size' => $batch_size ],
			'flux-media-optimizer'
		);

		if ( $action_id ) {
			$this->logger->info( "Scheduled bulk conversion discovery action (ID: {$action_id}, batch size: {$batch_size})" );
		} else {
			$this->logger->error( 'Failed to schedule bulk conversion discovery action' );
		}

		return $action_id;
	}

	/**
	 * Unschedule bulk conversion discovery action.
	 *
	 * @since 3.0.0
	 * @return void
	 */
	public function unschedule_bulk_discovery() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		// Check if Action Scheduler data store is initialized
		// Action Scheduler must be fully initialized before calling its functions
		// The action_scheduler_init action fires when Action Scheduler is ready
		if ( ! did_action( 'action_scheduler_init' ) ) {
			// Action Scheduler not initialized yet, schedule unscheduling for later
			add_action( 'action_scheduler_init', [ $this, 'unschedule_bulk_discovery' ], 20 );
			return;
		}

		as_unschedule_all_actions( 'flux_media_optimizer_bulk_discovery' );
		$this->logger->info( 'Unscheduled bulk conversion discovery action' );
	}

	/**
	 * Schedule single attachment conversion action.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID to convert.
	 * @return int|false Action ID on success, false on failure.
	 */
	public function schedule_attachment_conversion( $attachment_id ) {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			$this->logger->error( 'Action Scheduler functions not available. Action Scheduler may not be initialized.' );
			return false;
		}

		// Check if action is already scheduled for this attachment
		$next_scheduled = as_next_scheduled_action( 'flux_media_optimizer_convert_attachment', [ 'attachment_id' => $attachment_id ] );
		
		if ( $next_scheduled ) {
			// Already scheduled, return timestamp (indicates it's scheduled)
			return $next_scheduled;
		}

		// Schedule single action (run as soon as possible)
		$action_id = as_schedule_single_action(
			time(),
			'flux_media_optimizer_convert_attachment',
			[ 'attachment_id' => $attachment_id ],
			'flux-media-optimizer'
		);

		if ( $action_id ) {
			$this->logger->info( "Scheduled attachment conversion action (ID: {$action_id}, attachment: {$attachment_id})" );
		} else {
			$this->logger->error( "Failed to schedule attachment conversion action for attachment {$attachment_id}" );
		}

		return $action_id;
	}

	/**
	 * Cancel scheduled attachment conversion action.
	 *
	 * @since 3.0.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function cancel_attachment_conversion( $attachment_id ) {
		if ( ! function_exists( 'as_unschedule_action' ) ) {
			return;
		}

		as_unschedule_action( 'flux_media_optimizer_convert_attachment', [ 'attachment_id' => $attachment_id ] );
		$this->logger->info( "Cancelled scheduled conversion action for attachment {$attachment_id}" );
	}

	/**
	 * Handle bulk discovery action.
	 *
	 * Discovers unconverted attachments and schedules individual conversion actions.
	 *
	 * @since 3.0.0
	 * @param array $args Action arguments.
	 * @return void
	 */
	public function handle_bulk_discovery_action( $args ) {
		$batch_size = isset( $args['batch_size'] ) ? (int) $args['batch_size'] : 50;

		// Check if bulk conversion is enabled
		if ( ! Settings::is_bulk_conversion_enabled() ) {
			$this->logger->info( 'Bulk conversion discovery skipped: bulk conversion is disabled' );
			return;
		}

		// Check if auto-conversion is enabled
		if ( ! Settings::is_image_auto_convert_enabled() && ! Settings::is_video_auto_convert_enabled() ) {
			$this->logger->info( 'Bulk conversion discovery skipped: auto-conversion is disabled' );
			return;
		}

		// Get unconverted media
		$unconverted_attachments = $this->bulk_converter->get_unconverted_media( $batch_size );

		if ( empty( $unconverted_attachments ) ) {
			$this->logger->info( 'Bulk conversion discovery: No unconverted attachments found' );
			return;
		}

		// Schedule individual conversion actions for each attachment
		$scheduled_count = 0;
		foreach ( $unconverted_attachments as $attachment_id ) {
			$action_id = $this->schedule_attachment_conversion( $attachment_id );
			if ( $action_id ) {
				$scheduled_count++;
			}
		}

		$this->logger->info( "Bulk conversion discovery: Scheduled {$scheduled_count} attachment conversion actions" );
	}

	/**
	 * Handle single attachment conversion action.
	 *
	 * Processes conversion for a single attachment.
	 *
	 * @since 3.0.0
	 * @param array $args Action arguments.
	 * @return void
	 */
	public function handle_convert_attachment_action( $args ) {
		$attachment_id = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;

		if ( ! $attachment_id ) {
			$this->logger->error( 'Attachment conversion action: Invalid attachment ID' );
			return;
		}

		// Check if conversion is disabled for this attachment
		if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
			$this->logger->info( "Attachment conversion action skipped: Conversion disabled for attachment {$attachment_id}" );
			return;
		}

		// Get processing service
		$processor = $this->service_locator->get_processor();
		if ( ! $processor ) {
			$this->logger->error( "Processor not available for attachment conversion action (attachment: {$attachment_id})" );
			return;
		}

		// Process the attachment
		$processor->process( $attachment_id );
	}
}

