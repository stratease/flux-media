<?php
/**
 * Unit tests for LocalProcessingService image atomicity (files + metadata).
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentMetaHandler;
use FluxMedia\App\Services\BulkConverter;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\ImageConverter;
use FluxMedia\App\Services\LocalProcessingService;
use FluxMedia\App\Services\VideoConverter;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Ensures image conversion publishes files and DB state together.
 *
 * @since 4.3.0
 */
class LocalProcessingServiceImageAtomicityTest extends TestCase {

	/**
	 * Temporary uploads root.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $uploads_root;

	/**
	 * Relative attachment directory under uploads.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private $rel_dir = '2024/01';

	/**
	 * Tracker calls recorded during a test.
	 *
	 * @since 4.3.0
	 * @var array<int, array<string, mixed>>
	 */
	private $tracker_calls = [];

	/**
	 * Create uploads fixtures and reset stubs.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->uploads_root = sys_get_temp_dir() . '/fmo-atomic-' . uniqid( '', true );
		mkdir( $this->uploads_root . '/' . $this->rel_dir, 0755, true );

		$GLOBALS['fmo_test_upload_dir'] = [
			'basedir' => $this->uploads_root,
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		];
		$GLOBALS['fmo_test_post_meta']           = [];
		$GLOBALS['fmo_test_attached_files']      = [];
		$GLOBALS['fmo_test_original_files']      = [];
		$GLOBALS['fmo_test_attachment_metadata'] = [];
		$GLOBALS['fmo_test_mimetypes']           = [];
		$GLOBALS['fmo_test_options']             = [
			'flux_media_optimizer_options' => [
				'image_formats' => [ 'webp' ],
			],
		];
		$this->tracker_calls = [];
	}

	/**
	 * Remove temporary uploads tree.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function tearDown(): void {
		$this->removeTree( $this->uploads_root );
		parent::tearDown();
	}

	/**
	 * Partial size failure rolls back staged files and does not persist meta or stats.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPartialSizeFailureDoesNotPersistMetaOrStats() {
		$attachment_id = 701;
		$full          = $this->path( 'photo.jpg' );
		$thumb         = $this->path( 'photo-150x150.jpg' );
		$prior_webp    = $this->path( 'photo.webp' );

		file_put_contents( $full, str_repeat( 'F', 2048 ) );
		file_put_contents( $thumb, str_repeat( 'T', 512 ) );
		file_put_contents( $prior_webp, 'prior-webp' );

		$this->seed_attachment( $attachment_id, $full, $thumb );

		$image_converter = $this->createMock( ImageConverter::class );
		$image_converter->method( 'is_supported_image' )->willReturn( true );
		$image_converter->method( 'get_optimization_source_path' )->willReturn( $full );
		$image_converter->method( 'is_multi_frame_source' )->willReturn( false );
		$image_converter->method( 'process_image' )->willReturnCallback(
			function ( $source, $staging_paths ) use ( $full ) {
				if ( $source === $full && isset( $staging_paths['webp'] ) && false !== strpos( $staging_paths['webp'], 'photo.webp' ) ) {
					file_put_contents( $staging_paths['webp'], 'new-full-webp' );
					return [
						'success'           => true,
						'converted_formats' => [ 'webp' ],
						'converted_files'   => [ 'webp' => $staging_paths['webp'] ],
						'errors'            => [],
					];
				}

				return [
					'success'           => false,
					'converted_formats' => [],
					'converted_files'   => [],
					'errors'            => [ 'thumbnail conversion failed' ],
				];
			}
		);

		$service = $this->create_service( $image_converter );
		$result  = $service->process( $attachment_id, $full );

		$this->assertFalse( $result );
		$this->assertSame( 'prior-webp', file_get_contents( $prior_webp ) );
		$this->assertEmpty( $this->tracker_calls );
		$this->assertSame( [], AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id ) );
		$this->assertNotSame( '', AttachmentMetaHandler::get_conversion_error( $attachment_id ) );
		$this->assertSame( 'failed', AttachmentMetaHandler::get_external_job_state( $attachment_id ) );
	}

	/**
	 * Successful multi-size conversion persists tracker rows and file meta after commit.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testSuccessfulConversionPersistsMetaAndStatsAfterCommit() {
		$attachment_id = 702;
		$full          = $this->path( 'ok.jpg' );
		$thumb         = $this->path( 'ok-150x150.jpg' );

		file_put_contents( $full, str_repeat( 'F', 2048 ) );
		file_put_contents( $thumb, str_repeat( 'T', 512 ) );

		$this->seed_attachment( $attachment_id, $full, $thumb );

		$image_converter = $this->createMock( ImageConverter::class );
		$image_converter->method( 'is_supported_image' )->willReturn( true );
		$image_converter->method( 'get_optimization_source_path' )->willReturn( $full );
		$image_converter->method( 'is_multi_frame_source' )->willReturn( false );
		$image_converter->method( 'process_image' )->willReturnCallback(
			static function ( $source, $staging_paths ) {
				foreach ( $staging_paths as $staging ) {
					file_put_contents( $staging, 'converted-' . basename( $staging ) );
				}

				return [
					'success'           => true,
					'converted_formats' => [ 'webp' ],
					'converted_files'   => [ 'webp' => $staging_paths['webp'] ],
					'errors'            => [],
				];
			}
		);

		$service = $this->create_service( $image_converter );
		$result  = $service->process( $attachment_id, $full );

		$this->assertTrue( $result );
		$this->assertFileExists( $this->path( 'ok.webp' ) );
		$this->assertFileExists( $this->path( 'ok-150x150.webp' ) );
		$this->assertCount( 2, $this->tracker_calls );

		$files = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
		$this->assertArrayHasKey( 'full', $files );
		$this->assertArrayHasKey( 'thumbnail', $files );
		$this->assertArrayHasKey( 'webp', $files['full'] );
		$this->assertArrayHasKey( 'original', $files['full'] );
		$this->assertSame( '', AttachmentMetaHandler::get_conversion_error( $attachment_id ) );
	}

	/**
	 * Missing required format for a size rolls back without tracker writes.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testMissingRequiredFormatRollsBackWithoutTrackerWrites() {
		$attachment_id = 703;
		$full          = $this->path( 'missing.jpg' );

		file_put_contents( $full, str_repeat( 'F', 1024 ) );
		$this->seed_attachment( $attachment_id, $full, null );

		$image_converter = $this->createMock( ImageConverter::class );
		$image_converter->method( 'is_supported_image' )->willReturn( true );
		$image_converter->method( 'get_optimization_source_path' )->willReturn( $full );
		$image_converter->method( 'is_multi_frame_source' )->willReturn( false );
		$image_converter->method( 'process_image' )->willReturn(
			[
				'success'           => true,
				'converted_formats' => [],
				'converted_files'   => [],
				'errors'            => [],
			]
		);

		$service = $this->create_service( $image_converter );
		$result  = $service->process( $attachment_id, $full );

		$this->assertFalse( $result );
		$this->assertEmpty( $this->tracker_calls );
		$this->assertSame( [], AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id ) );
	}

	/**
	 * Build LocalProcessingService with mocked collaborators.
	 *
	 * @since 4.3.0
	 * @param ImageConverter $image_converter Image converter mock.
	 * @return LocalProcessingService
	 */
	private function create_service( ImageConverter $image_converter ): LocalProcessingService {
		$tracker = $this->createMock( ConversionTracker::class );
		$tracker->method( 'record_conversion' )->willReturnCallback(
			function ( $attachment_id, $format, $original_size, $converted_size, $size_name ) {
				$this->tracker_calls[] = [
					'attachment_id'  => $attachment_id,
					'format'         => $format,
					'original_size'  => $original_size,
					'converted_size' => $converted_size,
					'size_name'      => $size_name,
				];
				return true;
			}
		);
		$tracker->method( 'delete_attachment_conversions_by_formats' )->willReturn( 0 );

		return new LocalProcessingService(
			$image_converter,
			$this->createMock( VideoConverter::class ),
			$tracker,
			$this->createMock( BulkConverter::class ),
			Logger::get_instance()
		);
	}

