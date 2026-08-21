<?php
/**
 * Cleanup service for scheduled maintenance tasks.
 *
 * @package FluxMedia
 * @since 4.2.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * Handles daily cleanup: stale external jobs, bounded retries, and expired admin notices.
 *
 * @since 4.2.0
 */
class CleanupService {

	/**
	 * Logger instance.
	 *
	 * @since 4.2.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * External optimization provider instance.
	 *
	 * @since 4.2.0
	 * @var ExternalOptimizationProvider|null
	 */
	private $external_provider;

	/**
	 * Unified conversion retry service.
	 *
	 * @since 4.3.0
	 * @var ConversionRetryService|null
	 */
	private $conversion_retry_service;

	/**
	 * Constructor.
	 *
	 * @since 4.2.0
	 * @param Logger                            $logger            Logger instance.
	 * @param ExternalOptimizationProvider|null $external_provider External provider (stale detection).
	 */
	public function __construct( Logger $logger, ?ExternalOptimizationProvider $external_provider = null ) {
		$this->logger = $logger;
		$this->external_provider = $external_provider;
	}

	/**
	 * Inject the unified conversion retry service.
	 *
	 * @since 4.3.0
	 * @param ConversionRetryService $conversion_retry_service Retry service.
	 * @return void
	 */
	public function set_conversion_retry_service( ConversionRetryService $conversion_retry_service ) {
		$this->conversion_retry_service = $conversion_retry_service;
	}

	/**
	 * Register cleanup hooks.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function init() {
		add_action( 'flux_media_optimizer_cleanup', [ $this, 'run_cleanup' ] );
	}

	/**
	 * Run all cleanup operations.
	 *
	 * @since 4.2.0
	 * @return array Summary counts.
	 */
	public function run_cleanup() {
		$batch_size = self::get_cleanup_batch_size();

		do_action( 'flux_media_optimizer/cleanup_service/run_cleanup/before' );

		$stale_marked = $this->mark_stale_external_jobs_failed( $batch_size );
		$retry_results = $this->retry_failed_external_jobs( $batch_size );
		$notices_cleaned = $this->cleanup_expired_admin_notices();

		$summary = [
			'stale_marked_failed' => $stale_marked,
			'retries_attempted' => $retry_results['attempted'],
			'retries_succeeded' => $retry_results['succeeded'],
			'retries_exhausted' => $retry_results['exhausted'],
			'notices_cleaned' => $notices_cleaned,
		];

		$this->logger->info(
			'Cleanup completed: '
			. $stale_marked . ' stale jobs marked failed, '
			. $retry_results['attempted'] . ' retries attempted, '
			. $retry_results['succeeded'] . ' retries succeeded, '
			. $retry_results['exhausted'] . ' retries exhausted, '
			. $notices_cleaned . ' expired notices cleaned.'
		);

		do_action( 'flux_media_optimizer/cleanup_service/run_cleanup/after', $summary );

		return $summary;
	}

	/**
	 * Run bounded failed-job retries only.
	 *
	 * @since 4.2.0
	 * @deprecated 4.2.1 Daily cleanup owns retries; retained for backward compatibility.
	 * @return array Retry summary.
	 */
	public function run_retry_cleanup_only() {
		return $this->retry_failed_external_jobs( self::get_cleanup_batch_size() );
	}

