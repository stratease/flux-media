<?php
/**
 * Unit tests for external service constant alignment.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\ExternalServiceConfig;
use PHPUnit\Framework\TestCase;

/**
 * External service URL/timeout resolver tests.
 *
 * @since 4.3.0
 */
class ExternalServiceConfigTest extends TestCase {

	/**
	 * Defaults apply when no overrides are configured.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testDefaultsWhenNoOverrides() {
		$config = ExternalServiceConfig::resolve( null, null, null, null );

		$this->assertSame( ExternalServiceConfig::DEFAULT_URL, $config['url'] );
		$this->assertSame( ExternalServiceConfig::DEFAULT_TIMEOUT, $config['timeout'] );
	}

	/**
	 * Plugin-only overrides populate the resolved values.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testPluginOnlyOverride() {
		$config = ExternalServiceConfig::resolve(
			'https://plugin.example.com',
			null,
			30,
			null
		);

		$this->assertSame( 'https://plugin.example.com', $config['url'] );
		$this->assertSame( 30, $config['timeout'] );
	}

	/**
	 * Common-only overrides populate the resolved values.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testCommonOnlyOverride() {
		$config = ExternalServiceConfig::resolve(
			null,
			'https://common.example.com',
			null,
			45
		);

		$this->assertSame( 'https://common.example.com', $config['url'] );
		$this->assertSame( 45, $config['timeout'] );
	}

	/**
	 * Explicit common overrides win when both plugin and common are set.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testCommonTakesPrecedenceOverPlugin() {
		$config = ExternalServiceConfig::resolve(
			'https://plugin.example.com',
			'https://common.example.com',
			30,
			90
		);

		$this->assertSame( 'https://common.example.com', $config['url'] );
		$this->assertSame( 90, $config['timeout'] );
	}

	/**
	 * Timeout values are cast to integers.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testTimeoutCastsToInteger() {
		$config = ExternalServiceConfig::resolve(
			null,
			null,
			'25',
			null
		);

		$this->assertSame( 25, $config['timeout'] );
	}
}
