<?php
/**
 * Unit tests for HEIC capability detection.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\HeifCapabilityProbe;
use FluxMedia\App\Services\ProcessorDetector;
use PHPUnit\Framework\TestCase;

/**
 * ProcessorDetector HEIC tests.
 *
 * @since 4.3.0
 */
class ProcessorDetectorHeicTest extends TestCase {

	/**
	 * Test HEIC flags are booleans on available processors.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testProcessorHeicFlagsAreBoolean() {
		$detector   = new ProcessorDetector();
		$processors = $detector->get_available_image_processors();

		if ( empty( $processors ) ) {
			$this->markTestSkipped( 'No image processors available (install php-gd or php-imagick)' );
		}

		foreach ( $processors as $processor ) {
			$this->assertArrayHasKey( 'heic_support', $processor );
			$this->assertArrayHasKey( 'animated_heic_support', $processor );
			$this->assertIsBool( $processor['heic_support'] );
			$this->assertIsBool( $processor['animated_heic_support'] );
		}
	}

	/**
	 * Test animated HEIC is only reported when static HEIC or FFmpeg sequence support exists.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testAnimatedHeicRequiresStaticHeicOrFfmpeg() {
		$detector   = new ProcessorDetector();
		$processors = $detector->get_available_image_processors();
		$probe      = new HeifCapabilityProbe();

		if ( empty( $processors ) ) {
			$this->markTestSkipped( 'No image processors available (install php-gd or php-imagick)' );
		}

		$checked_animated = false;
		foreach ( $processors as $processor ) {
			if ( empty( $processor['animated_heic_support'] ) ) {
				continue;
			}

			$checked_animated = true;
			$this->assertTrue(
				! empty( $processor['heic_support'] ) || $probe->supports_heif_sequences(),
				'Animated HEIC support requires static HEIC decode or FFmpeg sequence support'
			);
		}

		if ( ! $checked_animated ) {
			$this->assertTrue( true );
		}
	}

	/**
	 * Test HEIF probe returns boolean for static support.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHeifProbeStaticSupportIsBoolean() {
		$probe = new HeifCapabilityProbe();
		$this->assertIsBool( $probe->supports_static_heic() );
	}

	/**
	 * Animated HEIC support implies multi_frame_support on ImagickProcessor.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testImagickMultiFrameSupportIncludesAnimatedHeic() {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'Imagick extension not available' );
		}

		$processor = new \FluxMedia\App\Services\ImagickProcessor(
			\FluxMedia\FluxPlugins\Common\Logger\Logger::get_instance()
		);
		$info = $processor->get_info();

		$this->assertArrayHasKey( 'multi_frame_support', $info );
		$this->assertIsBool( $info['multi_frame_support'] );

		if ( ! empty( $info['animated_heic_support'] ) ) {
			$this->assertTrue( $info['multi_frame_support'] );
		}
	}
}