	/**
	 * Mark stale in-flight external jobs as failed.
	 *
	 * @since 4.2.0
	 * @param int $limit Maximum attachments to process.
	 * @return int Number of jobs marked failed.
	 */
	public function mark_stale_external_jobs_failed( $limit = 50 ) {
		if ( ! Settings::is_external_processing_active() ) {
			return 0;
		}

		$limit = max( 1, (int) $limit );
		$threshold = self::get_stale_job_threshold();
		$cutoff = time() - $threshold;
		$marked = 0;

		$attachment_ids = $this->get_in_flight_attachment_ids( $limit );

		foreach ( $attachment_ids as $attachment_id ) {
			$started_at = AttachmentMetaHandler::get_external_job_started_at( $attachment_id );

			if ( $started_at <= 0 ) {
				$started_at = (int) get_post_modified_time( 'U', true, $attachment_id );
			}

			if ( $started_at <= 0 ) {
				AttachmentMetaHandler::mark_conversion_failed(
					$attachment_id,
					'External job missing started_at; marked stale.'
				);
				$marked++;
				$this->logger->warning( "Marked external job as failed for attachment {$attachment_id} (missing started_at)" );
				continue;
			}

			if ( ! self::is_job_stale( $started_at, $threshold ) ) {
				continue;
			}

			AttachmentMetaHandler::mark_conversion_failed(
				$attachment_id,
				'External job timed out and was marked stale.'
			);
			$marked++;
			$this->logger->warning( "Marked stale external job as failed for attachment {$attachment_id} (started: {$started_at}, cutoff: {$cutoff})" );
		}

		return $marked;
	}

	/**
	 * Enqueue Action Scheduler retries for failed jobs within the retry limit.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Enqueues via ConversionRetryService instead of direct cloud re-submit.
	 * @param int $limit Maximum attachments to process.
	 * @return array Retry summary.
	 */
	public function retry_failed_external_jobs( $limit = 50 ) {
		$summary = [
			'attempted' => 0,
			'succeeded' => 0,
			'exhausted' => 0,
		];

		if ( ! $this->conversion_retry_service ) {
			return $summary;
		}

		$limit = max( 1, (int) $limit );
		$retry_limit = self::get_failed_job_retry_limit();
		$attachment_ids = $this->get_failed_attachment_ids( $limit, $retry_limit );

		foreach ( $attachment_ids as $attachment_id ) {
			$summary['attempted']++;

			if ( $this->conversion_retry_service->schedule_retry( $attachment_id ) ) {
				$summary['succeeded']++;
			} else {
				$this->logger->warning( "Failed to enqueue conversion retry for attachment {$attachment_id}" );
			}
		}

		return $summary;
	}

	/**
	 * Remove expired admin notice transients.
	 *
	 * @since 4.2.0
	 * @return int Number of notice entries removed.
	 */
	public function cleanup_expired_admin_notices() {
		$notices = get_transient( 'flux_media_optimizer_notices' );
		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return 0;
		}

		$now = time();
		$ttl = HOUR_IN_SECONDS;
		$removed = 0;

		foreach ( $notices as $attachment_id => $notice ) {
			if ( ! is_array( $notice ) || empty( $notice['time'] ) || ( $now - (int) $notice['time'] ) > $ttl ) {
				unset( $notices[ $attachment_id ] );
				$removed++;
			}
		}

		if ( $removed > 0 ) {
			if ( empty( $notices ) ) {
				delete_transient( 'flux_media_optimizer_notices' );
			} else {
				set_transient( 'flux_media_optimizer_notices', $notices, $ttl );
			}
		}

