<?php
/**
 * Unit tests for HeifAnimationPolicy.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\Converter;
use FluxMedia\App\Services\HeifAnimationPolicy;
use PHPUnit\Framework\TestCase;

/**
 * HeifAnimationPolicy unit tests.
 *
 * @since 4.3.0
 */
class HeifAnimationPolicyTest extends TestCase {

	/**
	 * Policy under test.
	 *
	 * @since 4.3.0
	 * @var HeifAnimationPolicy
	 */
	private $policy;

	/**
	 * Set up test environment.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		$this->policy = new HeifAnimationPolicy();
	}

	/**
	 * WebP enabled prefers animated WebP when FFmpeg is available.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testWebpEnabledWithFfmpegUsesAnimatedWebp() {
		$result = $this->policy->resolve_sequence_outputs(
			[ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ],
			true,
			false
		);

		$this->assertTrue( $result['use_animated_webp'] );
		$this->assertTrue( $result['preserve_animation'] );
		$this->assertContains( Converter::FORMAT_WEBP, $result['static_formats'] );
		$this->assertContains( Converter::FORMAT_AVIF, $result['static_formats'] );
	}

	/**
	 * WebP disabled never uses animated WebP; only static formats remain.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testWebpDisabledFallsBackToStaticFormatsOnly() {
		$result = $this->policy->resolve_sequence_outputs(
			[ Converter::FORMAT_AVIF ],
			true,
			false
		);

		$this->assertFalse( $result['use_animated_webp'] );
		$this->assertFalse( $result['preserve_animation'] );
		$this->assertSame( [ Converter::FORMAT_AVIF ], $result['static_formats'] );
	}

	/**
	 * Missing FFmpeg falls back to static even when WebP is enabled.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testMissingFfmpegUsesStaticDespiteWebpEnabled() {
		$result = $this->policy->resolve_sequence_outputs(
			[ Converter::FORMAT_WEBP ],
			false,
			false
		);

		$this->assertFalse( $result['use_animated_webp'] );
		$this->assertTrue( $result['preserve_animation'] );
		$this->assertSame( [ Converter::FORMAT_WEBP ], $result['static_formats'] );
	}

	/**
	 * Hybrid approach enables animation preservation and both formats.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testHybridApproachCountsAsWebpEnabled() {
		$this->assertTrue( $this->policy->should_preserve_animation( [], true ) );
		$this->assertTrue( $this->policy->should_use_animated_webp( [], true, true ) );

		$result = $this->policy->resolve_sequence_outputs( [], true, true );
		$this->assertTrue( $result['use_animated_webp'] );
		$this->assertSame(
			[ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ],
			$result['static_formats']
		);
	}

	/**
	 * should_use_animated_webp requires both WebP settings and FFmpeg.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testShouldUseAnimatedWebpGates() {
		$this->assertTrue(
			$this->policy->should_use_animated_webp( [ Converter::FORMAT_WEBP ], true, false )
		);
		$this->assertFalse(
			$this->policy->should_use_animated_webp( [ Converter::FORMAT_WEBP ], false, false )
		);
		$this->assertFalse(
			$this->policy->should_use_animated_webp( [ Converter::FORMAT_AVIF ], true, false )
		);
	}
}
