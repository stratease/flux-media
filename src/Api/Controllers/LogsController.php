<?php
/**
 * Logs REST API controller.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Logs controller.
 *
 * @since 1.0.0
 */
class LogsController extends BaseController {

	/**
	 * Register routes for logs endpoints.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		$namespace = $this->get_namespace();

		// Logs endpoint.
		register_rest_route( $namespace, '/logs', [
			'methods' => 'GET',
			'callback' => [ $this, 'get_logs' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
			'args' => [
				'page' => [
					'description' => 'Current page of the collection.',
					'type' => 'integer',
					'default' => 1,
					'minimum' => 1,
				],
				'per_page' => [
					'description' => 'Maximum number of items to be returned in result set.',
					'type' => 'integer',
					'default' => 20,
					'minimum' => 1,
					'maximum' => 100,
				],
				'level' => [
					'description' => 'Filter logs by level.',
					'type' => 'string',
					'enum' => [ 'DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL' ],
				],
				'search' => [
					'description' => 'Search logs by message or context.',
					'type' => 'string',
				],
			],
		] );
	}

	/**
	 * Get logs with pagination.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error Response object.
	 */
	public function get_logs( WP_REST_Request $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'flux_media_logs';
		
		// Get parameters
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$level = $request->get_param( 'level' );
		$search = $request->get_param( 'search' );

		// Calculate offset
		$offset = ( $page - 1 ) * $per_page;

		// Build WHERE clause
		$where_conditions = [];
		$where_values = [];

		if ( $level ) {
			$where_conditions[] = 'level = %s';
			$where_values[] = $level;
		}

		if ( $search ) {
			$where_conditions[] = '(message LIKE %s OR context LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $search ) . '%';
			$where_values[] = $search_term;
			$where_values[] = $search_term;
		}

		$where_clause = '';
		if ( ! empty( $where_conditions ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
		}

		// Get total count
		$count_sql = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
		if ( ! empty( $where_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $where_values );
		}
		$total_logs = (int) $wpdb->get_var( $count_sql );

		// Get logs with pagination
		$logs_sql = "SELECT id, level, message, context, created_at 
			FROM {$table_name} 
			{$where_clause} 
			ORDER BY created_at DESC 
			LIMIT %d OFFSET %d";

		$query_values = array_merge( $where_values, [ $per_page, $offset ] );
		$logs_sql = $wpdb->prepare( $logs_sql, $query_values );
		$logs = $wpdb->get_results( $logs_sql, ARRAY_A );

		// Process logs
		$processed_logs = [];
		foreach ( $logs as $log ) {
			$processed_logs[] = [
				'id' => (int) $log['id'],
				'level' => $log['level'],
				'message' => $log['message'],
				'context' => $log['context'] ? json_decode( $log['context'], true ) : null,
				'created_at' => $log['created_at'],
			];
		}

		// Calculate pagination info
		$total_pages = ceil( $total_logs / $per_page );

		return $this->create_response( [
			'logs' => $processed_logs,
			'pagination' => [
				'page' => $page,
				'per_page' => $per_page,
				'total' => $total_logs,
				'total_pages' => $total_pages,
				'has_next' => $page < $total_pages,
				'has_prev' => $page > 1,
			],
		], 'Logs retrieved successfully' );
	}
}
