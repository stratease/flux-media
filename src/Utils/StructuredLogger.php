<?php
/**
 * Structured logger utility for consistent error reporting.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Utils;

/**
 * Structured logger for consistent error messages and troubleshooting.
 *
 * @since 1.0.0
 */
class StructuredLogger {

	/**
	 * Logger instance.
	 *
	 * @since 1.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Log image processor availability issues.
	 *
	 * @since 1.0.0
	 * @param string $processor_type Processor type (GD, Imagick).
	 * @param string $reason Reason for unavailability.
	 * @param array  $context Additional context.
	 */
	public function log_image_processor_unavailable( $processor_type, $reason, $context = [] ) {
		$message = "[IMAGE_PROCESSOR] {$processor_type} not available: {$reason}";
		$this->logger->warning( $message, array_merge( $context, [
			'component' => 'image_processor',
			'processor_type' => $processor_type,
			'unavailability_reason' => $reason,
		] ) );
	}

	/**
	 * Log image format support issues.
	 *
	 * @since 1.0.0
	 * @param string $processor_type Processor type (GD, Imagick).
	 * @param string $format Image format (WebP, AVIF).
	 * @param string $reason Reason for lack of support.
	 * @param array  $context Additional context.
	 */
	public function log_image_format_unsupported( $processor_type, $format, $reason, $context = [] ) {
		$message = "[IMAGE_FORMAT] {$processor_type} does not support {$format}: {$reason}";
		$this->logger->warning( $message, array_merge( $context, [
			'component' => 'image_format',
			'processor_type' => $processor_type,
			'format' => $format,
			'unsupported_reason' => $reason,
		] ) );
	}

	/**
	 * Log image conversion failures.
	 *
	 * @since 1.0.0
	 * @param string $source_path Source image path.
	 * @param string $target_format Target format (WebP, AVIF).
	 * @param string $error_message Error message.
	 * @param array  $context Additional context.
	 */
	public function log_image_conversion_failed( $source_path, $target_format, $error_message, $context = [] ) {
		$filename = basename( $source_path );
		$message = "[IMAGE_CONVERSION] Failed to convert {$filename} to {$target_format}: {$error_message}";
		$this->logger->error( $message, array_merge( $context, [
			'component' => 'image_conversion',
			'source_file' => $filename,
			'source_path' => $source_path,
			'target_format' => $target_format,
			'error_message' => $error_message,
		] ) );
	}

	/**
	 * Log video processor availability issues.
	 *
	 * @since 1.0.0
	 * @param string $reason Reason for unavailability.
	 * @param array  $context Additional context.
	 */
	public function log_video_processor_unavailable( $reason, $context = [] ) {
		$message = "[VIDEO_PROCESSOR] FFmpeg not available: {$reason}";
		$this->logger->warning( $message, array_merge( $context, [
			'component' => 'video_processor',
			'unavailability_reason' => $reason,
		] ) );
	}

	/**
	 * Log video format support issues.
	 *
	 * @since 1.0.0
	 * @param string $format Video format (AV1, WebM).
	 * @param string $reason Reason for lack of support.
	 * @param array  $context Additional context.
	 */
	public function log_video_format_unsupported( $format, $reason, $context = [] ) {
		$message = "[VIDEO_FORMAT] FFmpeg does not support {$format}: {$reason}";
		$this->logger->warning( $message, array_merge( $context, [
			'component' => 'video_format',
			'format' => $format,
			'unsupported_reason' => $reason,
		] ) );
	}

	/**
	 * Log video conversion failures.
	 *
	 * @since 1.0.0
	 * @param string $source_path Source video path.
	 * @param string $target_format Target format (AV1, WebM).
	 * @param string $error_message Error message.
	 * @param array  $context Additional context.
	 */
	public function log_video_conversion_failed( $source_path, $target_format, $error_message, $context = [] ) {
		$filename = basename( $source_path );
		$message = "[VIDEO_CONVERSION] Failed to convert {$filename} to {$target_format}: {$error_message}";
		$this->logger->error( $message, array_merge( $context, [
			'component' => 'video_conversion',
			'source_file' => $filename,
			'source_path' => $source_path,
			'target_format' => $target_format,
			'error_message' => $error_message,
		] ) );
	}

	/**
	 * Log PHP version compatibility issues.
	 *
	 * @since 1.0.0
	 * @param string $component Component affected (GD, Imagick, FFmpeg).
	 * @param string $required_version Required PHP version.
	 * @param string $current_version Current PHP version.
	 * @param array  $context Additional context.
	 */
	public function log_php_version_incompatible( $component, $required_version, $current_version, $context = [] ) {
		$message = "[PHP_VERSION] {$component} requires PHP {$required_version}+, current: {$current_version}";
		$this->logger->warning( $message, array_merge( $context, [
			'component' => 'php_version',
			'affected_component' => $component,
			'required_version' => $required_version,
			'current_version' => $current_version,
		] ) );
	}

	/**
	 * Log system resource issues.
	 *
	 * @since 1.0.0
	 * @param string $resource_type Resource type (memory, disk, execution_time).
	 * @param string $issue Description of the issue.
	 * @param array  $context Additional context.
	 */
	public function log_system_resource_issue( $resource_type, $issue, $context = [] ) {
		$message = "[SYSTEM_RESOURCE] {$resource_type} issue: {$issue}";
		$this->logger->warning( $message, array_merge( $context, [
			'component' => 'system_resource',
			'resource_type' => $resource_type,
			'issue_description' => $issue,
		] ) );
	}

	/**
	 * Log file system issues.
	 *
	 * @since 1.0.0
	 * @param string $operation Operation being performed.
	 * @param string $file_path File path involved.
	 * @param string $error_message Error message.
	 * @param array  $context Additional context.
	 */
	public function log_filesystem_issue( $operation, $file_path, $error_message, $context = [] ) {
		$filename = basename( $file_path );
		$message = "[FILESYSTEM] {$operation} failed for {$filename}: {$error_message}";
		$this->logger->error( $message, array_merge( $context, [
			'component' => 'filesystem',
			'operation' => $operation,
			'file_name' => $filename,
			'file_path' => $file_path,
			'error_message' => $error_message,
		] ) );
	}

	/**
	 * Log quota-related issues.
	 *
	 * @since 1.0.0
	 * @param string $quota_type Quota type (images, videos).
	 * @param string $issue Description of the issue.
	 * @param array  $context Additional context.
	 */
	public function log_quota_issue( $quota_type, $issue, $context = [] ) {
		$message = "[QUOTA] {$quota_type} quota issue: {$issue}";
		$this->logger->warning( $message, array_merge( $context, [
			'component' => 'quota',
			'quota_type' => $quota_type,
			'issue_description' => $issue,
		] ) );
	}

	/**
	 * Log successful operations for debugging.
	 *
	 * @since 1.0.0
	 * @param string $operation Operation performed.
	 * @param string $details Details of the operation.
	 * @param array  $context Additional context.
	 */
	public function log_operation_success( $operation, $details, $context = [] ) {
		$message = "[SUCCESS] {$operation}: {$details}";
		$this->logger->info( $message, array_merge( $context, [
			'component' => 'operation',
			'operation' => $operation,
			'details' => $details,
		] ) );
	}
}
