<?php
/**
 * Logger utility class.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Utils;

use Monolog\Logger as MonologLogger;
use FluxMedia\Utils\DatabaseHandler;

/**
 * Logger utility class using Monolog.
 *
 * @since 1.0.0
 */
class Logger {

	/**
	 * Monolog logger instance.
	 *
	 * @since 1.0.0
	 * @var MonologLogger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->logger = new MonologLogger( 'flux-media' );
		$this->setup_handlers();
	}

	/**
	 * Setup log handlers.
	 *
	 * @since 1.0.0
	 */
	private function setup_handlers() {
		// Check if logging is disabled
		$options = get_option( 'flux_media_options', [] );
		$logging_enabled = $options['enable_logging'] ?? true;
		
		if ( ! $logging_enabled ) {
			// If logging is disabled, don't add any handlers
			return;
		}

		// Database handler for all log levels (DEBUG and above)
		$database_handler = new DatabaseHandler( MonologLogger::DEBUG );
		$this->logger->pushHandler( $database_handler );
	}

	/**
	 * Log debug message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function debug( $message, $context = [] ) {
		$this->logger->debug( $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function info( $message, $context = [] ) {
		$this->logger->info( $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function warning( $message, $context = [] ) {
		$this->logger->warning( $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function error( $message, $context = [] ) {
		$this->logger->error( $message, $context );
	}

	/**
	 * Log critical message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function critical( $message, $context = [] ) {
		$this->logger->critical( $message, $context );
	}
}
