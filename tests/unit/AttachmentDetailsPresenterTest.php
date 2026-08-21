<?php
/**
 * Unit tests for AttachmentDetailsPresenter.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentDetailsPresenter;
use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\ConversionOrchestrator;
use FluxMedia\App\Services\FormatSupportDetector;
use FluxMedia\App\Services\MediaLibraryStatusService;
use FluxMedia\App\Services\Settings;
use PHPUnit\Framework\TestCase;

/**
 * AttachmentDetailsPresenter unit tests.
 *
 * @since 4.3.0
 */
class AttachmentDetailsPresenterTest extends TestCase {

	/**
	 * Reset stubs.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fmo_test_post_meta']           = [];
		$GLOBALS['fmo_test_mimetypes']           = [];
		$GLOBALS['fmo_test_attachment_metadata'] = [];
		$GLOBALS['fmo_test_actions']             = [];
		$GLOBALS['fmo_test_as_scheduled_actions'] = [];
		$GLOBALS['fmo_test_as_next_action']       = [];
		$GLOBALS['fmo_test_action_callbacks']     = [];
		$GLOBALS['fmo_test_options']              = [];
		$GLOBALS['fmo_test_attached_files']       = [];
		$GLOBALS['fmo_test_attachment_urls']      = [];
		$GLOBALS['fmo_test_posts']                = [];
		$GLOBALS['fmo_test_capabilities']         = [];
	}

	/**
	 * Build presenter with mocked format support.
	 *
	 * @since 4.3.0
	 * @param array{webp?:bool,avif?:bool,av1?:bool,webm?:bool} $support Capability map.
	 * @return AttachmentDetailsPresenter
	 */
	private function make_presenter( array $support = [] ) {
		$detector = $this->createMock( FormatSupportDetector::class );
		$detector->method( 'supports_webp' )->willReturn( $support['webp'] ?? true );
		$detector->method( 'supports_avif' )->willReturn( $support['avif'] ?? true );
		$detector->method( 'supports_av1' )->willReturn( $support['av1'] ?? true );
		$detector->method( 'supports_webm' )->willReturn( $support['webm'] ?? true );

		return new AttachmentDetailsPresenter( $detector );
	}

