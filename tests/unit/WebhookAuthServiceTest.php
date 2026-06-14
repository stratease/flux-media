<?php
/**
 * Unit tests for WebhookAuthService.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.1.6
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\WebhookAuthService;
use PHPUnit\Framework\TestCase;

/**
 * WebhookAuthService unit tests (pure logic; no WordPress bootstrap).
 *
 * @since 4.1.6
 */
class WebhookAuthServiceTest extends TestCase {

	/**
	 * Set up test constants and WordPress function stubs.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL' ) ) {
			define( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL', 'https://api.fluxplugins.com' );
		}
		if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS' ) ) {
			define( 'FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS', 'storage.googleapis.com,cdn.fluxplugins.com' );
		}
		if ( ! defined( 'FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST' ) ) {
			define( 'FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST', '' );
		}

		if ( ! function_exists( 'esc_url_raw' ) ) {
			/**
			 * Minimal esc_url_raw stub for unit tests.
			 *
			 * @param string $url URL.
			 * @return string
			 */
			function esc_url_raw( $url ) {
				$filtered = filter_var( $url, FILTER_VALIDATE_URL );
				return is_string( $filtered ) ? $filtered : '';
			}
		}
	}

	/**
	 * Test account ID validation with hash_equals semantics.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testValidateAccountId() {
		$uuid = '550e8400-e29b-41d4-a716-446655440000';

		$this->assertTrue( WebhookAuthService::validate_account_id( $uuid, $uuid ) );
		$this->assertFalse( WebhookAuthService::validate_account_id( $uuid, 'wrong-id' ) );
		$this->assertFalse( WebhookAuthService::validate_account_id( '', $uuid ) );
		$this->assertFalse( WebhookAuthService::validate_account_id( $uuid, '' ) );
	}

	/**
	 * Test incoming status resolution from cdn_urls payload.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testResolveIncomingStatus() {
		$this->assertSame( 'failed', WebhookAuthService::resolve_incoming_status( null ) );
		$this->assertSame( 'failed', WebhookAuthService::resolve_incoming_status( [] ) );
		$this->assertSame(
			'completed',
			WebhookAuthService::resolve_incoming_status(
				[
					'full' => [
						'webp' => [
							'url' => 'https://cdn.fluxplugins.com/file.webp',
							'filesize' => 100,
						],
					],
				]
			)
		);
	}

	/**
	 * Test job state transition rules.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testValidateJobStateTransition() {
		$this->assertTrue( WebhookAuthService::validate_job_state_transition( 'queued', 'completed' ) );
		$this->assertTrue( WebhookAuthService::validate_job_state_transition( 'processing', 'failed' ) );
		$this->assertFalse( WebhookAuthService::validate_job_state_transition( null, 'completed' ) );
		$this->assertFalse( WebhookAuthService::validate_job_state_transition( 'completed', 'completed' ) );
		$this->assertFalse( WebhookAuthService::validate_job_state_transition( 'failed', 'failed' ) );
		$this->assertFalse( WebhookAuthService::validate_job_state_transition( 'queued', 'unknown' ) );
	}

	/**
	 * Test CDN host allowlist parsing.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testGetAllowedCdnHosts() {
		$hosts = WebhookAuthService::get_allowed_cdn_hosts();

		$this->assertContains( 'storage.googleapis.com', $hosts );
		$this->assertContains( 'cdn.fluxplugins.com', $hosts );
		$this->assertContains( 'api.fluxplugins.com', $hosts );
	}

	/**
	 * Test parse_host_list helper.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testParseHostList() {
		$this->assertSame(
			[ 'a.example', 'b.example' ],
			WebhookAuthService::parse_host_list( 'a.example, b.example' )
		);
		$this->assertSame( [], WebhookAuthService::parse_host_list( '' ) );
	}

	/**
	 * Test URL host validation against allowlist.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testValidateUrlHost() {
		$allowed = [ 'cdn.fluxplugins.com', 'storage.googleapis.com' ];

		$this->assertTrue(
			WebhookAuthService::validate_url_host( 'https://cdn.fluxplugins.com/path/file.webp', $allowed )
		);
		$this->assertSame(
			'CDN host not allowed: evil.example',
			WebhookAuthService::validate_url_host( 'https://evil.example/file.webp', $allowed )
		);
	}

	/**
	 * Test is_host_allowed helper.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testIsHostAllowed() {
		$allowed = [ 'cdn.fluxplugins.com' ];
		$this->assertTrue( WebhookAuthService::is_host_allowed( 'cdn.fluxplugins.com', $allowed ) );
		$this->assertFalse( WebhookAuthService::is_host_allowed( 'evil.example', $allowed ) );
	}

	/**
	 * Test full CDN URLs payload validation.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testValidateCdnUrls() {
		$valid = [
			'full' => [
				'webp' => [
					'url' => 'https://storage.googleapis.com/bucket/file.webp',
					'filesize' => 500,
				],
			],
		];

		$this->assertTrue( WebhookAuthService::validate_cdn_urls( $valid ) );

		$invalid_host = [
			'full' => [
				'webp' => [
					'url' => 'https://evil.example/file.webp',
					'filesize' => 500,
				],
			],
		];

		$this->assertStringContainsString( 'not allowed', WebhookAuthService::validate_cdn_urls( $invalid_host ) );

		$this->assertSame( 'No valid CDN URLs in payload', WebhookAuthService::validate_cdn_urls( [ 'full' => [] ] ) );
	}

	/**
	 * Test rate limit pure helper.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testIsWithinRateLimit() {
		$this->assertTrue( WebhookAuthService::is_within_rate_limit( 0, 60 ) );
		$this->assertTrue( WebhookAuthService::is_within_rate_limit( 59, 60 ) );
		$this->assertFalse( WebhookAuthService::is_within_rate_limit( 60, 60 ) );
	}

	/**
	 * Test rate limit transient key is stable for an account ID.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testBuildRateLimitTransientKey() {
		$key = WebhookAuthService::build_rate_limit_transient_key( 'test-uuid' );
		$this->assertSame( $key, WebhookAuthService::build_rate_limit_transient_key( 'test-uuid' ) );
		$this->assertStringStartsWith( 'flux_mo_webhook_rl_', $key );
	}

	/**
	 * Test extract_url_host helper.
	 *
	 * @since 4.1.6
	 * @return void
	 */
	public function testExtractUrlHost() {
		$this->assertSame(
			'cdn.fluxplugins.com',
			WebhookAuthService::extract_url_host( 'https://cdn.fluxplugins.com/media/file.webp' )
		);
		$this->assertSame( '', WebhookAuthService::extract_url_host( 'not-a-url' ) );
	}
}
