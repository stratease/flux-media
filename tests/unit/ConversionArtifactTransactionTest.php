<?php
/**
 * Unit tests for ConversionArtifactTransaction.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.3.0
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\ConversionArtifactTransaction;
use PHPUnit\Framework\TestCase;

/**
 * ConversionArtifactTransaction unit tests.
 *
 * @since 4.3.0
 */
class ConversionArtifactTransactionTest extends TestCase {

	/**
	 * Temporary directory for staging fixtures.
	 *
	 * @var string
	 */
	private $dir;

	/**
	 * Set up temp directory.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/fmo-artifact-' . uniqid( '', true );
		mkdir( $this->dir );
	}

	/**
	 * Tear down temp directory.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( glob( $this->dir . '/*' ) ?: [] as $file ) {
			@unlink( $file );
		}
		@rmdir( $this->dir );
		parent::tearDown();
	}

	/**
	 * Commit publishes staged file and leaves prior file replaced.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testCommitPublishesStagedFile() {
		$final   = $this->dir . '/out.webp';
		$prior   = "prior-bytes";
		file_put_contents( $final, $prior );

		$tx      = new ConversionArtifactTransaction();
		$staging = $tx->stage( $final );
		file_put_contents( $staging, 'new-bytes' );

		$this->assertTrue( $tx->commit() );
		$this->assertSame( 'new-bytes', file_get_contents( $final ) );
		$this->assertFalse( file_exists( $staging ) );
	}

	/**
	 * Rollback removes staged file and preserves prior destination.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testRollbackPreservesPriorDestination() {
		$final = $this->dir . '/out.webp';
		file_put_contents( $final, 'prior-bytes' );

		$tx      = new ConversionArtifactTransaction();
		$staging = $tx->stage( $final );
		file_put_contents( $staging, 'new-bytes' );
		$tx->rollback();

		$this->assertFalse( file_exists( $staging ) );
		$this->assertSame( 'prior-bytes', file_get_contents( $final ) );
	}

	/**
	 * Multi-file commit publishes every staged artifact.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testCommitPublishesMultipleStagedFiles() {
		$final_a = $this->dir . '/a.webp';
		$final_b = $this->dir . '/b.webp';

		$tx        = new ConversionArtifactTransaction();
		$staging_a = $tx->stage( $final_a );
		$staging_b = $tx->stage( $final_b );
		file_put_contents( $staging_a, 'a-bytes' );
		file_put_contents( $staging_b, 'b-bytes' );

		$this->assertTrue( $tx->has_staged() );
		$this->assertTrue( $tx->commit() );
		$this->assertFalse( $tx->has_staged() );
		$this->assertSame( 'a-bytes', file_get_contents( $final_a ) );
		$this->assertSame( 'b-bytes', file_get_contents( $final_b ) );
	}

	/**
	 * Missing staging file aborts commit and rolls remaining staged files back.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testCommitFailsWhenStagingFileMissing() {
		$final_a = $this->dir . '/a.webp';
		$final_b = $this->dir . '/b.webp';
		file_put_contents( $final_a, 'prior-a' );

		$tx        = new ConversionArtifactTransaction();
		$staging_a = $tx->stage( $final_a );
		$staging_b = $tx->stage( $final_b );
		file_put_contents( $staging_a, 'new-a' );
		// Intentionally leave $staging_b missing.

		$this->assertFalse( $tx->commit() );
		$this->assertFalse( file_exists( $staging_a ) );
		$this->assertFalse( file_exists( $staging_b ) );
		$this->assertSame( 'prior-a', file_get_contents( $final_a ) );
		$this->assertFalse( file_exists( $final_b ) );
	}

	/**
	 * register() associates an existing staging path for later commit.
	 *
	 * @since 4.3.0
	 * @return void
	 */
	public function testRegisterThenCommitPublishesFile() {
		$final   = $this->dir . '/registered.webp';
		$staging = $this->dir . '/custom-stage.tmp';
		file_put_contents( $staging, 'registered-bytes' );

		$tx = new ConversionArtifactTransaction();
		$tx->register( $staging, $final );

		$this->assertTrue( $tx->commit() );
		$this->assertSame( 'registered-bytes', file_get_contents( $final ) );
		$this->assertFalse( file_exists( $staging ) );
	}
}
