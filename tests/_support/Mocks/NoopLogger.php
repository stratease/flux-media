<?php
/**
 * Noop Logger implementation for testing.
 *
 * @package FluxMedia\Tests\Support\Mocks
 * @since 1.0.0
 */

namespace FluxMedia\Tests\Support\Mocks;

use FluxMedia\Interfaces\LoggerInterface;

/**
 * Noop Logger that does nothing - used for testing to avoid WordPress dependencies.
 *
 * @since 1.0.0
 */
class NoopLogger implements LoggerInterface {

	/**
	 * Log debug message (noop).
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function debug( $message, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log info message (noop).
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function info( $message, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log warning message (noop).
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function warning( $message, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log error message (noop).
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function error( $message, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log critical message (noop).
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function critical( $message, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log an operation with structured context (noop).
	 *
	 * @since 1.0.0
	 * @param string $level Log level (debug, info, warning, error, critical).
	 * @param string $operation Operation being performed.
	 * @param string $message Human-readable message.
	 * @param array  $context Additional structured context.
	 */
	public function log_operation( $level, $operation, $message, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log a conversion operation (noop).
	 *
	 * @since 1.0.0
	 * @param string $level Log level.
	 * @param string $source_path Source file path.
	 * @param string $target_format Target format.
	 * @param string $message Human-readable message.
	 * @param array  $context Additional context.
	 */
	public function log_conversion( $level, $source_path, $target_format, $message, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log a processor availability issue (noop).
	 *
	 * @since 1.0.0
	 * @param string $processor_type Type of processor (GD, Imagick, FFmpeg).
	 * @param string $reason Reason for unavailability.
	 * @param array  $context Additional context.
	 */
	public function log_processor_unavailable( $processor_type, $reason, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log a format support issue (noop).
	 *
	 * @since 1.0.0
	 * @param string $processor_type Type of processor.
	 * @param string $format Format that's not supported.
	 * @param string $reason Reason for lack of support.
	 * @param array  $context Additional context.
	 */
	public function log_format_unsupported( $processor_type, $format, $reason, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log a system resource issue (noop).
	 *
	 * @since 1.0.0
	 * @param string $resource_type Type of resource (memory, disk, execution_time).
	 * @param string $issue Description of the issue.
	 * @param array  $context Additional context.
	 */
	public function log_resource_issue( $resource_type, $issue, $context = [] ) {
		// Noop - do nothing
	}

	/**
	 * Log a filesystem operation (noop).
	 *
	 * @since 1.0.0
	 * @param string $level Log level.
	 * @param string $operation Operation being performed.
	 * @param string $file_path File path involved.
	 * @param string $message Human-readable message.
	 * @param array  $context Additional context.
	 */
	public function log_filesystem( $level, $operation, $file_path, $message, $context = [] ) {
		// Noop - do nothing
	}
}
