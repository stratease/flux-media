<?php
/**
 * Unit tests for ConversionRetryService.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ActionSchedulerGroups;
use FluxMedia\App\Services\ConversionOrchestrator;
use FluxMedia\App\Services\ConversionRetryService;
use FluxMedia\App\Services\MediaProcessingServiceLocator;
use FluxMedia\App\Services\ProcessingServiceInterface;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * ConversionRetryService unit tests.
 *
 * @since 4.3.0
 */
class ConversionRetryServiceTest extends TestCase {

	/**
	 * Reset stubs before each test.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$GLOBALS['fmo_test_post_meta']            = [];
		$GLOBALS['fmo_test_as_scheduled_actions'] = [];
		$GLOBALS['fmo_test_as_next_action']       = [];
	}

	/**
	 * Service delay helper delegates to the media-aware policy for attempt 1.
	 *
	 * Full delay sequences live in MediaAwareRetryDelayPolicyTest.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetRetryDelayDelegatesFirstAttempt() {
		$service = $this->create_service();
		$this->assertSame( 1 * MINUTE_IN_SECONDS, $service->get_retry_delay( 1 ) );
	}

	/**
	 * Three automatic attempts after initial failure; fourth is exhausted.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testIsRetryEligibleAllowsThreeAttempts() {
		$this->assertTrue( ConversionRetryService::is_retry_eligible( 0, 3 ) );
		$this->assertTrue( ConversionRetryService::is_retry_eligible( 1, 3 ) );
		$this->assertTrue( ConversionRetryService::is_retry_eligible( 2, 3 ) );
		$this->assertFalse( ConversionRetryService::is_retry_eligible( 3, 3 ) );
		$this->assertFalse( ConversionRetryService::is_retry_eligible( 0, 0 ) );
	}

	/**
	 * Schedule retry queues attempt 1 at one-minute delay when count is 0.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testScheduleRetryQueuesFirstAttemptWithOneMinuteDelay() {
		AttachmentMetaHandler::mark_conversion_failed( 201, 'Decode failed' );

		$service   = $this->create_service();
		$before    = time();
		$action_id = $service->schedule_retry( 201 );

		$this->assertNotFalse( $action_id );
		$this->assertNotEmpty( $GLOBALS['fmo_test_as_scheduled_actions'] );

		$scheduled = $GLOBALS['fmo_test_as_scheduled_actions'][0];
		$this->assertSame( ConversionRetryService::HOOK, $scheduled['hook'] );
		$this->assertSame( [ 'attachment_id' => 201 ], $scheduled['args'] );
		$this->assertSame( ActionSchedulerGroups::MEDIA_OPTIMIZER, $scheduled['group'] );
		$this->assertGreaterThanOrEqual( $before + ( 1 * MINUTE_IN_SECONDS ), $scheduled['timestamp'] );
		$this->assertLessThanOrEqual( $before + ( 1 * MINUTE_IN_SECONDS ) + 2, $scheduled['timestamp'] );
		$this->assertSame( 0, AttachmentMetaHandler::get_retry_count( 201 ) );
	}

	/**
	 * Second scheduled retry uses five-minute delay after one completed attempt.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testScheduleRetryUsesFiveMinuteDelayForSecondAttempt() {
		AttachmentMetaHandler::mark_conversion_failed( 202, 'Fail' );
		AttachmentMetaHandler::increment_retry_count( 202 );

		$service = $this->create_service();
		$before  = time();
		$service->schedule_retry( 202 );

		$scheduled = $GLOBALS['fmo_test_as_scheduled_actions'][0];
		$this->assertSame( ConversionRetryService::HOOK, $scheduled['hook'] );
		$this->assertSame( [ 'attachment_id' => 202 ], $scheduled['args'] );
		$this->assertSame( ActionSchedulerGroups::MEDIA_OPTIMIZER, $scheduled['group'] );
		$this->assertGreaterThanOrEqual( $before + ( 5 * MINUTE_IN_SECONDS ), $scheduled['timestamp'] );
		$this->assertLessThanOrEqual( $before + ( 5 * MINUTE_IN_SECONDS ) + 2, $scheduled['timestamp'] );
	}

	/**
	 * Exhausted attachments are not scheduled.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testScheduleRetryReturnsFalseWhenExhausted() {
		AttachmentMetaHandler::mark_conversion_failed( 203, 'Fail' );
		AttachmentMetaHandler::increment_retry_count( 203 );
		AttachmentMetaHandler::increment_retry_count( 203 );
		AttachmentMetaHandler::increment_retry_count( 203 );

		$service = $this->create_service();
		$this->assertFalse( $service->schedule_retry( 203 ) );
		$this->assertEmpty( $GLOBALS['fmo_test_as_scheduled_actions'] );
	}

	/**
	 * Fallback path increments count before invoking the processor.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHandleRetryIncrementsBeforeProcess() {
		AttachmentMetaHandler::mark_conversion_failed( 204, 'Fail' );

		$observed_count = null;
		$processor      = $this->createMock( ProcessingServiceInterface::class );
		$processor->expects( $this->once() )
			->method( 'process' )
			->with( 204 )
			->willReturnCallback(
				static function () use ( &$observed_count ) {
					$observed_count = AttachmentMetaHandler::get_retry_count( 204 );
					AttachmentMetaHandler::set_external_job_state( 204, 'completed' );
					AttachmentMetaHandler::clear_conversion_failure( 204 );
					return true;
				}
			);

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$service = new ConversionRetryService( Logger::get_instance(), $locator );
		$service->handle_retry( 204 );

		$this->assertSame( 1, $observed_count );
	}

	/**
	 * Failed process schedules the next retry when still eligible.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHandleRetryReschedulesWhenStillFailed() {
		AttachmentMetaHandler::mark_conversion_failed( 205, 'Fail' );

		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willReturnCallback(
			static function () {
				AttachmentMetaHandler::mark_conversion_failed( 205, 'Still failing' );
				return false;
			}
		);

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$service = new ConversionRetryService( Logger::get_instance(), $locator );
		$service->handle_retry( 205 );

		$this->assertSame( 1, AttachmentMetaHandler::get_retry_count( 205 ) );
		$this->assertNotEmpty( $GLOBALS['fmo_test_as_scheduled_actions'] );
	}

	/**
	 * Orchestrator skipped outcomes do not consume a retry attempt.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHandleRetrySkippedDoesNotIncrementCount() {
		AttachmentMetaHandler::mark_conversion_failed( 208, 'Fail' );
		AttachmentMetaHandler::set_conversion_disabled( 208, true );

		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->expects( $this->never() )->method( 'process' );

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orchestrator = new ConversionOrchestrator( Logger::get_instance(), $locator );
		$service      = new ConversionRetryService( Logger::get_instance(), $locator );
		$service->set_orchestrator( $orchestrator );
		$service->handle_retry( 208 );

		$this->assertSame( 0, AttachmentMetaHandler::get_retry_count( 208 ) );
	}

	/**
	 * Orchestrator deferred outcomes consume one retry attempt.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHandleRetryDeferredIncrementsCount() {
		AttachmentMetaHandler::mark_conversion_failed( 209, 'Fail' );

		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willReturnCallback(
			static function ( $id ) {
				update_post_meta( $id, ConversionOrchestrator::META_VIDEO_DEFERRED, '1' );
				AttachmentMetaHandler::clear_conversion_failure( $id );
				return true;
			}
		);

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orchestrator = new ConversionOrchestrator( Logger::get_instance(), $locator );
		$service      = new ConversionRetryService( Logger::get_instance(), $locator );
		$service->set_orchestrator( $orchestrator );
		$service->handle_retry( 209 );

		$this->assertSame( 1, AttachmentMetaHandler::get_retry_count( 209 ) );
	}

	/**
	 * Orchestrator submitted outcomes consume one retry attempt.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHandleRetrySubmittedIncrementsCount() {
		AttachmentMetaHandler::mark_conversion_failed( 210, 'Fail' );

		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willReturnCallback(
			static function ( $id ) {
				AttachmentMetaHandler::set_external_job_state( $id, 'queued' );
				AttachmentMetaHandler::clear_conversion_failure( $id );
				return true;
			}
		);

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orchestrator = new ConversionOrchestrator( Logger::get_instance(), $locator );
		$service      = new ConversionRetryService( Logger::get_instance(), $locator );
		$service->set_orchestrator( $orchestrator );
		$service->handle_retry( 210 );

		$this->assertSame( 1, AttachmentMetaHandler::get_retry_count( 210 ) );
	}

	/**
	 * Reset cycle clears generic and legacy retry counters plus failure markers.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testResetCycleClearsCountersAndFailure() {
		AttachmentMetaHandler::mark_conversion_failed( 206, 'Fail' );
		AttachmentMetaHandler::increment_retry_count( 206 );
		update_post_meta( 206, AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_RETRY_COUNT, 2 );

		$service = $this->create_service();
		$service->reset_cycle( 206 );

		$this->assertSame( 0, AttachmentMetaHandler::get_retry_count( 206 ) );
		$this->assertSame( '', AttachmentMetaHandler::get_conversion_error( 206 ) );
		$this->assertNull( AttachmentMetaHandler::get_external_job_state( 206 ) );
	}

	/**
	 * Legacy external retry meta migrates into generic counter.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetRetryCountMigratesLegacyExternalMeta() {
		update_post_meta( 207, AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_RETRY_COUNT, 2 );

		$this->assertSame( 2, AttachmentMetaHandler::get_retry_count( 207 ) );
		$this->assertSame(
			2,
			(int) get_post_meta( 207, AttachmentMetaHandler::META_KEY_RETRY_COUNT, true )
		);
	}

	/**
	 * Build a retry service with a stub locator.
	 *
	 * @since 4.3.0
	 * @return ConversionRetryService
	 */
	private function create_service() {
		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		return new ConversionRetryService( Logger::get_instance(), $locator );
	}
}
