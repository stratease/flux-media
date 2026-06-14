<?php
/**
 * Webhook authentication and validation for external service callbacks.
 *
 * @package FluxMedia
 * @since 4.1.6
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Account\AccountIdService;
use FluxMedia\FluxPlugins\Common\Logger\Logger;
use WP_REST_Request;

/**
 * Static webhook security checks (account ID, attachment, job state, CDN allowlist, rate limit).
 *
 * @since 4.1.6
 */
class WebhookAuthService {

	/**
	 * Prevent instantiation; use static methods only.
	 *
	 * @since 4.1.6
	 */
	private function __construct() {
	}

	/**
	 * In-flight external job states that accept webhook updates.
	 *
	 * @since 4.1.6
	 * @var string[]
	 */
	private const IN_FLIGHT_JOB_STATES = [ 'queued', 'processing' ];

	/**
	 * Verify a webhook REST request (permission_callback).
	 *
	 * @since 4.1.6
	 * @param WP_REST_Request $request Request object.
	 * @return bool True if the request passes all checks.
	 */
	public static function verify_request( WP_REST_Request $request ) {
		$logger = Logger::get_instance();

		// Validate account_id from request (timing-safe compare).
		$request_account_id = sanitize_text_field( (string) $request->get_param( 'account_id' ) );
		$stored_account_id = AccountIdService::get_instance()->get_account_id();

		if ( ! self::validate_account_id( $request_account_id, $stored_account_id ) ) {
			if ( empty( $request_account_id ) ) {
				$logger->warning( 'Webhook rejected: missing account_id' );
			} elseif ( empty( $stored_account_id ) ) {
				$logger->error( 'Webhook rejected: account ID not configured on site' );
			} else {
				$logger->warning(
					'Webhook rejected: account_id mismatch. Request: '
					. AccountIdService::obfuscate( $request_account_id )
					. ', Stored: '
					. AccountIdService::obfuscate( $stored_account_id )
				);
			}
			return false;
		}

		// Per-account rate limit (transient counter).
		if ( ! self::check_rate_limit( $stored_account_id ) ) {
			$logger->warning( 'Webhook rejected: rate limit exceeded for account ' . AccountIdService::obfuscate( $stored_account_id ) );
			return false;
		}

		// Attachment must exist and be post type attachment.
		$attachment_id_param = $request->get_param( 'attachment_id' );
		$attachment_id = ! empty( $attachment_id_param ) ? (int) $attachment_id_param : 0;

		if ( ! self::validate_attachment( $attachment_id ) ) {
			$logger->warning( "Webhook rejected: invalid attachment_id {$attachment_id}" );
			return false;
		}

		// Job must be in-flight (queued/processing) before completed or failed webhook.
		$cdn_urls = $request->get_param( 'cdn_urls' );
		$incoming_status = self::resolve_incoming_status( $cdn_urls );

		$current_state = AttachmentMetaHandler::get_external_job_state( $attachment_id );
		if ( ! self::validate_job_state_transition( $current_state, $incoming_status ) ) {
			$logger->warning(
				"Webhook rejected: invalid job transition for attachment {$attachment_id} (current: "
				. ( $current_state ?? 'none' )
				. ", incoming: {$incoming_status})"
			);
			return false;
		}

		// Completed payloads must include at least one allowlisted CDN URL.
		if ( $incoming_status === 'completed' ) {
			$cdn_validation = self::validate_cdn_urls( is_array( $cdn_urls ) ? $cdn_urls : [] );
			if ( $cdn_validation !== true ) {
				$logger->warning(
					"Webhook rejected: CDN URL validation failed for attachment {$attachment_id}: {$cdn_validation}"
				);
				return false;
			}
		}

		return true;
	}

