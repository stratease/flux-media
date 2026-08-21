<?php
/**
 * Unit tests for Database table drop helpers.
 *
 * @package FluxMedia\Tests\Unit
 * @since 4.2.1
 */

namespace FluxMedia\Tests\Unit;

use FluxMedia\App\Services\Database;
use PHPUnit\Framework\TestCase;

/**
 * Database drop_table_if_exists tests.
 *
 * @since 4.2.1
 */
class DatabaseDropTablesTest extends TestCase {

	/**
	 * @since 4.2.1
	 * @var object|null
	 */
	private $wpdb_backup;

	/**
	 * Restore global $wpdb after each test.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->wpdb_backup;
		parent::tearDown();
	}

	/**
	 * Test DROP uses backtick-quoted identifiers, not prepared string literals.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testDropTableIfExistsUsesIdentifierSql() {
		$this->wpdb_backup = $GLOBALS['wpdb'] ?? null;

		$mock = new class() {
			/** @var array<int, string> */
			public $queries = [];

			/**
			 * @param string $query SQL.
			 * @return int
			 */
			public function query( $query ) {
				$this->queries[] = $query;
				return 1;
			}
		};
		$GLOBALS['wpdb'] = $mock;

		Database::drop_table_if_exists( 'wp_flux_media_optimizer_conversions' );

		$this->assertCount( 1, $mock->queries );
		$this->assertSame(
			'DROP TABLE IF EXISTS `wp_flux_media_optimizer_conversions`',
			$mock->queries[0]
		);
	}

	/**
	 * Invalid table names must not execute SQL.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testDropTableIfExistsRejectsUnsafeIdentifiers() {
		$this->wpdb_backup = $GLOBALS['wpdb'] ?? null;

		$mock = new class() {
			/** @var array<int, string> */
			public $queries = [];

			/**
			 * @param string $query SQL.
			 * @return int
			 */
			public function query( $query ) {
				$this->queries[] = $query;
				return 1;
			}
		};
		$GLOBALS['wpdb'] = $mock;

		Database::drop_table_if_exists( 'wp_flux; DROP TABLE users; --' );

		$this->assertSame( [], $mock->queries );
	}

	/**
	 * Table name list includes current and legacy tables.
	 *
	 * @since 4.2.1
	 * @return void
	 */
	public function testGetTableNamesIncludesLegacyTables() {
		$this->wpdb_backup = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb']   = (object) [ 'prefix' => 'wp_' ];

		$tables = Database::get_table_names();

		$this->assertContains( 'wp_flux_media_optimizer_conversions', $tables );
		$this->assertContains( 'wp_flux_media_optimizer_logs', $tables );
		$this->assertContains( 'wp_flux_media_optimizer_external_jobs', $tables );
	}
}
