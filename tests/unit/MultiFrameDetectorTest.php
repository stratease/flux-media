<?php
/**
 * Unit tests for MultiFrameDetector.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\MultiFrameDetector;
use FluxMedia\App\Services\SourceFormatRegistry;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * MultiFrameDetector unit tests.
 *
 * @since 4.3.0
 */
class MultiFrameDetectorTest extends TestCase {

	/**
	 * Detector instance.
	 *
	 * @since 4.3.0
	 * @var MultiFrameDetector
	 */
	private $detector;

	/**
	 * Set up test environment.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		$this->detector = new MultiFrameDetector( Logger::get_instance() );
	}

	/**
	 * Test missing files are treated as static.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testMissingFileIsNotMultiFrame() {
		$this->assertFalse( $this->detector->is_multi_frame( '/tmp/does-not-exist-' . uniqid() . '.png' ) );
	}

	/**
	 * Test context builder returns null for unsupported paths.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testBuildContextReturnsNullForUnsupportedExtension() {
		$registry = new SourceFormatRegistry();
		$context = $this->detector->build_context( '/tmp/file.pdf', $registry );

		$this->assertNull( $context );
	}

	/**
	 * Test context builder for HEIC marks Imagick requirement.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testBuildContextForHeicRequiresImagick() {
		$test_files_dir = __DIR__ . '/../_support/files/';
		$heic_path = $test_files_dir . 'sample_static.heic';

		if ( ! file_exists( $heic_path ) ) {
			$this->markTestSkipped( 'HEIC fixture not found: ' . $heic_path );
		}

		$registry = new SourceFormatRegistry();
		$context = $this->detector->build_context( $heic_path, $registry );

		$this->assertNotNull( $context );
		$this->assertSame( 'heic', $context->get_extension() );
		$this->assertTrue( $context->requires_imagick() );
	}

	/**
	 * Animated HEIF fixture is detected as a multi-frame sequence via ftyp brand.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testAnimatedHeifDetectedAsSequence() {
		$heif_path = __DIR__ . '/../_support/files/sample_animated.heif';
		if ( ! file_exists( $heif_path ) ) {
			$this->markTestSkipped( 'Animated HEIF fixture not found: ' . $heif_path );
		}

		$this->assertTrue( $this->detector->is_heif_sequence( $heif_path ) );
		$this->assertTrue( $this->detector->is_multi_frame( $heif_path ) );
		$this->assertGreaterThan( 1, $this->detector->get_frame_count( $heif_path ) );
	}
}
