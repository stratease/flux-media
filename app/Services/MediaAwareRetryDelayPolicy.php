<?php
/**
 * Default image/video retry delay sequences.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Progressive delays: images 1/5/15 minutes; videos 5/30/120 minutes.
 *
 * @since 4.3.0
 */
final class MediaAwareRetryDelayPolicy implements RetryDelayPolicy {

	/**
	 * Image delay in minutes keyed by attempt number.
	 *
	 * @since 4.3.0
	 * @var array<int, int>
	 */
	private const IMAGE_DELAY_MINUTES = [
		1 => 1,
		2 => 5,
		3 => 15,
	];

	/**
	 * Video delay in minutes keyed by attempt number.
	 *
	 * @since 4.3.0
	 * @var array<int, int>
	 */
	private const VIDEO_DELAY_MINUTES = [
		1 => 5,
		2 => 30,
		3 => 120,
	];

	/**
	 * Video converter used to detect video attachments.
	 *
	 * @since 4.3.0
	 * @var VideoConverter|null
	 */
	private $video_converter;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param VideoConverter|null $video_converter Optional video detector; null treats as image delays.
	 */
	public function __construct( ?VideoConverter $video_converter = null ) {
		$this->video_converter = $video_converter;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @since 4.3.0
	 */
	public function get_delay_seconds( int $attachment_id, int $attempt ): int {
		$minutes = $this->is_video_attachment( $attachment_id )
			? ( self::VIDEO_DELAY_MINUTES[ $attempt ] ?? null )
			: ( self::IMAGE_DELAY_MINUTES[ $attempt ] ?? null );

		if ( null === $minutes ) {
			return 0;
		}

		$minute = defined( 'MINUTE_IN_SECONDS' ) ? (int) MINUTE_IN_SECONDS : 60;

		return $minutes * $minute;
	}

	/**
	 * Whether the attachment should use the video delay sequence.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_video_attachment( int $attachment_id ): bool {
		if ( null === $this->video_converter ) {
			$mime = (string) get_post_mime_type( $attachment_id );
			return 0 === strpos( $mime, 'video/' );
		}

		$file_path = get_attached_file( $attachment_id );
		if ( empty( $file_path ) ) {
			$mime = (string) get_post_mime_type( $attachment_id );
			return 0 === strpos( $mime, 'video/' );
		}

		return $this->video_converter->is_supported_video( $file_path );
	}
}
