<?php
/**
 * Unit tests for UploadPathGuard.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\UploadPathGuard;
use PHPUnit\Framework\TestCase;

/**
 * UploadPathGuard containment tests.
 *
 * @since 4.3.0
 */
class UploadPathGuardTest extends TestCase {

	/**
	 * Temporary uploads root for fixtures.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private static $uploads_root;

	/**
	 * Sibling directory outside uploads.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	private static $outside_root;

	/**
	 * Create temp directories used by path tests.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		$base = sys_get_temp_dir() . '/fmo-upload-guard-' . uniqid( '', true );
		self::$uploads_root = $base . '/uploads';
		self::$outside_root = $base . '/outside';
		mkdir( self::$uploads_root . '/2024/01', 0755, true );
		mkdir( self::$outside_root, 0755, true );
	}

	/**
	 * Remove temp directories.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public static function tearDownAfterClass(): void {
		self::removeTree( dirname( self::$uploads_root ) );
	}

	/**
	 * Reset upload dir stub globals between tests.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fmo_test_upload_dir'] = [
			'basedir' => self::$uploads_root,
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		];
	}

	/**
	 * Existing child under uploads is allowed.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testExistingPathWithinUploadsPasses() {
		$file = self::$uploads_root . '/2024/01/photo.jpg';
		touch( $file );

		$this->assertTrue( UploadPathGuard::is_existing_path_within( $file, self::$uploads_root ) );
		$this->assertSame( '2024/01/photo.jpg', UploadPathGuard::get_relative_path_within( $file, self::$uploads_root ) );
	}

	/**
	 * Sibling-prefix and traversal paths outside uploads are rejected.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPathsOutsideUploadsFail() {
		$outside = self::$outside_root . '/secret.txt';
		touch( $outside );

		$sibling_prefix = self::$uploads_root . '-evil/file.jpg';
		mkdir( dirname( $sibling_prefix ), 0755, true );
		touch( $sibling_prefix );

		$traversal = self::$uploads_root . '/2024/01/../../../outside/secret.txt';

		$this->assertFalse( UploadPathGuard::is_existing_path_within( $outside, self::$uploads_root ) );
		$this->assertFalse( UploadPathGuard::is_existing_path_within( $sibling_prefix, self::$uploads_root ) );
		$this->assertFalse( UploadPathGuard::is_existing_path_within( $traversal, self::$uploads_root ) );
	}

	/**
	 * Destination validation uses parent directory containment.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDestinationWithinUsesParentDirectory() {
		$destination = self::$uploads_root . '/2024/01/photo.webp';
		$outside_destination = self::$outside_root . '/photo.webp';

		$this->assertTrue( UploadPathGuard::is_destination_within( $destination, self::$uploads_root ) );
		$this->assertFalse( UploadPathGuard::is_destination_within( $outside_destination, self::$uploads_root ) );
	}

	/**
	 * Local upload URL conversion rejects traversal and foreign hosts.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testLocalUploadUrlToPath() {
		$file = self::$uploads_root . '/2024/01/photo.webp';
		touch( $file );

		$base_url = 'https://example.com/wp-content/uploads';
		$ok = UploadPathGuard::local_upload_url_to_path(
			$base_url . '/2024/01/photo.webp',
			$base_url,
			self::$uploads_root
		);

		$this->assertSame( wp_normalize_path( (string) realpath( $file ) ), $ok );

		$this->assertFalse(
			UploadPathGuard::local_upload_url_to_path(
				$base_url . '/2024/01/../../outside/secret.txt',
				$base_url,
				self::$uploads_root
			)
		);

		$this->assertFalse(
			UploadPathGuard::local_upload_url_to_path(
				'https://evil.example/wp-content/uploads/2024/01/photo.webp',
				$base_url,
				self::$uploads_root
			)
		);
	}

	/**
	 * URL origin matching includes default HTTPS port equivalence.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testLocalUploadUrlToPathMatchesDefaultHttpsPort() {
		$file = self::$uploads_root . '/2024/01/port.webp';
		touch( $file );

		$resolved = UploadPathGuard::local_upload_url_to_path(
			'https://example.com:443/wp-content/uploads/2024/01/port.webp',
			'https://example.com/wp-content/uploads',
			self::$uploads_root
		);

		$this->assertSame( wp_normalize_path( (string) realpath( $file ) ), $resolved );
	}

	/**
	 * Encoded traversal segments are rejected after decoding.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testLocalUploadUrlToPathRejectsEncodedTraversal() {
		$this->assertFalse(
			UploadPathGuard::local_upload_url_to_path(
				'https://example.com/wp-content/uploads/2024/01/%2e%2e/%2e%2e/outside/secret.txt',
				'https://example.com/wp-content/uploads',
				self::$uploads_root
			)
		);
	}

	/**
	 * Uploads helpers fail closed when wp_upload_dir reports an error.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetUploadsPathsFailClosedOnError() {
		$GLOBALS['fmo_test_upload_dir'] = [
			'basedir' => self::$uploads_root,
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => 'Unable to create directory',
		];

		$this->assertFalse( UploadPathGuard::get_uploads_basedir() );
		$this->assertFalse( UploadPathGuard::get_uploads_baseurl() );
	}

	/**
	 * Uploads helpers return configured basedir/baseurl when healthy.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetUploadsPathsReturnConfiguredValues() {
		$this->assertSame(
			wp_normalize_path( self::$uploads_root ),
			UploadPathGuard::get_uploads_basedir()
		);
		$this->assertSame(
			'https://example.com/wp-content/uploads',
			UploadPathGuard::get_uploads_baseurl()
		);
	}

	/**
	 * Recursively remove a directory tree.
	 *
	 * @since 4.3.0
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function removeTree( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$path = $item->getRealPath();
			if ( $item->isDir() ) {
				rmdir( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}
}
