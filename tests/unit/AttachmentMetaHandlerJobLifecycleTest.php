<?php
/**
 * Unit tests for AttachmentMetaHandler job lifecycle helpers.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentMetaHandler;
use PHPUnit\Framework\TestCase;

/**
 * AttachmentMetaHandler job lifecycle unit tests.
 *
 * @since 4.2.0
 */
class AttachmentMetaHandlerJobLifecycleTest extends TestCase {

	/**
	 * Reset in-memory meta before each test.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fmo_test_post_meta'] = [];
	}

	/**
	 * Test queued state records started timestamp.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testQueuedStateRecordsStartedAt() {
		AttachmentMetaHandler::set_external_job_state( 101, 'queued' );

		$this->assertSame( 'queued', AttachmentMetaHandler::get_external_job_state( 101 ) );
		$this->assertGreaterThan( 0, AttachmentMetaHandler::get_external_job_started_at( 101 ) );
	}

	/**
	 * Test completed state clears lifecycle meta but keeps completed state.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testCompletedStateClearsLifecycleMeta() {
		AttachmentMetaHandler::set_external_job_state( 102, 'queued' );
		AttachmentMetaHandler::increment_external_job_retry_count( 102 );

		AttachmentMetaHandler::set_external_job_state( 102, 'completed' );

		$this->assertSame( 'completed', AttachmentMetaHandler::get_external_job_state( 102 ) );
		$this->assertSame( 0, AttachmentMetaHandler::get_external_job_started_at( 102 ) );
		$this->assertSame( 0, AttachmentMetaHandler::get_external_job_retry_count( 102 ) );
	}

	/**
	 * Unified retry counter increments and resets for the 4.3.0 retry budget.
	 *
	 * Legacy external counter migration is covered in ConversionRetryServiceTest.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Uses unified retry counter API.
	 * @return void
	 */
	public function testRetryCountIncrementAndReset() {
		$this->assertSame( 1, AttachmentMetaHandler::increment_retry_count( 103 ) );
		$this->assertSame( 2, AttachmentMetaHandler::increment_retry_count( 103 ) );
		$this->assertSame( 2, AttachmentMetaHandler::get_retry_count( 103 ) );

		AttachmentMetaHandler::reset_retry_count( 103 );
		$this->assertSame( 0, AttachmentMetaHandler::get_retry_count( 103 ) );
	}

	/**
	 * Test in-flight job state helpers.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testInFlightJobStateHelpers() {
		$this->assertSame(
			[ 'queued', 'processing' ],
			AttachmentMetaHandler::get_in_flight_job_states()
		);
		$this->assertTrue( AttachmentMetaHandler::is_in_flight_job_state( 'queued' ) );
		$this->assertTrue( AttachmentMetaHandler::is_in_flight_job_state( 'processing' ) );
		$this->assertFalse( AttachmentMetaHandler::is_in_flight_job_state( 'completed' ) );
		$this->assertFalse( AttachmentMetaHandler::is_in_flight_job_state( null ) );
	}

	/**
	 * HEIC decode failures persist error meta and failed job state for Media Library.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testMarkConversionFailedStoresErrorAndFailedState() {
		AttachmentMetaHandler::mark_conversion_failed( 416, 'Unable to decode HEIC source' );

		$this->assertSame( 'failed', AttachmentMetaHandler::get_external_job_state( 416 ) );
		$this->assertSame(
			'Unable to decode HEIC source',
			AttachmentMetaHandler::get_conversion_error( 416 )
		);
	}

	/**
	 * Clearing failure removes error meta and failed state.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testClearConversionFailureRemovesErrorMarkers() {
		AttachmentMetaHandler::mark_conversion_failed( 417, 'Decode failed' );
		AttachmentMetaHandler::clear_conversion_failure( 417 );

		$this->assertSame( '', AttachmentMetaHandler::get_conversion_error( 417 ) );
		$this->assertNull( AttachmentMetaHandler::get_external_job_state( 417 ) );
	}
}
