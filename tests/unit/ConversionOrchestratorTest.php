<?php
/**
 * Unit tests for ConversionOrchestrator outcome mapping.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ConversionDispatchResult;
use FluxMedia\App\Services\ConversionOrchestrator;
use FluxMedia\App\Services\ConversionRequest;
use FluxMedia\App\Services\MediaProcessingServiceLocator;
use FluxMedia\App\Services\ProcessingServiceInterface;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * ConversionOrchestrator unit tests.
 *
 * @since 4.3.0
 */
class ConversionOrchestratorTest extends TestCase {

	/**
	 * Reset stubs.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fmo_test_post_meta'] = [];
	}

	/**
	 * Completed local success maps to completed outcome and clears failure.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDispatchCompleted() {
		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willReturn( true );

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$logger = $this->createMock( Logger::class );
		$orch   = new ConversionOrchestrator( $logger, $locator );

		AttachmentMetaHandler::mark_conversion_failed( 501, 'old' );
		// Clear the failed state so interpret path treats success as completed.
		AttachmentMetaHandler::clear_conversion_failure( 501 );
		AttachmentMetaHandler::increment_retry_count( 501 );

		$result = $orch->dispatch( new ConversionRequest( 501, ConversionRequest::TRIGGER_MANUAL ) );

		$this->assertTrue( $result->is_completed() );
		$this->assertSame( ConversionDispatchResult::OUTCOME_COMPLETED, $result->get_outcome() );
		$this->assertSame( 0, AttachmentMetaHandler::get_retry_count( 501 ) );
	}

	/**
	 * In-flight external job state maps to submitted.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDispatchSubmittedWhenInFlight() {
		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willReturnCallback(
			static function ( $id ) {
				AttachmentMetaHandler::set_external_job_state( $id, 'queued' );
				return true;
			}
		);

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orch = new ConversionOrchestrator( $this->createMock( Logger::class ), $locator );
		$result = $orch->dispatch( new ConversionRequest( 502, ConversionRequest::TRIGGER_UPLOAD ) );

		$this->assertTrue( $result->is_in_flight() );
		$this->assertSame( ConversionDispatchResult::OUTCOME_SUBMITTED, $result->get_outcome() );
	}

	/**
	 * Video deferred meta maps to deferred (not terminal success).
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDispatchDeferredForVideo() {
		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willReturnCallback(
			static function ( $id ) {
				update_post_meta( $id, ConversionOrchestrator::META_VIDEO_DEFERRED, '1' );
				return true;
			}
		);

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orch = new ConversionOrchestrator( $this->createMock( Logger::class ), $locator );
		$result = $orch->dispatch( new ConversionRequest( 503, ConversionRequest::TRIGGER_RETRY ) );

		$this->assertTrue( $result->is_in_flight() );
		$this->assertSame( ConversionDispatchResult::OUTCOME_DEFERRED, $result->get_outcome() );
		$this->assertFalse( $result->is_completed() );
	}

	/**
	 * Disabled attachments are skipped without calling the processor.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDispatchSkippedWhenDisabled() {
		AttachmentMetaHandler::set_conversion_disabled( 504, true );

		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->expects( $this->never() )->method( 'process' );

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orch   = new ConversionOrchestrator( $this->createMock( Logger::class ), $locator );
		$result = $orch->dispatch( new ConversionRequest( 504, ConversionRequest::TRIGGER_RETRY ) );

		$this->assertTrue( $result->is_skipped() );
		$this->assertSame( ConversionDispatchResult::OUTCOME_SKIPPED, $result->get_outcome() );
	}

	/**
	 * In-flight job state before dispatch is skipped without calling the processor.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDispatchSkippedWhenAlreadyInFlight() {
		AttachmentMetaHandler::set_external_job_state( 505, 'processing' );

		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->expects( $this->never() )->method( 'process' );

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orch   = new ConversionOrchestrator( $this->createMock( Logger::class ), $locator );
		$result = $orch->dispatch( new ConversionRequest( 505, ConversionRequest::TRIGGER_RETRY ) );

		$this->assertTrue( $result->is_skipped() );
	}

	/**
	 * Processor false maps to failed outcome.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDispatchFailedWhenProcessorReturnsFalse() {
		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willReturn( false );

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orch   = new ConversionOrchestrator( $this->createMock( Logger::class ), $locator );
		$result = $orch->dispatch( new ConversionRequest( 506, ConversionRequest::TRIGGER_RETRY ) );

		$this->assertTrue( $result->is_failed() );
		$this->assertSame( 'failed', AttachmentMetaHandler::get_external_job_state( 506 ) );
	}

	/**
	 * Processor exceptions map to failed outcome.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDispatchFailedWhenProcessorThrows() {
		$processor = $this->createMock( ProcessingServiceInterface::class );
		$processor->method( 'process' )->willThrowException( new \RuntimeException( 'boom' ) );

		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$locator->method( 'get_processor' )->willReturn( $processor );

		$orch   = new ConversionOrchestrator( $this->createMock( Logger::class ), $locator );
		$result = $orch->dispatch( new ConversionRequest( 507, ConversionRequest::TRIGGER_RETRY ) );

		$this->assertTrue( $result->is_failed() );
		$this->assertSame( 'boom', AttachmentMetaHandler::get_conversion_error( 507 ) );
	}
}
