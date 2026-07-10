<?php
/**
 * Unit tests for AttachmentMetaHandler job lifecycle helpers.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\CleanupService;
use PHPUnit\Framework\TestCase;

/**
 * AttachmentMetaHandler job lifecycle unit tests.
 *
 * @since 4.2.0
 */
class AttachmentMetaHandlerJobLifecycleTest extends TestCase {

	/**
	 * In-memory post meta storage for stubs.
	 *
	 * @since 4.2.0
	 * @var array<int, array<string, mixed>>
	 */
	public static $post_meta = [];

	/**
	 * Bootstrap WordPress function stubs once.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! function_exists( 'get_post_meta' ) ) {
			/**
			 * Stub get_post_meta.
			 *
			 * @param int    $post_id Post ID.
			 * @param string $key Meta key.
			 * @param bool   $single Return single value.
			 * @return mixed
			 */
			function get_post_meta( $post_id, $key, $single = false ) {
				$post_id = (int) $post_id;
				if ( ! isset( AttachmentMetaHandlerJobLifecycleTest::$post_meta[ $post_id ][ $key ] ) ) {
					return $single ? '' : [];
				}

				$value = AttachmentMetaHandlerJobLifecycleTest::$post_meta[ $post_id ][ $key ];
				return $single ? $value : [ $value ];
			}
		}

		if ( ! function_exists( 'update_post_meta' ) ) {
			/**
			 * Stub update_post_meta.
			 *
			 * @param int    $post_id Post ID.
			 * @param string $key Meta key.
			 * @param mixed  $value Meta value.
			 * @return bool
			 */
			function update_post_meta( $post_id, $key, $value ) {
				$post_id = (int) $post_id;
				AttachmentMetaHandlerJobLifecycleTest::$post_meta[ $post_id ][ $key ] = $value;
				return true;
			}
		}

		if ( ! function_exists( 'delete_post_meta' ) ) {
			/**
			 * Stub delete_post_meta.
			 *
			 * @param int    $post_id Post ID.
			 * @param string $key Meta key.
			 * @return bool
			 */
			function delete_post_meta( $post_id, $key ) {
				$post_id = (int) $post_id;
				unset( AttachmentMetaHandlerJobLifecycleTest::$post_meta[ $post_id ][ $key ] );
				return true;
			}
		}

		if ( ! function_exists( 'absint' ) ) {
			/**
			 * Stub absint.
			 *
			 * @param mixed $value Value.
			 * @return int
			 */
			function absint( $value ) {
				return abs( (int) $value );
			}
		}
	}

	/**
	 * Reset in-memory meta before each test.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		self::$post_meta = [];
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
	 * Test retry count increment and cleanup helper thresholds.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function testRetryCountIncrementAndEligibility() {
		$this->assertSame( 1, AttachmentMetaHandler::increment_external_job_retry_count( 103 ) );
		$this->assertSame( 2, AttachmentMetaHandler::increment_external_job_retry_count( 103 ) );
		$this->assertTrue( CleanupService::is_retry_eligible( AttachmentMetaHandler::get_external_job_retry_count( 103 ), 3 ) );

		AttachmentMetaHandler::increment_external_job_retry_count( 103 );
		$this->assertFalse( CleanupService::is_retry_eligible( AttachmentMetaHandler::get_external_job_retry_count( 103 ), 3 ) );

		AttachmentMetaHandler::reset_external_job_retry_count( 103 );
		$this->assertSame( 0, AttachmentMetaHandler::get_external_job_retry_count( 103 ) );
	}
}
