<?php
/**
 * Unit tests for HEIC/HEIF conversion via ImageConverter.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\ImageConverter;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\App\Services\Settings;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use PHPUnit\Framework\TestCase;

/**
 * HEIC conversion regression tests.
 *
 * @since 4.3.0
 */
class HeicConversionTest extends TestCase {

	/**
	 * ImageConverter instance.
	 *
	 * @since 4.3.0
	 * @var ImageConverter
	 */
	private $image_converter;

	/**
	 * Set up test environment.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		$this->image_converter = new ImageConverter( Logger::get_instance() );
	}

	/**
	 * Static HEIC converts to WebP when Imagick libheif is available.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testStaticHeicConvertsToWebp() {
		$detector = new ProcessorDetector();
		if ( ! $detector->imagick_supports_heic() ) {
			$this->markTestSkipped( 'Imagick HEIC decode not available on this environment' );
		}

		if ( ! $this->image_converter->is_format_supported( Converter::FORMAT_WEBP ) ) {
			$this->markTestSkipped( 'WebP output not supported on this environment' );
		}

		$source_file = __DIR__ . '/../_support/files/sample_static.heic';
		if ( ! file_exists( $source_file ) ) {
			$this->markTestSkipped( 'HEIC fixture not found: ' . $source_file );
		}

		$this->assertTrue( $this->image_converter->is_supported_image( $source_file ) );

		$output_file = TEST_TEMP_DIR . '/heic_static_' . uniqid() . '.webp';
		$settings    = Settings::get_image_conversion_settings();

		$result = $this->image_converter->process_image(
			$source_file,
			[ Converter::FORMAT_WEBP => $output_file ],
			$settings
		);

		$this->assertTrue( $result['success'], 'HEIC conversion failed: ' . implode( ', ', $result['errors'] ?? [] ) );
		$this->assertFileExists( $output_file );
		$this->assertGreaterThan( 0, filesize( $output_file ) );

		if ( file_exists( $output_file ) ) {
			unlink( $output_file );
		}
	}

	/**
	 * Animated HEIF sequence converts to multi-frame WebP via FFmpeg when available.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testAnimatedHeifConvertsToAnimatedWebp() {
		$sequence_converter = new \FluxMedia\App\Services\HeifSequenceConverter( Logger::get_instance() );
		if ( ! $sequence_converter->is_available() ) {
			$this->markTestSkipped( 'FFmpeg libwebp_anim not available on this environment' );
		}

		$source_file = __DIR__ . '/../_support/files/sample_animated.heif';
		if ( ! file_exists( $source_file ) ) {
			$this->markTestSkipped( 'Animated HEIF fixture not found: ' . $source_file );
		}

		$detector = new \FluxMedia\App\Services\MultiFrameDetector( Logger::get_instance() );
		$this->assertTrue( $detector->is_heif_sequence( $source_file ) );
		$this->assertTrue( $detector->is_multi_frame( $source_file ) );

		$output_file = TEST_TEMP_DIR . '/heif_anim_' . uniqid() . '.webp';
		$settings    = Settings::get_image_conversion_settings();

		$result = $this->image_converter->process_image(
			$source_file,
			[ Converter::FORMAT_WEBP => $output_file ],
			$settings
		);

		$this->assertTrue( $result['success'], 'Animated HEIF conversion failed: ' . implode( ', ', $result['errors'] ?? [] ) );
		$this->assertFileExists( $output_file );
		$this->assertGreaterThan( 0, filesize( $output_file ) );

		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$imagick = new \Imagick( $output_file );
			$this->assertGreaterThan( 1, $imagick->getNumberImages(), 'Output WebP should contain multiple frames' );
			$imagick->clear();
			$imagick->destroy();
		}

		if ( file_exists( $output_file ) ) {
			unlink( $output_file );
		}
	}

	/**
	 * Undecodable HEIC surfaces an error message instead of an empty errors array.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testUndecodableHeicReportsErrors() {
		$detector = new ProcessorDetector();
		if ( ! $detector->imagick_supports_heic() ) {
			$this->markTestSkipped( 'Imagick HEIC decode not available on this environment' );
		}

		// Minimal invalid HEIC-like file: ftyp heic box without a valid image.
		$bogus = TEST_TEMP_DIR . '/bogus_' . uniqid() . '.heic';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents(
			$bogus,
			pack( 'N', 16 ) . 'ftyp' . 'heic' . 'heic'
		);

		$output_file = TEST_TEMP_DIR . '/bogus_' . uniqid() . '.webp';
		$settings    = Settings::get_image_conversion_settings();

		$result = $this->image_converter->process_image(
			$bogus,
			[ Converter::FORMAT_WEBP => $output_file ],
			$settings
		);

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['errors'], 'Decode failures must populate errors' );

		@unlink( $bogus );
		@unlink( $output_file );
	}
}
