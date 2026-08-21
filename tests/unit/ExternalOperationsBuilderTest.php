<?php
/**
 * Unit tests for ExternalOperationsBuilder.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.1
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\ExternalOperationsBuilder;
use FluxMedia\App\Services\Settings;
use PHPUnit\Framework\TestCase;

/**
 * ExternalOperationsBuilder unit tests.
 *
 * @since 4.2.1
 */
class ExternalOperationsBuilderTest extends TestCase {

	/**
	 * Reset per-test attachment fixtures.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fmo_test_attached_files']      = [];
		$GLOBALS['fmo_test_original_files']      = [];
		$GLOBALS['fmo_test_attachment_metadata'] = [];
		$GLOBALS['fmo_test_mimetypes']           = [];
	}

	/**
	 * Full-size image operations include resize dimensions when metadata provides them.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testImageFullOperationIncludesResize() {
		$GLOBALS['fmo_test_mimetypes'][42]           = 'image/jpeg';
		$GLOBALS['fmo_test_attachment_metadata'][42] = [
			'width'  => 1200,
			'height' => 800,
			'sizes'  => [
				'medium' => [
					'width'  => 300,
					'height' => 200,
				],
			],
		];

		$operations = ExternalOperationsBuilder::build_for_attachment( 42 );

		$this->assertCount( 2, $operations );
		$this->assertSame( 'full', $operations[0]['key_name'] );
		$this->assertSame( Settings::get_image_formats(), $operations[0]['formats'] );
		$this->assertSame(
			[ 'width' => 1200, 'height' => 800 ],
			$operations[0]['resize']
		);
		$this->assertArrayNotHasKey( 'multi_frame', $operations[0] );
		$this->assertSame( 'medium', $operations[1]['key_name'] );
		$this->assertSame(
			[ 'width' => 300, 'height' => 200 ],
			$operations[1]['resize']
		);
	}

	/**
	 * Video operations only include a full-size entry.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testVideoOperationsIncludeFullOnly() {
		$GLOBALS['fmo_test_mimetypes'][7] = 'video/mp4';

		$operations = ExternalOperationsBuilder::build_for_attachment( 7 );

		$this->assertCount( 1, $operations );
		$this->assertSame( 'full', $operations[0]['key_name'] );
		$this->assertSame( Settings::get_video_formats(), $operations[0]['formats'] );
		$this->assertArrayNotHasKey( 'resize', $operations[0] );
	}

	/**
	 * Non-media files use CDN storage operation shape.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testNonMediaUsesCdnStorageOperation() {
		$GLOBALS['fmo_test_mimetypes'][9] = 'application/pdf';

		$operations = ExternalOperationsBuilder::build_for_attachment( 9 );

		$this->assertSame(
			[
				[
					'key_name' => 'full',
				],
			],
			$operations
		);
	}

	/**
	 * Animated GIF ops match static image shape; no client multi_frame flag.
	 *
	 * SaaS detects animation from the pull-file source. The plugin only sends
	 * formats, key_name, and resize.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testAnimatedGifOperationsOmitMultiFrameFlag() {
		$GLOBALS['fmo_test_mimetypes'][100]           = 'image/gif';
		$GLOBALS['fmo_test_attachment_metadata'][100] = [
			'width'  => 400,
			'height' => 400,
			'sizes'  => [
				'medium' => [
					'width'  => 300,
					'height' => 300,
				],
			],
		];

		$operations = ExternalOperationsBuilder::build_for_attachment( 100 );

		$this->assertCount( 2, $operations );
		$this->assertSame( 'full', $operations[0]['key_name'] );
		$this->assertSame( 'medium', $operations[1]['key_name'] );
		$this->assertSame(
			[ 'width' => 300, 'height' => 300 ],
			$operations[1]['resize']
		);
		$this->assertArrayNotHasKey( 'multi_frame', $operations[0] );
		$this->assertArrayNotHasKey( 'multi_frame', $operations[1] );
	}
}