	/**
	 * Seed attachment file and metadata stubs.
	 *
	 * @since 4.3.0
	 * @param int         $attachment_id Attachment ID.
	 * @param string      $full_path     Full-size path.
	 * @param string|null $thumb_path    Thumbnail path or null.
	 * @return void
	 */
	private function seed_attachment( $attachment_id, $full_path, $thumb_path ) {
		$GLOBALS['fmo_test_attached_files'][ $attachment_id ] = $full_path;
		$GLOBALS['fmo_test_mimetypes'][ $attachment_id ]      = 'image/jpeg';

		$metadata = [
			'file'   => $this->rel_dir . '/' . basename( $full_path ),
			'width'  => 800,
			'height' => 600,
			'sizes'  => [],
		];

		if ( null !== $thumb_path ) {
			$metadata['sizes']['thumbnail'] = [
				'file'   => basename( $thumb_path ),
				'width'  => 150,
				'height' => 150,
			];
		}

		$GLOBALS['fmo_test_attachment_metadata'][ $attachment_id ] = $metadata;
	}

	/**
	 * Absolute path under the uploads fixture directory.
	 *
	 * @since 4.3.0
	 * @param string $basename File basename.
	 * @return string
	 */
	private function path( $basename ) {
		return $this->uploads_root . '/' . $this->rel_dir . '/' . $basename;
	}

	/**
	 * Recursively remove a directory tree.
	 *
	 * @since 4.3.0
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function removeTree( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$todo = $item->isDir() ? 'rmdir' : 'unlink';
			$todo( $item->getRealPath() );
		}

		rmdir( $dir );
	}
}
