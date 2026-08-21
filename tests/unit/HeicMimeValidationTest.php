<?php
/**
 * Unit tests for hardened HEIC MIME validation.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\WordPressProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * HEIC filetype filter hardening tests.
 *
 * @since 4.3.0
 */
class HeicMimeValidationTest extends TestCase {

	/**
	 * Invoke fix_heic_filetype without constructing full WordPressProvider dependencies.
	 *
	 * @since 4.3.0
	 * @param array       $data      File data.
	 * @param string      $file      Temp path.
	 * @param string      $filename  Filename.
	 * @param string|null $real_mime Real MIME.
	 * @return array
	 */
	private function invoke_fix_heic( array $data, string $file, string $filename, $real_mime ): array {
		$ref  = new ReflectionClass( WordPressProvider::class );
		$inst = $ref->newInstanceWithoutConstructor();
		return $inst->fix_heic_filetype( $data, $file, $filename, [], $real_mime );
	}

	/**
	 * Conflicting real MIME must not force HEIC type.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testConflictingRealMimeRejected() {
		$data = [
			'ext'  => false,
			'type' => false,
		];

		$result = $this->invoke_fix_heic( $data, __FILE__, 'photo.heic', 'image/jpeg' );
		$this->assertSame( $data, $result );
	}

	/**
	 * Missing temp file must not force HEIC type.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testMissingTempFileRejected() {
		$data = [
			'ext'  => false,
			'type' => false,
		];

		$result = $this->invoke_fix_heic(
			$data,
			'/tmp/fmo-missing-' . uniqid( '', true ) . '.heic',
			'photo.heic',
			'image/heic'
		);
		$this->assertSame( $data, $result );
	}

	/**
	 * Non-HEIC extension is ignored.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testNonHeicExtensionIgnored() {
		$data = [
			'ext'  => 'jpg',
			'type' => 'image/jpeg',
		];

		$result = $this->invoke_fix_heic( $data, __FILE__, 'photo.jpg', 'image/jpeg' );
		$this->assertSame( $data, $result );
	}
}
