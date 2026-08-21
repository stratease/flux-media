<?php
/**
 * Unit tests for Settings uninstall and option name helpers.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.1
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Settings option name and uninstall list tests.
 *
 * @since 4.2.1
 */
class SettingsOptionNameTest extends TestCase {

	/**
	 * Plugin settings use flux_media_optimizer_options in wp_options.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testGetOptionsOptionName() {
		$this->assertSame( 'flux_media_optimizer_options', Settings::get_options_option_name() );
	}

	/**
	 * Uninstall removes plugin options but not shared suite account ID.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testGetUninstallOptionNames() {
		$options = Settings::get_uninstall_option_names();

		$this->assertContains( 'flux_media_optimizer_options', $options );
		$this->assertContains( 'flux_media_optimizer_db_version', $options );
		$this->assertNotContains( 'flux_media_optimizer_settings', $options );
		$this->assertNotContains( 'flux-plugins_account_id', $options );
	}

	/**
	 * Image conversion settings include hybrid approach flag for ImageConverter callers.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testGetImageConversionSettingsIncludesHybridFlag() {
		$settings = Settings::get_image_conversion_settings();

		$this->assertArrayHasKey( 'webp_quality', $settings );
		$this->assertArrayHasKey( 'avif_quality', $settings );
		$this->assertArrayHasKey( 'avif_speed', $settings );
		$this->assertArrayHasKey( 'image_hybrid_approach', $settings );
		$this->assertIsBool( $settings['image_hybrid_approach'] );
	}

	/**
	 * Reset in-memory options before each test that mutates settings.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fmo_test_options'] = [];
	}

	/**
	 * Provide boolean wire values and expected stored booleans.
	 *
	 * @since 4.3.0
	 * @return array<string, array{0: mixed, 1: bool}>
	 */
	public function booleanSanitizationProvider() {
		return [
			'bool false' => [ false, false ],
			'int zero' => [ 0, false ],
			'string false' => [ 'false', false ],
			'string zero' => [ '0', false ],
			'string no' => [ 'no', false ],
			'string off' => [ 'off', false ],
			'bool true' => [ true, true ],
			'int one' => [ 1, true ],
			'string true' => [ 'true', true ],
			'string one' => [ '1', true ],
			'string yes' => [ 'yes', true ],
			'string on' => [ 'on', true ],
		];
	}

	/**
	 * External service toggle must persist WordPress-compatible boolean values.
	 *
	 * @since 4.3.0
	 * @dataProvider booleanSanitizationProvider
	 * @param mixed $input    Raw value as received from REST/options payload.
	 * @param bool  $expected Expected stored boolean.
	 * @return void
	 */
	public function testExternalServiceEnabledBooleanSanitization( $input, $expected ) {
		$this->assertTrue( Settings::set( 'external_service_enabled', $input ) );
		$this->assertSame( $expected, Settings::get( 'external_service_enabled' ) );
	}

	/**
	 * Unknown settings keys remain rejected to prevent option injection.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testSetRejectsUnknownSettingKey() {
		$this->assertFalse( Settings::set( 'not_a_real_setting', true ) );
		$this->assertArrayNotHasKey( 'flux_media_optimizer_options', $GLOBALS['fmo_test_options'] );
	}
}