	/**
	 * Savings math for smaller converted files.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testCalculateSavingsSmaller() {
		$result = AttachmentDetailsPresenter::calculate_savings( 12000, 3000 );

		$this->assertSame( 75.0, $result['percent'] );
		$this->assertSame( 9000, $result['bytes'] );
		$this->assertStringContainsString( '75%', $result['label'] );
		$this->assertStringContainsString( 'saved', $result['saved_label'] );
	}

	/**
	 * Presenter builds size rows with formats, savings, and upsell when unlicensed.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPresentBuildsSizeRowsAndUpsellWhenUnlicensed() {
		$attachment_id = 701;
		$GLOBALS['fmo_test_mimetypes'][ $attachment_id ] = 'image/jpeg';
		$GLOBALS['fmo_test_attachment_metadata'][ $attachment_id ] = [
			'width'  => 150,
			'height' => 150,
			'sizes'  => [
				'thumbnail' => [
					'width'  => 150,
					'height' => 150,
					'file'   => 'thumb.jpg',
				],
			],
		];

		AttachmentMetaHandler::set_converted_files_grouped_by_size(
			$attachment_id,
			[
				'thumbnail' => [
					'original' => [
						'url'      => 'https://example.com/thumb.jpg',
						'filesize' => 12000,
					],
					'avif'     => [
						'url'      => 'https://example.com/thumb.avif',
						'filesize' => 3000,
					],
					'webp'     => [
						'url'      => 'https://example.com/thumb.webp',
						'filesize' => 3000,
					],
				],
			]
		);
		AttachmentMetaHandler::set_converted_formats( $attachment_id, [ 'webp', 'avif' ] );

		$payload = $this->make_presenter()->present( $attachment_id );

		$this->assertSame( $attachment_id, $payload['attachmentId'] );
		$this->assertSame( 'image', $payload['mediaType'] );
		$this->assertSame( 28, $payload['brandIconSize'] );
		$this->assertTrue( $payload['showUpsell'] );
		$this->assertSame( AttachmentDetailsPresenter::CDN_BUY_URL, $payload['upsellUrl'] );
		$this->assertTrue( $payload['hasConversions'] );
		$this->assertSame( 'Re-convert', $payload['actions']['convertLabel'] );
		$this->assertCount( 1, $payload['sizes'] );
		$this->assertSame( 'thumbnail', $payload['sizes'][0]['name'] );
		$this->assertSame( 'core', $payload['sizes'][0]['source'] );
		$this->assertSame( 'JPG', $payload['sizes'][0]['original']['label'] );
		$this->assertSame( 75.0, $payload['sizes'][0]['savingsPercent'] );
		$this->assertArrayHasKey( 'webp', $payload['sizes'][0]['variants'] );
		$this->assertArrayHasKey( 'avif', $payload['sizes'][0]['variants'] );
	}

	/**
	 * Failed attachments expose error and retry text.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPresentFailedIncludesErrorAndRetryText() {
		AttachmentMetaHandler::mark_conversion_failed( 702, 'Unable to decode HEIC' );

		$payload = $this->make_presenter()->present( 702 );

		$this->assertSame( 'failed', $payload['status'] );
		$this->assertSame( 'Unable to decode HEIC', $payload['error'] );
		$this->assertSame( 'Retry 0/3', $payload['retryText'] );
	}

	/**
	 * Hide AVIF when processor cannot produce it.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testEffectiveFormatsHideUnsupportedAvif() {
		$GLOBALS['fmo_test_options'][ Settings::get_options_option_name() ] = [
			'image_formats' => [ 'webp', 'avif' ],
		];

		$formats = $this->make_presenter( [ 'webp' => true, 'avif' => false ] )->resolve_effective_formats( 'image' );

		$this->assertSame( [ 'webp' ], $formats );
	}

	/**
	 * Hide disabled image formats even when processor supports them.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testEffectiveFormatsRespectSettingsOnlyWebp() {
		$GLOBALS['fmo_test_options'][ Settings::get_options_option_name() ] = [
			'image_formats' => [ 'webp' ],
		];

		$formats = $this->make_presenter( [ 'webp' => true, 'avif' => true ] )->resolve_effective_formats( 'image' );

		$this->assertSame( [ 'webp' ], $formats );
	}

	/**
	 * Video rows expose AV1/WebM variants and savings.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPresentVideoBuildsAv1WebmRow() {
		$attachment_id = 803;
		$GLOBALS['fmo_test_mimetypes'][ $attachment_id ] = 'video/mp4';
		$GLOBALS['fmo_test_options'][ Settings::get_options_option_name() ] = [
			'video_formats' => [ 'av1', 'webm' ],
		];

		AttachmentMetaHandler::set_converted_files_grouped_by_size(
			$attachment_id,
			[
				'full' => [
					'original' => [
						'url'      => 'https://example.com/clip.mp4',
						'filesize' => 10000,
					],
					'av1'      => [
						'url'      => 'https://example.com/clip-av1.mp4',
						'filesize' => 4000,
					],
					'webm'     => [
						'url'      => 'https://example.com/clip.webm',
						'filesize' => 5000,
					],
				],
			]
		);
		AttachmentMetaHandler::set_converted_formats( $attachment_id, [ 'av1', 'webm' ] );

		$payload = $this->make_presenter()->present( $attachment_id );

		$this->assertSame( 'video', $payload['mediaType'] );
		$this->assertSame( [ 'av1', 'webm' ], $payload['effectiveFormats'] );
		$this->assertCount( 1, $payload['sizes'] );
		$this->assertSame( 'full', $payload['sizes'][0]['name'] );
		$this->assertSame( 60.0, $payload['sizes'][0]['savingsPercent'] );
		$this->assertNotNull( $payload['sizes'][0]['variants']['av1'] );
		$this->assertNotNull( $payload['sizes'][0]['variants']['webm'] );
		$this->assertSame( 'Media size', $payload['columns']['mediaSize'] );
	}

	/**
	 * Locally deferred video is Pending and processing.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPresentVideoDeferredIsPending() {
		$attachment_id = 804;
		$GLOBALS['fmo_test_mimetypes'][ $attachment_id ] = 'video/mp4';
		update_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED, '1' );

		$payload = $this->make_presenter()->present( $attachment_id );

		$this->assertSame( MediaLibraryStatusService::STATUS_PENDING, $payload['status'] );
		$this->assertTrue( $payload['processing'] );
		$this->assertFalse( $payload['actions']['canConvert'] );
	}

	/**
	 * Multi-size image rows each get savings against same-size original.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPresentMultiSizeSavingsAgainstSameSizeOriginal() {
		$attachment_id = 805;
		$GLOBALS['fmo_test_mimetypes'][ $attachment_id ] = 'image/jpeg';
		$GLOBALS['fmo_test_attachment_metadata'][ $attachment_id ] = [
			'width'  => 1000,
			'height' => 800,
			'file'   => '2024/01/photo.jpg',
			'sizes'  => [
				'thumbnail' => [
					'width'  => 150,
					'height' => 150,
					'file'   => 'photo-150x150.jpg',
				],
				'medium'    => [
					'width'  => 300,
					'height' => 240,
					'file'   => 'photo-300x240.jpg',
				],
			],
		];
		$GLOBALS['fmo_test_options'][ Settings::get_options_option_name() ] = [
			'image_formats' => [ 'webp' ],
		];

		AttachmentMetaHandler::set_converted_files_grouped_by_size(
			$attachment_id,
			[
				'full'      => [
					'original' => [
						'url'      => 'https://example.com/photo.jpg',
						'filesize' => 10000,
					],
					'webp'     => [
						'url'      => 'https://example.com/photo.webp',
						'filesize' => 5000,
					],
				],
				'thumbnail' => [
					'original' => [
						'url'      => 'https://example.com/photo-150x150.jpg',
						'filesize' => 2000,
					],
					'webp'     => [
						'url'      => 'https://example.com/photo-150x150.webp',
						'filesize' => 500,
					],
				],
				'medium'    => [
					'original' => [
						'url'      => 'https://example.com/photo-300x240.jpg',
						'filesize' => 4000,
					],
					'webp'     => [
						'url'      => 'https://example.com/photo-300x240.webp',
						'filesize' => 1000,
					],
				],
			]
		);
		AttachmentMetaHandler::set_converted_formats( $attachment_id, [ 'webp' ] );

		$payload = $this->make_presenter( [ 'webp' => true, 'avif' => false ] )->present( $attachment_id );
		$by_name = [];
		foreach ( $payload['sizes'] as $row ) {
			$by_name[ $row['name'] ] = $row;
		}

		$this->assertSame( [ 'webp' ], $payload['effectiveFormats'] );
		$this->assertArrayNotHasKey( 'avif', $by_name['full']['variants'] );
		$this->assertSame( 50.0, $by_name['full']['savingsPercent'] );
		$this->assertSame( 75.0, $by_name['thumbnail']['savingsPercent'] );
		$this->assertSame( 75.0, $by_name['medium']['savingsPercent'] );
		$this->assertNotEmpty( $by_name['thumbnail']['savingsLabel'] );
		$this->assertNotEmpty( $by_name['medium']['savingsLabel'] );
	}

	/**
	 * Status helper treats deferred video as pending.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDeriveStatusVideoDeferredPending() {
		$status = MediaLibraryStatusService::derive_status( false, null, [], [], true );
		$this->assertSame( MediaLibraryStatusService::STATUS_PENDING, $status );
	}
}
