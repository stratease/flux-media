<?php
/**
 * Unit tests for conversion failure routing into the unified retry pipeline.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ConversionRetryService;
use FluxMedia\App\Services\MediaProcessingServiceLocator;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Failure routing unit tests.
 *
 * @since 4.3.0
 */
class ConversionFailureRoutingTest extends TestCase {

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

		$GLOBALS['fmo_test_post_meta']            = [];
		$GLOBALS['fmo_test_as_scheduled_actions'] = [];
		$GLOBALS['fmo_test_as_next_action']       = [];
		$GLOBALS['fmo_test_actions']              = [];
		$GLOBALS['fmo_test_action_callbacks']     = [];
	}

	/**
	 * Mark conversion failed schedules a retry when ConversionRetryService is listening.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testMarkConversionFailedSchedulesRetryViaAction() {
		$locator = $this->createMock( MediaProcessingServiceLocator::class );
		$service = new ConversionRetryService( Logger::get_instance(), $locator );
		$service->init();

		AttachmentMetaHandler::mark_conversion_failed( 501, 'Decode failed' );

		$this->assertNotEmpty( $GLOBALS['fmo_test_as_scheduled_actions'] );
		$this->assertSame( ConversionRetryService::HOOK, $GLOBALS['fmo_test_as_scheduled_actions'][0]['hook'] );
		$this->assertSame( [ 'attachment_id' => 501 ], $GLOBALS['fmo_test_as_scheduled_actions'][0]['args'] );
	}

	/**
	 * Failed action payload includes attachment ID and message.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testMarkConversionFailedFiresDomainAction() {
		AttachmentMetaHandler::mark_conversion_failed( 502, 'Cloud submit failed' );

		$this->assertNotEmpty( $GLOBALS['fmo_test_actions'] );
		$last = end( $GLOBALS['fmo_test_actions'] );
		$this->assertSame( AttachmentMetaHandler::ACTION_CONVERSION_FAILED, $last['hook'] );
		$this->assertSame( [ 502, 'Cloud submit failed' ], $last['args'] );
	}
}