		return $removed;
	}

	/**
	 * Determine whether an in-flight job is stale.
	 *
	 * @since 4.2.0
	 * @param int      $started_at Unix timestamp when the job started.
	 * @param int|null $threshold  Stale threshold in seconds.
	 * @return bool True if the job is stale.
	 */
	public static function is_job_stale( $started_at, $threshold = null ) {
		$started_at = (int) $started_at;
		if ( $started_at <= 0 ) {
			return false;
		}

		$threshold = null !== $threshold ? (int) $threshold : self::get_stale_job_threshold();
		if ( $threshold <= 0 ) {
			return false;
		}

		return ( time() - $started_at ) >= $threshold;
	}

	/**
	 * Determine whether a failed job is eligible for another retry.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Delegates to ConversionRetryService.
	 * @param int      $retry_count Current retry count.
	 * @param int|null $limit       Maximum retry attempts.
	 * @return bool True if another retry is allowed.
	 */
	public static function is_retry_eligible( $retry_count, $limit = null ) {
		return ConversionRetryService::is_retry_eligible( $retry_count, $limit );
	}

	/**
	 * Get stale job threshold in seconds.
	 *
	 * @since 4.2.0
	 * @return int Threshold in seconds.
	 */
	public static function get_stale_job_threshold() {
		if ( defined( 'FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD' ) ) {
			return max( 0, (int) FLUX_MEDIA_OPTIMIZER_STALE_JOB_THRESHOLD );
		}

		return 6 * HOUR_IN_SECONDS;
	}

	/**
	 * Get failed job retry limit.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Delegates to ConversionRetryService.
	 * @return int Maximum retry attempts.
	 */
	public static function get_failed_job_retry_limit() {
		return ConversionRetryService::get_failed_job_retry_limit();
	}

	/**
	 * Get cleanup batch size.
	 *
	 * @since 4.2.0
	 * @return int Batch size.
	 */
	public static function get_cleanup_batch_size() {
		if ( defined( 'FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE' ) ) {
			return max( 1, (int) FLUX_MEDIA_OPTIMIZER_CLEANUP_BATCH_SIZE );
		}

		return 50;
	}

	/**
	 * Get attachment IDs with in-flight external job states.
	 *
	 * @since 4.2.0
	 * @param int $limit Maximum results.
	 * @return int[] Attachment IDs.
	 */
	private function get_in_flight_attachment_ids( $limit ) {
		$query = new \WP_Query(
			[
				'post_type' => 'attachment',
				'post_status' => 'any',
				'posts_per_page' => $limit,
				'fields' => 'ids',
				'no_found_rows' => true,
				'meta_query' => [
					[
						'key' => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
						'value' => AttachmentMetaHandler::get_in_flight_job_states(),
						'compare' => 'IN',
					],
				],
			]
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Get failed attachment IDs for retry enqueue.
	 *
	 * Eligibility is filtered in PHP via get_retry_count() so legacy meta migrates correctly.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Drops retry-count meta_query; filters with unified get_retry_count().
	 * @param int $limit       Maximum results.
	 * @param int $retry_limit Maximum retry attempts (unused; kept for signature compatibility).
	 * @return int[] Attachment IDs.
	 */
	/**
	 * Query failed attachments, paginating until an eligible batch is filled.
	 *
	 * Exhausted retry rows cannot starve later eligible IDs: pages are scanned in
	 * deterministic ID order and filtered with unified get_retry_count().
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Paginates past exhausted failures until eligible batch is filled.
	 * @param int $limit       Maximum eligible results.
	 * @param int $retry_limit Maximum retry attempts.
	 * @return int[] Attachment IDs.
	 */
	private function get_failed_attachment_ids( $limit, $retry_limit ) {
		$limit       = max( 1, (int) $limit );
		$retry_limit = (int) $retry_limit;
		$eligible    = [];
		$page        = 1;
		$max_pages   = 50;

		while ( count( $eligible ) < $limit && $page <= $max_pages ) {
			$query = new \WP_Query(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => $limit,
					'paged'          => $page,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'meta_query'     => [
						[
							'key'     => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
							'value'   => 'failed',
							'compare' => '=',
						],
					],
				]
			);

			$ids = array_map( 'intval', $query->posts );
			if ( empty( $ids ) ) {
				break;
			}

			foreach ( $ids as $attachment_id ) {
				if ( count( $eligible ) >= $limit ) {
					break;
				}

				$retry_count = AttachmentMetaHandler::get_retry_count( $attachment_id );
				if ( ! self::is_retry_eligible( $retry_count, $retry_limit ) ) {
					continue;
				}

				$eligible[] = $attachment_id;
			}

			if ( count( $ids ) < $limit ) {
				break;
			}

			$page++;
		}

		return $eligible;
	}
}
