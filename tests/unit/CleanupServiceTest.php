<?php
/**
 * Unit tests for CleanupService helpers and eligible-batch fairness.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\CleanupService;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * CleanupService unit tests.
 *
 * @since 4.2.0
 * @since 4.3.0 Adds pagination fairness coverage; retry eligibility lives in ConversionRetryServiceTest.
 */
class CleanupServiceTest extends TestCase {

	/**
	 * Set up test constants and stubs.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$GLOBALS['fmo_test_post_meta']      = [];
		$GLOBALS['fmo_test_wp_query_pages'] = [];
	}

	/**
	 * Test stale job detection.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testIsJobStale() {
		$threshold = 6 * HOUR_IN_SECONDS;
		$now       = time();

		$this->assertFalse( CleanupService::is_job_stale( 0, $threshold ) );
		$this->assertFalse( CleanupService::is_job_stale( $now - ( 5 * HOUR_IN_SECONDS ), $threshold ) );
		$this->assertTrue( CleanupService::is_job_stale( $now - ( 7 * HOUR_IN_SECONDS ), $threshold ) );
	}

	/**
	 * Test cleanup batch size default.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testGetCleanupBatchSizeDefault() {
		$this->assertSame( 50, CleanupService::get_cleanup_batch_size() );
	}

	/**
	 * Exhausted failures on early pages do not starve later eligible IDs.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetFailedAttachmentIdsPaginatesPastExhaustedFailures() {
		AttachmentMetaHandler::set_external_job_state( 901, 'failed' );
		AttachmentMetaHandler::increment_retry_count( 901 );
		AttachmentMetaHandler::increment_retry_count( 901 );
		AttachmentMetaHandler::increment_retry_count( 901 );

		AttachmentMetaHandler::set_external_job_state( 902, 'failed' );

		$GLOBALS['fmo_test_wp_query_pages'] = [
			1 => [ 901 ],
			2 => [ 902 ],
		];

		$service = new CleanupService( Logger::get_instance() );
		$ids     = $this->invoke_get_failed( $service, 1, 3 );

		$this->assertSame( [ 902 ], $ids );
	}

	/**
	 * Reflection helper for private get_failed_attachment_ids.
	 *
	 * @since 4.3.0
	 * @param CleanupService $service Service.
	 * @param int            $limit   Eligible batch limit.
	 * @param int            $retry   Retry limit.
	 * @return int[]
	 */
	private function invoke_get_failed( CleanupService $service, int $limit, int $retry ): array {
		$ref    = new ReflectionClass( $service );
		$method = $ref->getMethod( 'get_failed_attachment_ids' );
		$method->setAccessible( true );
		return $method->invoke( $service, $limit, $retry );
	}
}