	/**
	 * Compare request account ID to stored value using timing-safe comparison.
	 *
	 * @since 4.1.6
	 * @param string $request_account_id Account ID from the webhook payload.
	 * @param string $stored_account_id Account ID stored for this site.
	 * @return bool True when both are non-empty and match.
	 */
	public static function validate_account_id( $request_account_id, $stored_account_id ) {
		$request_account_id = is_string( $request_account_id ) ? trim( $request_account_id ) : '';
		$stored_account_id = is_string( $stored_account_id ) ? trim( $stored_account_id ) : '';

		if ( $request_account_id === '' || $stored_account_id === '' ) {
			return false;
		}

		return hash_equals( $stored_account_id, $request_account_id );
	}

	/**
	 * Validate attachment exists and is an attachment post type.
	 *
	 * @since 4.1.6
	 * @param int $attachment_id Attachment post ID.
	 * @return bool True if valid attachment.
	 */
	public static function validate_attachment( $attachment_id ) {
		if ( $attachment_id <= 0 ) {
			return false;
		}

		if ( ! function_exists( 'get_post_type' ) ) {
			return false;
		}

		return get_post_type( $attachment_id ) === 'attachment';
	}

	/**
	 * Resolve webhook status from payload.
	 *
	 * @since 4.1.6
	 * @param mixed $cdn_urls CDN URLs payload.
	 * @return string 'completed' or 'failed'.
	 */
	public static function resolve_incoming_status( $cdn_urls ) {
		if ( ! empty( $cdn_urls ) && is_array( $cdn_urls ) ) {
			return 'completed';
		}

		return 'failed';
	}

	/**
	 * Validate job state transition for webhook processing.
	 *
	 * @since 4.1.6
	 * @param string|null $current_state Current external job state.
	 * @param string      $incoming_status Incoming status ('completed' or 'failed').
	 * @return bool True if transition is allowed.
	 */
	public static function validate_job_state_transition( $current_state, $incoming_status ) {
		if ( ! in_array( $incoming_status, [ 'completed', 'failed' ], true ) ) {
			return false;
		}

		return in_array( $current_state, self::IN_FLIGHT_JOB_STATES, true );
	}

	/**
	 * Validate all CDN URLs in the webhook payload against the host allowlist.
	 *
	 * @since 4.1.6
	 * @param array $cdn_urls CDN URLs grouped by size.
	 * @return true|string True on success, error message string on failure.
	 */
	public static function validate_cdn_urls( array $cdn_urls ) {
		$allowed_hosts = self::get_allowed_cdn_hosts();
		$found_url = false;

		foreach ( $cdn_urls as $size_name => $format_data_array ) {
			if ( ! is_array( $format_data_array ) ) {
				continue;
			}

			foreach ( $format_data_array as $format => $data ) {
				if ( ! is_array( $data ) || empty( $data['url'] ) || ! is_string( $data['url'] ) ) {
					continue;
				}

				$url = esc_url_raw( $data['url'] );
				if ( empty( $url ) ) {
					return 'Invalid or empty CDN URL';
				}

				$host_check = self::validate_url_host( $url, $allowed_hosts );
				if ( $host_check !== true ) {
					return $host_check;
				}

				$found_url = true;
			}
		}

		if ( ! $found_url ) {
			return 'No valid CDN URLs in payload';
		}

		return true;
	}

	/**
	 * Validate a single URL host against the allowlist.
	 *
	 * @since 4.1.6
	 * @param string   $url           Sanitized URL.
	 * @param string[] $allowed_hosts Lowercase hostnames (no port).
	 * @return true|string True on success, error message on failure.
	 */
	public static function validate_url_host( $url, array $allowed_hosts ) {
		$host = self::extract_url_host( $url );
		if ( $host === '' ) {
			return 'Could not parse CDN URL host';
		}

		if ( ! self::is_host_allowed( $host, $allowed_hosts ) ) {
			return 'CDN host not allowed: ' . $host;
		}

		return true;
	}

	/**
	 * Extract lowercase hostname from a URL.
	 *
	 * @since 4.1.6
	 * @param string $url URL string.
	 * @return string Hostname or empty string.
	 */
	public static function extract_url_host( $url ) {
		if ( function_exists( 'wp_parse_url' ) ) {
			$parsed = wp_parse_url( $url );
		} else {
			$parsed = parse_url( $url );
		}

		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return '';
		}

