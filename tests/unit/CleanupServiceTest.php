<?php
/**
 * Unit tests for CleanupService pure helpers.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\CleanupService;
use PHPUnit\Framework\TestCase;

/**
 * CleanupService unit tests (pure logic; no WordPress bootstrap).
 *
 * @since 4.2.0
 */
class CleanupServiceTest extends TestCase {

	/**
	 * Set up test constants.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}
	}

	/**
	 * Test stale job detection.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testIsJobStale() {
		$threshold = 6 * HOUR_IN_SECONDS;
		$now = time();

		$this->assertFalse( CleanupService::is_job_stale( 0, $threshold ) );
		$this->assertFalse( CleanupService::is_job_stale( $now - ( 5 * HOUR_IN_SECONDS ), $threshold ) );
		$this->assertTrue( CleanupService::is_job_stale( $now - ( 7 * HOUR_IN_SECONDS ), $threshold ) );
	}

	/**
	 * Test retry eligibility.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testIsRetryEligible() {
		$limit = 3;

		$this->assertTrue( CleanupService::is_retry_eligible( 0, $limit ) );
		$this->assertTrue( CleanupService::is_retry_eligible( 1, $limit ) );
		$this->assertTrue( CleanupService::is_retry_eligible( 2, $limit ) );
		$this->assertFalse( CleanupService::is_retry_eligible( 3, $limit ) );
		$this->assertFalse( CleanupService::is_retry_eligible( 0, 0 ) );
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
}
