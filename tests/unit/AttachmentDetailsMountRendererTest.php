<?php
/**
 * Unit tests for AttachmentDetailsMountRenderer.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\AttachmentDetailsMountRenderer;
use PHPUnit\Framework\TestCase;

/**
 * AttachmentDetailsMountRenderer unit tests.
 *
 * @since 4.3.0
 */
class AttachmentDetailsMountRendererTest extends TestCase {

	/**
	 * Reset stubs.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fmo_test_capabilities'] = [];
	}

	/**
	 * Mount HTML includes attachment ID and skeleton without embedded JSON payload.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testModifyAttachmentFieldsEmitsSkeletonWithoutPayload() {
		$post = (object) [
			'ID'        => 901,
			'post_type' => 'attachment',
		];

		$renderer = new AttachmentDetailsMountRenderer();
		$fields   = $renderer->modify_attachment_fields( [], $post );

		$this->assertArrayHasKey( 'flux_media_optimizer', $fields );
		$this->assertFalse( $fields['flux_media_optimizer']['show_in_modal'] );
		$this->assertTrue( $fields['flux_media_optimizer']['show_in_edit'] );
		$html = $fields['flux_media_optimizer']['html'];
		$this->assertStringContainsString( 'data-flux-media-attachment-id="901"', $html );
		$this->assertStringContainsString( 'data-flux-media-attachment-skeleton="1"', $html );
		$this->assertStringContainsString( 'data-flux-media-attachment-app="1"', $html );
		$this->assertStringNotContainsString( 'application/json', $html );
		$this->assertStringNotContainsString( 'data-flux-media-attachment-data', $html );
		$this->assertStringNotContainsString( 'fluxMediaConvertAttachment', $html );
	}

	/**
	 * build_mount_html matches the field HTML payload.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testBuildMountHtmlMatchesFieldHtml() {
		$renderer = new AttachmentDetailsMountRenderer();
		$html     = $renderer->build_mount_html( 903 );

		$this->assertStringContainsString( 'data-flux-media-attachment-id="903"', $html );
		$this->assertStringContainsString( 'id="flux-media-optimizer-attachment-903"', $html );
		$this->assertStringContainsString( 'data-flux-media-attachment-skeleton="1"', $html );
	}

	/**
	 * Users without edit_post capability do not receive the mount field.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testModifyAttachmentFieldsSkipsWithoutCapability() {
		$GLOBALS['fmo_test_capabilities']['edit_post:902'] = false;
		$post = (object) [
			'ID'        => 902,
			'post_type' => 'attachment',
		];

		$renderer = new AttachmentDetailsMountRenderer();
		$fields   = $renderer->modify_attachment_fields( [ 'title' => [] ], $post );

		$this->assertArrayNotHasKey( 'flux_media_optimizer', $fields );
		$this->assertArrayHasKey( 'title', $fields );
	}
}
