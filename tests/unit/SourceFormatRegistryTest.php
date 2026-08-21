<?php
/**
 * Unit tests for SourceFormatRegistry.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\SourceFormatRegistry;
use FluxMedia\App\Services\SourceImageContext;
use PHPUnit\Framework\TestCase;

/**
 * SourceFormatRegistry unit tests.
 *
 * @since 4.3.0
 */
class SourceFormatRegistryTest extends TestCase {

	/**
	 * Registry instance.
	 *
	 * @since 4.3.0
	 * @var SourceFormatRegistry
	 */
	private $registry;

	/**
	 * Set up test environment.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		$this->registry = new SourceFormatRegistry();
	}

	/**
	 * Test supported extensions include HEIC and HEIF.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testSupportedExtensionsIncludeHeic() {
		$extensions = $this->registry->get_supported_extensions();

		$this->assertContains( 'heic', $extensions );
		$this->assertContains( 'heif', $extensions );
		$this->assertContains( 'heics', $extensions );
		$this->assertContains( 'heifs', $extensions );
	}

	/**
	 * Test HEIC paths are recognized as supported input.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testIsSupportedPathForHeic() {
		$this->assertTrue( $this->registry->is_supported_path( '/tmp/photo.heic' ) );
		$this->assertTrue( $this->registry->is_supported_path( '/tmp/photo.heif' ) );
		$this->assertTrue( $this->registry->is_supported_path( '/tmp/photo.heics' ) );
		$this->assertFalse( $this->registry->is_supported_path( '/tmp/document.pdf' ) );
	}

	/**
	 * Test HEIC requires Imagick for local processing.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHeicRequiresImagick() {
		$this->assertTrue( $this->registry->requires_imagick( 'heic' ) );
		$this->assertTrue( $this->registry->requires_imagick( 'heif' ) );
		$this->assertFalse( $this->registry->requires_imagick( 'jpg' ) );
	}

	/**
	 * Test source format mapping.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetSourceFormat() {
		$this->assertSame( Converter::FORMAT_HEIC, $this->registry->get_source_format( 'heic' ) );
		$this->assertSame( Converter::FORMAT_HEIF, $this->registry->get_source_format( 'heif' ) );
	}

	/**
	 * Test local processing gate respects HEIC capability flag.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testCanProcessLocallyForHeic() {
		$context = new SourceImageContext(
			'/tmp/photo.heic',
			'heic',
			'image/heic',
			false,
			1,
			true,
			Converter::FORMAT_HEIC
		);

		$this->assertFalse( $this->registry->can_process_locally( $context, false ) );
		$this->assertTrue( $this->registry->can_process_locally( $context, true ) );
	}

	/**
	 * Test upload MIME map includes sequence aliases when HEIC is supported.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetHeicUploadMimesIncludesSequences() {
		$mimes = $this->registry->get_heic_upload_mimes( true );
		$this->assertSame( 'image/heic-sequence', $mimes['heics'] );
		$this->assertSame( 'image/heif-sequence', $mimes['heifs'] );
		$this->assertSame( [], $this->registry->get_heic_upload_mimes( false ) );
	}
}
