<?php
/**
 * Shared conversion lifecycle orchestration.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;

/**
 * Owns trigger policy, processor dispatch, and terminal outcome interpretation.
 *
 * Upload, manual Re-convert, bulk, and automatic retry all call dispatch().
 *
 * @since 4.3.0
 */
final class ConversionOrchestrator {

	/**
	 * Meta flag set when local video work is deferred to cron.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	public const META_VIDEO_DEFERRED = '_flux_media_optimizer_video_deferred';

	/**
	 * Logger.
	 *
	 * @since 4.3.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Processor locator.
	 *
	 * @since 4.3.0
	 * @var MediaProcessingServiceLocator
	 */
	private $service_locator;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param Logger                        $logger          Logger.
	 * @param MediaProcessingServiceLocator $service_locator Locator.
	 */
	public function __construct( Logger $logger, MediaProcessingServiceLocator $service_locator ) {
		$this->logger          = $logger;
		$this->service_locator = $service_locator;
	}

	/**
	 * Dispatch conversion for the given request.
	 *
	 * @since 4.3.0
	 * @param ConversionRequest $request Request.
	 * @return ConversionDispatchResult
	 */
	public function dispatch( ConversionRequest $request ): ConversionDispatchResult {
		$attachment_id = $request->get_attachment_id();
		if ( $attachment_id <= 0 ) {
			return ConversionDispatchResult::failed( 'Invalid attachment ID.' );
		}

		if ( AttachmentMetaHandler::is_conversion_disabled( $attachment_id ) ) {
			return ConversionDispatchResult::skipped( 'Conversion disabled.' );
		}

		$job_state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
		if ( AttachmentMetaHandler::is_in_flight_job_state( $job_state ) ) {
			return ConversionDispatchResult::skipped( 'Conversion already in flight.', [ 'job_state' => $job_state ] );
		}

		$processor = $this->service_locator->get_processor();

		try {
			$success = (bool) $processor->process( $attachment_id, $request->get_file_path() );
		} catch ( \Throwable $e ) {
			$this->fail( $attachment_id, $e->getMessage(), [ 'trigger' => $request->get_trigger() ] );
			return ConversionDispatchResult::failed( $e->getMessage() );
		}

		return $this->interpret_processor_outcome( $attachment_id, $success, $request );
	}

	/**
	 * Mark terminal success: clear failure state and reset retry count.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function complete( int $attachment_id ): void {
		AttachmentMetaHandler::mark_conversion_succeeded( $attachment_id );
		delete_post_meta( $attachment_id, self::META_VIDEO_DEFERRED );
		$this->logger->info( "Conversion completed for attachment {$attachment_id}." );
	}

	/**
	 * Mark terminal failure (schedules retry via AttachmentMetaHandler action).
	 *
	 * @since 4.3.0
	 * @param int                  $attachment_id Attachment ID.
	 * @param string               $message       Error message.
	 * @param array<string, mixed> $context       Structured context for logs.
	 * @return void
	 */
	public function fail( int $attachment_id, string $message, array $context = [] ): void {
		delete_post_meta( $attachment_id, self::META_VIDEO_DEFERRED );

		if ( ! empty( $context ) ) {
			$this->logger->error(
				"Conversion failed for attachment {$attachment_id}: {$message}",
				$context
			);
		} else {
			$this->logger->error( "Conversion failed for attachment {$attachment_id}: {$message}" );
		}

		AttachmentMetaHandler::mark_conversion_failed( $attachment_id, $message );
	}

	/**
	 * Map processor boolean + job meta into an explicit dispatch result.
	 *
	 * @since 4.3.0
	 * @param int               $attachment_id Attachment ID.
	 * @param bool              $success       Processor return.
	 * @param ConversionRequest $request       Request.
	 * @return ConversionDispatchResult
	 */
	private function interpret_processor_outcome( int $attachment_id, bool $success, ConversionRequest $request ): ConversionDispatchResult {
		$error     = AttachmentMetaHandler::get_conversion_error( $attachment_id );
		$job_state = AttachmentMetaHandler::get_external_job_state( $attachment_id );

		if ( AttachmentMetaHandler::is_in_flight_job_state( $job_state ) ) {
			return ConversionDispatchResult::submitted(
				'Submitted to external processor.',
				[
					'job_state' => $job_state,
					'trigger'   => $request->get_trigger(),
				]
			);
		}

		if ( get_post_meta( $attachment_id, self::META_VIDEO_DEFERRED, true ) ) {
			return ConversionDispatchResult::deferred(
				'Video conversion deferred to async worker.',
				[ 'trigger' => $request->get_trigger() ]
			);
		}

		if ( ! $success || 'failed' === $job_state || '' !== (string) $error ) {
			$message = '' !== (string) $error ? (string) $error : 'Conversion failed.';
			if ( 'failed' !== $job_state ) {
				$this->fail( $attachment_id, $message, [ 'trigger' => $request->get_trigger() ] );
			}
			return ConversionDispatchResult::failed( $message );
		}

		$this->complete( $attachment_id );
		return ConversionDispatchResult::completed( 'Conversion completed.', [ 'trigger' => $request->get_trigger() ] );
	}
}
