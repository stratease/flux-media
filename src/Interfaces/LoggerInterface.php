<?php
/**
 * Logger interface for Flux Media plugin.
 *
 * @package FluxMedia\Interfaces
 * @since 1.0.0
 */

namespace FluxMedia\Interfaces;

/**
 * Logger interface defining logging capabilities.
 *
 * @since 1.0.0
 */
interface LoggerInterface {

	/**
	 * Log debug message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function debug( $message, $context = [] );

	/**
	 * Log info message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function info( $message, $context = [] );

	/**
	 * Log warning message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function warning( $message, $context = [] );

	/**
	 * Log error message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function error( $message, $context = [] );

	/**
	 * Log critical message.
	 *
	 * @since 1.0.0
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	public function critical( $message, $context = [] );

	/**
	 * Log an operation with structured context.
	 *
	 * @since 1.0.0
	 * @param string $level Log level (debug, info, warning, error, critical).
	 * @param string $operation Operation being performed.
	 * @param string $message Human-readable message.
	 * @param array  $context Additional structured context.
	 */
	public function log_operation( $level, $operation, $message, $context = [] );

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
	public function log_conversion( $level, $source_path, $target_format, $message, $context = [] );

	/**
	 * Log a processor availability issue.
	 *
	 * @since 1.0.0
	 * @param string $processor_type Type of processor (GD, Imagick, FFmpeg).
	 * @param string $reason Reason for unavailability.
	 * @param array  $context Additional context.
	 */
	public function log_processor_unavailable( $processor_type, $reason, $context = [] );

	/**
	 * Log a format support issue.
	 *
	 * @since 1.0.0
	 * @param string $processor_type Type of processor.
	 * @param string $format Format that's not supported.
	 * @param string $reason Reason for lack of support.
	 * @param array  $context Additional context.
	 */
	public function log_format_unsupported( $processor_type, $format, $reason, $context = [] );

	/**
	 * Log a system resource issue.
	 *
	 * @since 1.0.0
	 * @param string $resource_type Type of resource (memory, disk, execution_time).
	 * @param string $issue Description of the issue.
	 * @param array  $context Additional context.
	 */
	public function log_resource_issue( $resource_type, $issue, $context = [] );

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
	public function log_filesystem( $level, $operation, $file_path, $message, $context = [] );
}
