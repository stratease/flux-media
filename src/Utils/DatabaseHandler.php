<?php
/**
 * Database handler for Monolog logger.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Utils;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

/**
 * Database handler for storing logs in WordPress database.
 *
 * @since 1.0.0
 */
class DatabaseHandler extends AbstractProcessingHandler {

	/**
	 * Database table name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param int    $level  The minimum logging level at which this handler will be triggered.
	 * @param bool   $bubble Whether the messages that are handled can bubble up the stack or not.
	 */
	public function __construct( $level = \Monolog\Logger::DEBUG, $bubble = true ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'flux_media_logs';
		parent::__construct( $level, $bubble );
	}

	/**
	 * Write the log record to the database.
	 *
	 * @since 1.0.0
	 * @param LogRecord $record The log record to write.
	 */
	protected function write( LogRecord $record ): void {
		global $wpdb;

		// Check if logging is disabled
		$options = get_option( 'flux_media_options', [] );
		if ( ! ( $options['enable_logging'] ?? true ) ) {
			return;
		}

		$wpdb->insert(
			$this->table_name,
			[
				'level'     => $record->level->name,
				'message'   => $record->message,
				'context'   => ! empty( $record->context ) ? wp_json_encode( $record->context ) : null,
				'created_at' => $record->datetime->format( 'Y-m-d H:i:s' ),
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);
	}
}
