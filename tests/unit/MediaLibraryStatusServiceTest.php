<?php
/**
 * Unit tests for MediaLibraryStatusService.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\MediaLibraryStatusService;
use PHPUnit\Framework\TestCase;

/**
 * MediaLibraryStatusService unit tests (pure logic; no WordPress bootstrap).
 *
 * @since 4.2.0
 */
class MediaLibraryStatusServiceTest extends TestCase {

	/**
	 * Test optimized status derivation.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testDeriveStatusOptimized() {
		$status = MediaLibraryStatusService::derive_status(
			false,
			null,
			[ 'webp', 'avif' ],
			[]
		);

		$this->assertSame( MediaLibraryStatusService::STATUS_OPTIMIZED, $status );
	}

	/**
	 * Test pending status derivation.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testDeriveStatusPending() {
		$this->assertSame(
			MediaLibraryStatusService::STATUS_PENDING,
			MediaLibraryStatusService::derive_status( false, 'queued', [], [] )
		);
		$this->assertSame(
			MediaLibraryStatusService::STATUS_PENDING,
			MediaLibraryStatusService::derive_status( false, 'processing', [], [] )
		);
	}

	/**
	 * Test failed status derivation.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testDeriveStatusFailed() {
		$this->assertSame(
			MediaLibraryStatusService::STATUS_FAILED,
			MediaLibraryStatusService::derive_status( false, 'failed', [], [] )
		);
	}

	/**
	 * Test disabled status takes precedence.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testDeriveStatusDisabled() {
		$this->assertSame(
			MediaLibraryStatusService::STATUS_DISABLED,
			MediaLibraryStatusService::derive_status( true, 'failed', [ 'webp' ], [ 'full' => [] ] )
		);
	}

	/**
	 * Test unprocessed status derivation.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testDeriveStatusUnprocessed() {
		$this->assertSame(
			MediaLibraryStatusService::STATUS_UNPROCESSED,
			MediaLibraryStatusService::derive_status( false, null, [], [] )
		);
	}

	/**
	 * Test status key normalization.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testNormalizeStatusKey() {
		$this->assertSame(
			MediaLibraryStatusService::STATUS_UNPROCESSED,
			MediaLibraryStatusService::normalize_status_key( 'unprocessed' )
		);
		$this->assertSame( '', MediaLibraryStatusService::normalize_status_key( 'invalid-status' ) );
	}

	/**
	 * Test filter meta query generation.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testBuildFilterMetaQuery() {
		$failed_query = MediaLibraryStatusService::build_filter_meta_query( MediaLibraryStatusService::STATUS_FAILED );
		$this->assertNotEmpty( $failed_query );
		$this->assertSame( 'failed', $failed_query[0]['value'] );

		$unprocessed_query = MediaLibraryStatusService::build_filter_meta_query( MediaLibraryStatusService::STATUS_UNPROCESSED );
		$this->assertSame( 'AND', $unprocessed_query['relation'] );
	}
}
