<?php
/**
 * Media Library optimization status column and filters.
 *
 * @package FluxMedia
 * @since 4.2.0
 */

namespace FluxMedia\App\Services;

/**
 * Adds optimization status visibility to the Media Library list table.
 *
 * @since 4.2.0
 */
class MediaLibraryStatusService {

	/**
	 * Column key for the list table.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const COLUMN_KEY = 'flux_optimization_status';

	/**
	 * Query var for list-table filtering.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const FILTER_QUERY_VAR = 'flux_optimization_status';

	/**
	 * Status: optimized.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const STATUS_OPTIMIZED = 'optimized';

	/**
	 * Status: pending external processing.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const STATUS_PENDING = 'pending';

	/**
	 * Status: failed external processing.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const STATUS_FAILED = 'failed';

	/**
	 * Status: conversion disabled.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const STATUS_DISABLED = 'disabled';

	/**
	 * Status: not yet processed.
	 *
	 * @since 4.2.0
	 * @var string
	 */
	const STATUS_UNPROCESSED = 'unprocessed';

	/**
	 * Register Media Library hooks.
	 *
	 * @since 4.2.0
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'manage_upload_columns', [ $this, 'register_columns' ] );
		add_action( 'manage_media_custom_column', [ $this, 'render_column' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ $this, 'register_filters' ] );
		add_action( 'pre_get_posts', [ $this, 'apply_filter' ] );
	}

	/**
	 * Register the optimization status column.
	 *
	 * @since 4.2.0
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function register_columns( $columns ) {
		$columns[ self::COLUMN_KEY ] = \__( 'Optimization', 'flux-media-optimizer' );
		return $columns;
	}

	/**
	 * Render optimization status for a list-table row.
	 *
	 * @since 4.2.0
	 * @param string $column_name Column name.
	 * @param int    $attachment_id Attachment ID.
	 * @return void
	 */
	public function render_column( $column_name, $attachment_id ) {
		if ( self::COLUMN_KEY !== $column_name ) {
			return;
		}

		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 ) {
			return;
		}

