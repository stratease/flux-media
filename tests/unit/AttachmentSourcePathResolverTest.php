<?php
/**
 * Unit tests for AttachmentSourcePathResolver.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentSourcePathResolver;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * AttachmentSourcePathResolver unit tests.
 *
 * @since 4.3.0
 */
class AttachmentSourcePathResolverTest extends TestCase {

	/**
	 * Temporary directory for fixture files.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private static $temp_dir;

	/**
	 * Set up temp directory.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::$temp_dir = sys_get_temp_dir() . '/fmo-source-path-' . uniqid();
		mkdir( self::$temp_dir );
	}

	/**
	 * Remove temp directory.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public static function tearDownAfterClass(): void {
		if ( is_dir( self::$temp_dir ) ) {
			$files = glob( self::$temp_dir . '/*' );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
			}
			rmdir( self::$temp_dir );
		}
	}

	/**
	 * Reset mock state before each test.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		$GLOBALS['fmo_test_attached_files']      = [];
		$GLOBALS['fmo_test_original_files']      = [];
		$GLOBALS['fmo_test_attachment_metadata'] = [];
	}

	/**
	 * Test attached-only paths return the attached file.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testReturnsAttachedPathWhenNoOriginalDiffers() {
		$attached = self::$temp_dir . '/photo.jpg';
		touch( $attached );

		$GLOBALS['fmo_test_attached_files'][42] = $attached;
		$GLOBALS['fmo_test_original_files'][42] = $attached;

		$resolver = new AttachmentSourcePathResolver( Logger::get_instance() );

		$this->assertSame( $attached, $resolver->get_optimization_source_path( 42 ) );
	}

	/**
	 * Test HEIC original is preferred over attached JPEG.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPrefersHeicOriginalOverAttachedJpeg() {
		$attached = self::$temp_dir . '/photo.jpg';
		$original = self::$temp_dir . '/photo.heic';
		touch( $attached );
		touch( $original );

		$GLOBALS['fmo_test_attached_files'][55]      = $attached;
		$GLOBALS['fmo_test_original_files'][55]      = $original;
		$GLOBALS['fmo_test_attachment_metadata'][55] = [
			'original_image' => 'photo.heic',
		];

		$resolver = new AttachmentSourcePathResolver( Logger::get_instance() );

		$this->assertSame( $original, $resolver->get_optimization_source_path( 55 ) );
	}

	/**
	 * Test missing original falls back to attached file.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testFallsBackWhenOriginalMissing() {
		$attached = self::$temp_dir . '/photo.jpg';
		$original = self::$temp_dir . '/missing.heic';
		touch( $attached );

		$GLOBALS['fmo_test_attached_files'][66]      = $attached;
		$GLOBALS['fmo_test_original_files'][66]      = $original;
		$GLOBALS['fmo_test_attachment_metadata'][66] = [
			'original_image' => 'missing.heic',
		];

		$resolver = new AttachmentSourcePathResolver( Logger::get_instance() );

		$this->assertSame( $attached, $resolver->get_optimization_source_path( 66 ) );
	}
}
