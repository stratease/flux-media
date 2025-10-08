<?php
/**
 * Logger utility class with structured logging support.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Utils;

use Monolog\Logger as MonologLogger;
use FluxMedia\Utils\DatabaseHandler;
use FluxMedia\Interfaces\LoggerInterface;

/**
 * Logger utility class using Monolog with simplified structured logging.
 *
 * @since 1.0.0
 */
class Logger implements LoggerInterface {

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

	// ===== Structured Logging Methods =====

	/**
	 * Log an operation with structured context.
	 *
	 * @since 1.0.0
	 * @param string $level Log level (debug, info, warning, error, critical).
	 * @param string $operation Operation being performed.
	 * @param string $message Human-readable message.
	 * @param array  $context Additional structured context.
	 */
	public function log_operation( $level, $operation, $message, $context = [] ) {
		$structured_context = array_merge( $context, [
			'operation' => $operation,
			'component' => $context['component'] ?? 'unknown',
		] );

		$this->logger->$level( $message, $structured_context );
	}

	/**
	 * Log a conversion operation.
	 *
	 * @since 1.0.0
	 * @param string $level Log level.
	 * @param string $source_path Source file path.
	 * @param string $target_format Target format.
	 * @param string $message Human-readable message.
	 * @param array  $context Additional context.
	 */
	public function log_conversion( $level, $source_path, $target_format, $message, $context = [] ) {
		$filename = basename( $source_path );
		$structured_context = array_merge( $context, [
			'operation' => 'conversion',
			'component' => 'converter',
			'source_file' => $filename,
			'source_path' => $source_path,
			'target_format' => $target_format,
		] );

		$this->logger->$level( $message, $structured_context );
	}

	/**
	 * Log a processor availability issue.
	 *
	 * @since 1.0.0
	 * @param string $processor_type Type of processor (GD, Imagick, FFmpeg).
	 * @param string $reason Reason for unavailability.
	 * @param array  $context Additional context.
	 */
	public function log_processor_unavailable( $processor_type, $reason, $context = [] ) {
		$message = "{$processor_type} not available: {$reason}";
		$structured_context = array_merge( $context, [
			'operation' => 'processor_check',
			'component' => 'processor',
			'processor_type' => $processor_type,
			'unavailability_reason' => $reason,
		] );

		$this->logger->warning( $message, $structured_context );
	}

	/**
	 * Log a format support issue.
	 *
	 * @since 1.0.0
	 * @param string $processor_type Type of processor.
	 * @param string $format Format that's not supported.
	 * @param string $reason Reason for lack of support.
	 * @param array  $context Additional context.
	 */
	public function log_format_unsupported( $processor_type, $format, $reason, $context = [] ) {
		$message = "{$processor_type} does not support {$format}: {$reason}";
		$structured_context = array_merge( $context, [
			'operation' => 'format_check',
			'component' => 'format_support',
			'processor_type' => $processor_type,
			'format' => $format,
			'unsupported_reason' => $reason,
		] );

		$this->logger->warning( $message, $structured_context );
	}

	/**
	 * Log a system resource issue.
	 *
	 * @since 1.0.0
	 * @param string $resource_type Type of resource (memory, disk, execution_time).
	 * @param string $issue Description of the issue.
	 * @param array  $context Additional context.
	 */
	public function log_resource_issue( $resource_type, $issue, $context = [] ) {
		$message = "{$resource_type} issue: {$issue}";
		$structured_context = array_merge( $context, [
			'operation' => 'resource_check',
			'component' => 'system_resource',
			'resource_type' => $resource_type,
			'issue_description' => $issue,
		] );

		$this->logger->warning( $message, $structured_context );
	}

	/**
	 * Log a filesystem operation.
	 *
	 * @since 1.0.0
	 * @param string $level Log level.
	 * @param string $operation Operation being performed.
	 * @param string $file_path File path involved.
	 * @param string $message Human-readable message.
	 * @param array  $context Additional context.
	 */
	public function log_filesystem( $level, $operation, $file_path, $message, $context = [] ) {
		$filename = basename( $file_path );
		$structured_context = array_merge( $context, [
			'operation' => $operation,
			'component' => 'filesystem',
			'file_name' => $filename,
			'file_path' => $file_path,
		] );

		$this->logger->$level( $message, $structured_context );
	}
}
