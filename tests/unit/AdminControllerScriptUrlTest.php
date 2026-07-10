<?php
/**
 * Ensures admin script URL logic does not ship hardcoded localhost dev URLs (WordPress.org guideline).
 *
 * @package FluxMedia
 * @since 4.2.0
 */

use PHPUnit\Framework\TestCase;

final class AdminControllerScriptUrlTest extends TestCase {

	public function test_admin_controller_sources_contain_no_hardcoded_localhost_dev_port(): void {
		$path = dirname( __DIR__, 2 ) . '/app/Http/Controllers/AdminController.php';
		$this->assertFileExists( $path );
		$src = (string) file_get_contents( $path );
		$this->assertStringNotContainsString( 'localhost:3000', $src );
	}
}
