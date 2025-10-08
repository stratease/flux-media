<?php
/**
 * Quota management service for freemium model.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Services;

use FluxMedia\Core\Database;
use FluxMedia\Utils\Logger;

/**
 * Service for managing conversion quotas in freemium model.
 *
 * @since 1.0.0
 */
class QuotaManager {

	/**
	 * Free tier limits.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	const FREE_LIMITS = [
		'images_per_month' => 100,
		'videos_per_month' => 20,
	];

	/**
	 * Database instance.
	 *
	 * @since 1.0.0
	 * @var Database
	 */
	private $database;

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
		$this->database = new Database();
	}

	/**
	 * Initialize quota tracking for new installations.
	 *
	 * @since 1.0.0
	 */
	public function initialize_quota_tracking() {
		$activation_date = get_option( 'flux_media_activation_date' );
		
		if ( ! $activation_date ) {
			// Set activation date to current time.
			update_option( 'flux_media_activation_date', current_time( 'mysql' ) );
			
			// Initialize usage tracking.
			$this->reset_monthly_usage();
		}
	}

	/**
	 * Check if conversion is allowed within quota.
	 *
	 * @since 1.0.0
	 * @param string $type Conversion type ('image' or 'video').
	 * @return bool True if conversion is allowed, false otherwise.
	 */
	public function can_convert( $type ) {
		// TODO: Implement secure remote quota validation to prevent local override.
		// For now, using local tracking which can be bypassed by modifying code.
		
		$usage = $this->get_current_usage();
		$limits = $this->get_limits();
		
		if ( 'image' === $type ) {
			return $usage['images_used'] < $limits['images_per_month'];
		} elseif ( 'video' === $type ) {
			return $usage['videos_used'] < $limits['videos_per_month'];
		}
		
		return false;
	}

	/**
	 * Record a conversion usage.
	 *
	 * @since 1.0.0
	 * @param string $type Conversion type ('image' or 'video').
	 * @return bool True on success, false on failure.
	 */
	public function record_usage( $type ) {
		// Check if we need to reset monthly usage.
		$this->check_and_reset_monthly_usage();
		
		$usage = $this->get_current_usage();
		
		if ( 'image' === $type ) {
			$usage['images_used']++;
		} elseif ( 'video' === $type ) {
			$usage['videos_used']++;
		} else {
			return false;
		}
		
		return update_option( 'flux_media_monthly_usage', $usage );
	}

	/**
	 * Get current usage statistics.
	 *
	 * @since 1.0.0
	 * @return array Current usage data.
	 */
	public function get_current_usage() {
		$usage = get_option( 'flux_media_monthly_usage', [
			'images_used' => 0,
			'videos_used' => 0,
			'last_reset' => current_time( 'mysql' ),
		] );
		
		// Ensure all keys exist.
		$usage = array_merge( [
			'images_used' => 0,
			'videos_used' => 0,
			'last_reset' => current_time( 'mysql' ),
		], $usage );
		
		return $usage;
	}

	/**
	 * Get quota limits for current plan.
	 *
	 * @since 1.0.0
	 * @return array Quota limits.
	 */
	public function get_limits() {
		// TODO: Implement plan detection (free vs premium).
		// For now, always return free limits.
		return self::FREE_LIMITS;
	}

	/**
	 * Get quota progress information.
	 *
	 * @since 1.0.0
	 * @return array Quota progress data.
	 */
	public function get_quota_progress() {
		$usage = $this->get_current_usage();
		$limits = $this->get_limits();
		
		$image_progress = $limits['images_per_month'] > 0 
			? ( $usage['images_used'] / $limits['images_per_month'] ) * 100 
			: 0;
			
		$video_progress = $limits['videos_per_month'] > 0 
			? ( $usage['videos_used'] / $limits['videos_per_month'] ) * 100 
			: 0;
		
		return [
			'images' => [
				'used' => $usage['images_used'],
				'limit' => $limits['images_per_month'],
				'remaining' => max( 0, $limits['images_per_month'] - $usage['images_used'] ),
				'progress' => min( 100, $image_progress ),
			],
			'videos' => [
				'used' => $usage['videos_used'],
				'limit' => $limits['videos_per_month'],
				'remaining' => max( 0, $limits['videos_per_month'] - $usage['videos_used'] ),
				'progress' => min( 100, $video_progress ),
			],
			'plan' => 'free', // TODO: Implement plan detection.
			'next_reset' => $this->get_next_reset_date(),
		];
	}

	/**
	 * Check if monthly usage needs to be reset.
	 *
	 * @since 1.0.0
	 */
	private function check_and_reset_monthly_usage() {
		$usage = $this->get_current_usage();
		$last_reset = strtotime( $usage['last_reset'] );
		$current_time = current_time( 'timestamp' );
		
		// Reset if more than a month has passed.
		if ( $current_time - $last_reset > ( 30 * 24 * 60 * 60 ) ) {
			$this->reset_monthly_usage();
		}
	}

	/**
	 * Reset monthly usage counter.
	 *
	 * @since 1.0.0
	 */
	private function reset_monthly_usage() {
		$usage = [
			'images_used' => 0,
			'videos_used' => 0,
			'last_reset' => current_time( 'mysql' ),
		];
		
		update_option( 'flux_media_monthly_usage', $usage );
		
		// TODO: Queue existing media for next month's processing.
		$this->queue_existing_media();
	}

	/**
	 * Queue existing media for processing in next month.
	 *
	 * @since 1.0.0
	 */
	private function queue_existing_media() {
		// TODO: Implement queuing of existing media for next month.
		// This should scan the media library and queue images/videos
		// that haven't been converted yet for processing in the next month.
		
		// For now, just log that this should be implemented.
		error_log( 'Flux Media: TODO - Queue existing media for next month processing' );
	}

	/**
	 * Get next quota reset date.
	 *
	 * @since 1.0.0
	 * @return string Next reset date in MySQL format.
	 */
	private function get_next_reset_date() {
		$usage = $this->get_current_usage();
		$last_reset = strtotime( $usage['last_reset'] );
		
		// Add 30 days to last reset date.
		$next_reset = $last_reset + ( 30 * 24 * 60 * 60 );
		
		return date( 'Y-m-d H:i:s', $next_reset );
	}

	/**
	 * Get days until next quota reset.
	 *
	 * @since 1.0.0
	 * @return int Days until reset.
	 */
	public function get_days_until_reset() {
		$next_reset = strtotime( $this->get_next_reset_date() );
		$current_time = current_time( 'timestamp' );
		
		$diff = $next_reset - $current_time;
		return max( 0, ceil( $diff / ( 24 * 60 * 60 ) ) );
	}

	/**
	 * Check if user is on free plan.
	 *
	 * @since 1.0.0
	 * @return bool True if on free plan, false otherwise.
	 */
	public function is_free_plan() {
		// TODO: Implement proper plan detection.
		// For now, always return true (free plan).
		return true;
	}

	/**
	 * Get upgrade URL for premium plans.
	 *
	 * @since 1.0.0
	 * @return string Upgrade URL.
	 */
	public function get_upgrade_url() {
		// TODO: Implement proper upgrade URL.
		return 'https://fluxmedia.com/upgrade';
	}

	/**
	 * Get plan information.
	 *
	 * @since 1.0.0
	 * @return array Plan information.
	 */
	public function get_plan_info() {
		$is_free = $this->is_free_plan();
		
		return [
			'name' => $is_free ? 'Free' : 'Premium',
			'is_free' => $is_free,
			'limits' => $this->get_limits(),
			'upgrade_url' => $this->get_upgrade_url(),
			'features' => $is_free ? [
				'100 images per month',
				'20 videos per month',
				'WebP and AVIF conversion',
				'AV1 and WebM conversion',
				'Basic support',
			] : [
				'Unlimited images',
				'Unlimited videos',
				'All conversion formats',
				'Priority processing',
				'CDN integration',
				'External conversion services',
				'Premium support',
			],
		];
	}
}
