<?php
/**
 * Unit tests for MediaAwareRetryDelayPolicy.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\MediaAwareRetryDelayPolicy;
use PHPUnit\Framework\TestCase;

/**
 * MediaAwareRetryDelayPolicy unit tests.
 *
 * @since 4.3.0
 */
class MediaAwareRetryDelayPolicyTest extends TestCase {

	/**
	 * Reset stubs.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}
		$GLOBALS['fmo_test_mimetypes']      = [];
		$GLOBALS['fmo_test_attached_files'] = [];
	}

	/**
	 * Image delays are 1/5/15 minutes.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testImageDelaySequence() {
		$GLOBALS['fmo_test_mimetypes'][10] = 'image/jpeg';
		$policy = new MediaAwareRetryDelayPolicy();

		$this->assertSame( 1 * MINUTE_IN_SECONDS, $policy->get_delay_seconds( 10, 1 ) );
		$this->assertSame( 5 * MINUTE_IN_SECONDS, $policy->get_delay_seconds( 10, 2 ) );
		$this->assertSame( 15 * MINUTE_IN_SECONDS, $policy->get_delay_seconds( 10, 3 ) );
		$this->assertSame( 0, $policy->get_delay_seconds( 10, 4 ) );
	}

	/**
	 * Video delays are 5/30/120 minutes.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testVideoDelaySequence() {
		$GLOBALS['fmo_test_mimetypes'][11] = 'video/mp4';
		$policy = new MediaAwareRetryDelayPolicy();

		$this->assertSame( 5 * MINUTE_IN_SECONDS, $policy->get_delay_seconds( 11, 1 ) );
		$this->assertSame( 30 * MINUTE_IN_SECONDS, $policy->get_delay_seconds( 11, 2 ) );
		$this->assertSame( 120 * MINUTE_IN_SECONDS, $policy->get_delay_seconds( 11, 3 ) );
		$this->assertSame( 0, $policy->get_delay_seconds( 11, 4 ) );
	}
}