		echo wp_kses_post( $this->get_status_badge_html( $attachment_id ) );
	}

	/**
	 * Render optimization status filter dropdown.
	 *
	 * @since 4.2.0
	 * @param string $post_type Current post type.
	 * @return void
	 */
	public function register_filters( $post_type ) {
		if ( 'attachment' !== $post_type ) {
			return;
		}

		$selected = isset( $_GET[ self::FILTER_QUERY_VAR ] )
			? \sanitize_key( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) )
			: '';

		echo '<label for="' . esc_attr( self::FILTER_QUERY_VAR ) . '" class="screen-reader-text">';
		echo esc_html__( 'Filter by optimization status', 'flux-media-optimizer' );
		echo '</label>';
		echo '<select name="' . esc_attr( self::FILTER_QUERY_VAR ) . '" id="' . esc_attr( self::FILTER_QUERY_VAR ) . '">';
		echo '<option value="">' . esc_html__( 'All optimization statuses', 'flux-media-optimizer' ) . '</option>';

		foreach ( self::get_status_options() as $status_key => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $status_key ),
				selected( $selected, $status_key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Apply optimization status filter to Media Library queries.
	 *
	 * @since 4.2.0
	 * @param \WP_Query $query Query object.
	 * @return void
	 */
	public function apply_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		if ( ! isset( $_GET[ self::FILTER_QUERY_VAR ] ) ) {
			return;
		}

		$status = \sanitize_key( wp_unslash( $_GET[ self::FILTER_QUERY_VAR ] ) );
		if ( '' === $status || ! self::is_valid_status( $status ) ) {
			return;
		}

		$meta_query = self::build_filter_meta_query( $status );
		if ( empty( $meta_query ) ) {
			return;
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Get optimization status for an attachment.
	 *
	 * @since 4.2.0
	 * @param int $attachment_id Attachment ID.
	 * @return string Status key.
	 */
	public function get_status( $attachment_id ) {
		$video_deferred = (bool) get_post_meta(
			$attachment_id,
			ConversionOrchestrator::META_VIDEO_DEFERRED,
			true
		);

		return self::derive_status(
			AttachmentMetaHandler::is_conversion_disabled( $attachment_id ),
			AttachmentMetaHandler::get_external_job_state( $attachment_id ),
			AttachmentMetaHandler::get_converted_formats( $attachment_id ),
			AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id ),
			$video_deferred
		);
	}

	/**
	 * Derive optimization status from attachment meta values.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Accepts local video-deferred flag as Pending.
	 * @param bool        $conversion_disabled Whether conversion is disabled.
	 * @param string|null $job_state External job state.
	 * @param array       $converted_formats Converted format list.
	 * @param array       $converted_files_by_size Converted files grouped by size.
	 * @param bool        $video_deferred Whether local video work is deferred to cron.
	 * @return string Status key.
	 */
	public static function derive_status( $conversion_disabled, $job_state, array $converted_formats, array $converted_files_by_size, $video_deferred = false ) {
		if ( $conversion_disabled ) {
			return self::STATUS_DISABLED;
		}

		if ( AttachmentMetaHandler::is_in_flight_job_state( $job_state ) || $video_deferred ) {
			return self::STATUS_PENDING;
		}

		if ( 'failed' === $job_state ) {
			return self::STATUS_FAILED;
		}

		if ( ! empty( $converted_formats ) || ! empty( $converted_files_by_size ) ) {
			return self::STATUS_OPTIMIZED;
		}

		return self::STATUS_UNPROCESSED;
	}

	/**
	 * Get human-readable status labels.
	 *
	 * @since 4.2.0
	 * @return array<string, string> Status key to label map.
	 */
	public static function get_status_options() {
		return [
			self::STATUS_OPTIMIZED => \__( 'Optimized', 'flux-media-optimizer' ),
			self::STATUS_PENDING => \__( 'Pending', 'flux-media-optimizer' ),
			self::STATUS_FAILED => \__( 'Failed', 'flux-media-optimizer' ),
			self::STATUS_DISABLED => \__( 'Disabled', 'flux-media-optimizer' ),
			self::STATUS_UNPROCESSED => \__( 'Unprocessed', 'flux-media-optimizer' ),
		];
	}

	/**
	 * Normalize and validate a status key.
	 *
	 * @since 4.2.0
	 * @param string $status Status key.
	 * @return string Normalized status key or empty string.
	 */
	public static function normalize_status_key( $status ) {
		$status = \sanitize_key( $status );
		return self::is_valid_status( $status ) ? $status : '';
	}

	/**
	 * Check whether a status key is valid.
	 *
	 * @since 4.2.0
	 * @param string $status Status key.
	 * @return bool True if valid.
	 */
	public static function is_valid_status( $status ) {
		return array_key_exists( $status, self::get_status_options() );
	}

	/**
	 * Build meta query args for a status filter.
	 *
	 * @since 4.2.0
	 * @param string $status Status key.
	 * @return array Meta query arguments.
	 */
	public static function build_filter_meta_query( $status ) {
		switch ( $status ) {
			case self::STATUS_DISABLED:
				return [
					[
						'key' => AttachmentMetaHandler::META_KEY_CONVERSION_DISABLED,
						'value' => '1',
						'compare' => '=',
					],
				];

			case self::STATUS_PENDING:
				return [
					[
						'key' => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
						'value' => AttachmentMetaHandler::get_in_flight_job_states(),
						'compare' => 'IN',
					],
				];

			case self::STATUS_FAILED:
				return [
					[
						'key' => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
						'value' => 'failed',
						'compare' => '=',
					],
				];

			case self::STATUS_OPTIMIZED:
				return [
					'relation' => 'OR',
					[
						'key' => AttachmentMetaHandler::META_KEY_CONVERTED_FORMATS,
						'compare' => 'EXISTS',
					],
					[
						'key' => AttachmentMetaHandler::META_KEY_CONVERTED_FILES_BY_SIZE,
						'compare' => 'EXISTS',
					],
				];

			case self::STATUS_UNPROCESSED:
				return [
					'relation' => 'AND',
					[
						'relation' => 'OR',
						[
							'key' => AttachmentMetaHandler::META_KEY_CONVERSION_DISABLED,
							'compare' => 'NOT EXISTS',
						],
						[
							'key' => AttachmentMetaHandler::META_KEY_CONVERSION_DISABLED,
							'value' => '',
							'compare' => '=',
						],
					],
					[
						'relation' => 'OR',
						[
							'key' => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
							'compare' => 'NOT EXISTS',
						],
						[
							'key' => AttachmentMetaHandler::META_KEY_EXTERNAL_JOB_STATE,
							'value' => array_merge( AttachmentMetaHandler::get_in_flight_job_states(), [ 'failed' ] ),
							'compare' => 'NOT IN',
						],
					],
					[
						'key' => AttachmentMetaHandler::META_KEY_CONVERTED_FORMATS,
						'compare' => 'NOT EXISTS',
					],
					[
						'key' => AttachmentMetaHandler::META_KEY_CONVERTED_FILES_BY_SIZE,
						'compare' => 'NOT EXISTS',
					],
				];
		}

		return [];
	}

	/**
	 * Build status badge HTML for an attachment.
	 *
	 * @since 4.2.0
	 * @since 4.3.0 Failed badges include conversion error as title tooltip.
	 * @param int $attachment_id Attachment ID.
	 * @return string Escapable HTML string.
	 */
	private function get_status_badge_html( $attachment_id ) {
		$status = $this->get_status( $attachment_id );
		$labels = self::get_status_options();
		$label = $labels[ $status ] ?? $status;
		$secondary = $this->get_secondary_status_text( $attachment_id, $status );
		$error = self::STATUS_FAILED === $status
			? AttachmentMetaHandler::get_conversion_error( $attachment_id )
			: '';

		$title_attr = '';
		if ( '' !== $error ) {
			$title_attr = ' title="' . esc_attr( $error ) . '"';
		}

		$html = '<span class="flux-media-optimizer-status flux-media-optimizer-status--' . esc_attr( $status ) . '"' . $title_attr . '>';
		$html .= '<strong>' . esc_html( $label ) . '</strong>';

		if ( '' !== $secondary ) {
			$html .= '<br><span class="description">' . esc_html( $secondary ) . '</span>';
		}

		$html .= '</span>';

		return $html;
	}

	/**
	 * Get secondary status detail text.
	 *
	 * @since 4.2.0
	 * @param int    $attachment_id Attachment ID.
	 * @param string $status Status key.
	 * @return string Secondary text.
	 */
	private function get_secondary_status_text( $attachment_id, $status ) {
		if ( self::STATUS_OPTIMIZED === $status ) {
			$formats = AttachmentMetaHandler::get_converted_formats( $attachment_id );
			if ( empty( $formats ) ) {
				return '';
			}

			$display_formats = array_map(
				static function ( $format ) {
					return strtoupper( sanitize_text_field( (string) $format ) );
				},
				$formats
			);

			return implode( ', ', $display_formats );
		}

		if ( self::STATUS_FAILED !== $status ) {
			return '';
		}

		$retry_count = AttachmentMetaHandler::get_retry_count( $attachment_id );
		$retry_limit = ConversionRetryService::get_failed_job_retry_limit();

		return sprintf(
			/* translators: 1: current retry count, 2: maximum retry attempts */
			\__( 'Retry %1$d/%2$d', 'flux-media-optimizer' ),
			$retry_count,
			$retry_limit
		);
	}
}