		return strtolower( $parsed['host'] );
	}

	/**
	 * Check if a host is in the allowlist.
	 *
	 * @since 4.1.6
	 * @param string   $host          Hostname to check (lowercase).
	 * @param string[] $allowed_hosts Allowed hostnames (lowercase).
	 * @return bool True if allowed.
	 */
	public static function is_host_allowed( $host, array $allowed_hosts ) {
		$host = strtolower( $host );
		return in_array( $host, $allowed_hosts, true );
	}

	/**
	 * Build the CDN host allowlist from constants.
	 *
	 * @since 4.1.6
	 * @return string[] Unique lowercase hostnames.
	 */
	public static function get_allowed_cdn_hosts() {
		$hosts = [];

		// Default Flux CDN hosts (see FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS in wp-config).
		if ( defined( 'FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS' ) && FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS !== '' ) {
			$hosts = array_merge( $hosts, self::parse_host_list( FLUX_MEDIA_OPTIMIZER_DEFAULT_CDN_HOSTS ) );
		}

		// API service host (same base URL as upload/init).
		if ( defined( 'FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL' ) ) {
			$api_host = self::extract_url_host( FLUX_MEDIA_OPTIMIZER_EXTERNAL_SERVICE_URL );
			if ( $api_host !== '' ) {
				$hosts[] = $api_host;
			}
		}

		// Optional extra hosts for staging or custom CDN (wp-config override).
		if ( defined( 'FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST' ) && FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST !== '' ) {
			$hosts = array_merge( $hosts, self::parse_host_list( FLUX_MEDIA_OPTIMIZER_CDN_HOST_ALLOWLIST ) );
		}

		$hosts = array_map( 'strtolower', array_filter( array_map( 'trim', $hosts ) ) );

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Parse a comma-separated host list.
	 *
	 * @since 4.1.6
	 * @param string $list Comma-separated hostnames.
	 * @return string[] Hostnames.
	 */
	public static function parse_host_list( $list ) {
		if ( ! is_string( $list ) || $list === '' ) {
			return [];
		}

		$parts = explode( ',', $list );
		$hosts = [];

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( $part !== '' ) {
				$hosts[] = $part;
			}
		}

		return $hosts;
	}

	/**
	 * Check webhook rate limit for an account (increments counter on success).
	 *
	 * @since 4.1.6
	 * @param string $account_id Site account ID.
	 * @return bool True if request is within limit.
	 */
	public static function check_rate_limit( $account_id ) {
		if ( ! function_exists( 'get_transient' ) || ! function_exists( 'set_transient' ) ) {
			return true;
		}

		$max_requests = defined( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT' )
			? (int) FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_LIMIT
			: 60;

		$window_seconds = defined( 'FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_WINDOW' )
			? (int) FLUX_MEDIA_OPTIMIZER_WEBHOOK_RATE_WINDOW
			: 60;

		if ( $max_requests <= 0 || $window_seconds <= 0 ) {
			return true;
		}

		$key = self::build_rate_limit_transient_key( $account_id );
		$count = (int) get_transient( $key );

		if ( $count >= $max_requests ) {
			return false;
		}

		set_transient( $key, $count + 1, $window_seconds );

		return true;
	}

	/**
	 * Build transient key for webhook rate limiting.
	 *
	 * @since 4.1.6
	 * @param string $account_id Account ID.
	 * @return string Transient key.
	 */
	public static function build_rate_limit_transient_key( $account_id ) {
		return 'flux_mo_webhook_rl_' . md5( $account_id );
	}

	/**
	 * Determine whether request count is within the configured limit (pure helper for tests).
	 *
	 * @since 4.1.6
	 * @param int $current_count Current request count in the window.
	 * @param int $max_requests  Maximum allowed requests.
	 * @return bool True if another request is allowed.
	 */
	public static function is_within_rate_limit( $current_count, $max_requests ) {
		return $current_count < $max_requests;
	}
}
