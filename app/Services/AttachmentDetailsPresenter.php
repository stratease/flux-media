<?php
/**
 * Attachment details presenter for Media Library panel data.
 *
 * @package FluxMedia\App\Services
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\License\LicenseService;

/**
 * Builds the attachment optimization panel payload (SSOT for React island).
 *
 * @since 4.3.0
 */
class AttachmentDetailsPresenter {

	/**
	 * CDN purchase URL for the license upsell.
	 *
	 * @since 4.3.0
	 * @var string
	 */
	const CDN_BUY_URL = 'https://fluxplugins.com/buy';

	/**
	 * Shared brand icon size in pixels for attachment and settings headers.
	 *
	 * @since 4.3.0
	 * @var int
	 */
	const BRAND_ICON_SIZE = 28;

	/**
	 * WordPress core image size names shown with a Core badge.
	 *
	 * @since 4.3.0
	 * @var string[]
	 */
	private const CORE_SIZE_NAMES = [
		'thumbnail',
		'medium',
		'medium_large',
		'large',
		'full',
		'1536x1536',
		'2048x2048',
	];

	/**
	 * Format support detector.
	 *
	 * @since 4.3.0
	 * @var FormatSupportDetector
	 */
	private $format_detector;

	/**
	 * Constructor.
	 *
	 * @since 4.3.0
	 * @param FormatSupportDetector $format_detector Format support detector.
	 */
	public function __construct( FormatSupportDetector $format_detector ) {
		$this->format_detector = $format_detector;
	}

	/**
	 * Present attachment details for the Flux Media Optimizer panel.
	 *
	 * @since 4.3.0
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function present( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$mime_type     = get_post_mime_type( $attachment_id );
		$media_type    = $this->resolve_media_type( $mime_type );
		$disabled      = AttachmentMetaHandler::is_conversion_disabled( $attachment_id );
		$job_state     = AttachmentMetaHandler::get_external_job_state( $attachment_id );
		$video_deferred = (bool) get_post_meta( $attachment_id, ConversionOrchestrator::META_VIDEO_DEFERRED, true );
		$converted     = AttachmentMetaHandler::get_converted_files_grouped_by_size( $attachment_id );
		$status        = MediaLibraryStatusService::derive_status(
			$disabled,
			$job_state,
			AttachmentMetaHandler::get_converted_formats( $attachment_id ),
			$converted,
			$video_deferred
		);

		$has_conversions = ! empty( $converted );
		$error           = AttachmentMetaHandler::get_conversion_error( $attachment_id );
		$retry_count     = AttachmentMetaHandler::get_retry_count( $attachment_id );
		$retry_limit     = ConversionRetryService::get_failed_job_retry_limit();
		$license_valid   = LicenseService::get_instance()->is_license_valid( false );
		$in_flight       = AttachmentMetaHandler::is_in_flight_job_state( $job_state )
			|| $video_deferred
			|| MediaLibraryStatusService::STATUS_PENDING === $status;

		$effective_formats = $this->resolve_effective_formats( $media_type );
		$format_columns    = $this->build_format_columns( $effective_formats );
		$sizes             = $this->build_size_rows( $attachment_id, $converted, $media_type, $mime_type, $effective_formats );

		return [
			'attachmentId'       => $attachment_id,
			'mediaType'          => $media_type,
			'title'              => \__( 'Flux Media Optimizer', 'flux-media-optimizer' ),
			'brandIconSize'      => self::BRAND_ICON_SIZE,
			'status'             => $status,
			'statusLabel'        => $this->status_label( $status ),
			'retryText'          => MediaLibraryStatusService::STATUS_FAILED === $status
				? sprintf(
					/* translators: 1: current retry count, 2: maximum retry attempts */
					\__( 'Retry %1$d/%2$d', 'flux-media-optimizer' ),
					$retry_count,
					$retry_limit
				)
				: '',
			'error'              => $error,
			'showUpsell'         => ! $license_valid,
			'upsellUrl'          => self::CDN_BUY_URL,
			'upsellMessage'      => \__( 'Want to save space and improve page speeds?', 'flux-media-optimizer' ),
			'upsellLinkLabel'    => \__( 'Upgrade to our CDN', 'flux-media-optimizer' ),
			'conversionDisabled' => $disabled,
			'hasConversions'     => $has_conversions,
			'processing'         => $in_flight,
			'sizes'              => $sizes,
			'effectiveFormats'   => $effective_formats,
			'formatColumns'      => $format_columns,
			'actions'            => [
				// Disable Convert/Re-convert while submitted/deferred work is in flight.
				'canConvert'   => ! $disabled && ! $in_flight,
				'convertLabel' => $in_flight
					? \__( 'Processing…', 'flux-media-optimizer' )
					: ( $has_conversions
						? \__( 'Re-convert', 'flux-media-optimizer' )
						: \__( 'Convert', 'flux-media-optimizer' ) ),
				'canDisable'   => ! $disabled && ! $in_flight,
				'canEnable'    => $disabled,
				'disableLabel' => \__( 'Disable conversion', 'flux-media-optimizer' ),
				'enableLabel'  => \__( 'Enable conversion', 'flux-media-optimizer' ),
			],
			'columns'            => [
				'mediaSize' => 'video' === $media_type
					? \__( 'Media size', 'flux-media-optimizer' )
					: \__( 'Image size', 'flux-media-optimizer' ),
				'original'  => \__( 'Original', 'flux-media-optimizer' ),
				'savings'   => \__( 'Savings', 'flux-media-optimizer' ),
			],
			'labels'             => [
				'pendingRefresh' => \__( 'Checking for updates…', 'flux-media-optimizer' ),
				'loadError'      => \__( 'Unable to load optimization details.', 'flux-media-optimizer' ),
				'retry'          => \__( 'Retry', 'flux-media-optimizer' ),
				'empty'          => \__( 'No conversions yet', 'flux-media-optimizer' ),
				'url'            => \__( 'URL', 'flux-media-optimizer' ),
				'copied'         => \__( 'Copied', 'flux-media-optimizer' ),
				'openUrl'        => \__( 'Open in new tab', 'flux-media-optimizer' ),
				'copyUrl'        => \__( 'Copy to clipboard', 'flux-media-optimizer' ),
				'expand'         => \__( 'Expand', 'flux-media-optimizer' ),
				'collapse'       => \__( 'Collapse', 'flux-media-optimizer' ),
				'coreBadge'      => \__( 'Core', 'flux-media-optimizer' ),
			],
		];
	}

	/**
	 * Resolve media type from mime type.
	 *
	 * @since 4.3.0
	 * @param string|null $mime_type Mime type.
	 * @return string image|video|other
	 */
	private function resolve_media_type( $mime_type ) {
		if ( ! is_string( $mime_type ) || '' === $mime_type ) {
			return 'other';
		}

		if ( 0 === strpos( $mime_type, 'image/' ) ) {
			return 'image';
		}

		if ( 0 === strpos( $mime_type, 'video/' ) ) {
			return 'video';
		}

		return 'other';
	}

	/**
	 * Resolve formats that settings enable and the active processor can produce.
	 *
	 * Cloud processing uses enabled settings only; local uses settings ∩ capability.
	 *
	 * @since 4.3.0
	 * @param string $media_type Media type.
	 * @return string[]
	 */
	public function resolve_effective_formats( $media_type ) {
		$cloud = Settings::is_external_processing_active();

		if ( 'video' === $media_type ) {
			$enabled = Settings::get_video_formats();
			$enabled = is_array( $enabled ) ? $enabled : [];
			$allowed = [ Converter::FORMAT_AV1, Converter::FORMAT_WEBM ];
			$enabled = array_values( array_intersect( $enabled, $allowed ) );

			if ( $cloud ) {
				return $enabled;
			}

			$effective = [];
			foreach ( $enabled as $format ) {
				if ( Converter::FORMAT_AV1 === $format && $this->format_detector->supports_av1() ) {
					$effective[] = $format;
				}
				if ( Converter::FORMAT_WEBM === $format && $this->format_detector->supports_webm() ) {
					$effective[] = $format;
				}
			}

			return $effective;
		}

		if ( 'image' !== $media_type ) {
			return [];
		}

		$enabled = Settings::get_image_formats();
		$enabled = is_array( $enabled ) ? $enabled : [];
		$allowed = [ Converter::FORMAT_WEBP, Converter::FORMAT_AVIF ];
		$enabled = array_values( array_intersect( $enabled, $allowed ) );

		if ( $cloud ) {
			return $enabled;
		}

		$effective = [];
		foreach ( $enabled as $format ) {
			if ( Converter::FORMAT_WEBP === $format && $this->format_detector->supports_webp() ) {
				$effective[] = $format;
			}
			if ( Converter::FORMAT_AVIF === $format && $this->format_detector->supports_avif() ) {
				$effective[] = $format;
			}
		}

		return $effective;
	}

	/**
	 * Build localized format column descriptors for enabled formats.
	 *
	 * @since 4.3.0
	 * @param string[] $formats Effective formats.
	 * @return array<int, array{key: string, label: string, color: string}>
	 */
	private function build_format_columns( array $formats ) {
		$meta = [
			Converter::FORMAT_AVIF => [
				'label' => \__( 'AVIF', 'flux-media-optimizer' ),
				'color' => '#ea4335',
			],
			Converter::FORMAT_WEBP => [
				'label' => \__( 'WebP', 'flux-media-optimizer' ),
				'color' => '#4285f4',
			],
			Converter::FORMAT_AV1  => [
				'label' => \__( 'AV1', 'flux-media-optimizer' ),
				'color' => '#ea4335',
			],
			Converter::FORMAT_WEBM => [
				'label' => \__( 'WebM', 'flux-media-optimizer' ),
				'color' => '#4285f4',
			],
		];

		$columns = [];
		foreach ( $formats as $format ) {
			if ( ! isset( $meta[ $format ] ) ) {
				continue;
			}
			$columns[] = [
				'key'   => $format,
				'label' => $meta[ $format ]['label'],
				'color' => $meta[ $format ]['color'],
			];
		}

		return $columns;
	}

	/**
	 * Human-readable status label.
	 *
	 * @since 4.3.0
	 * @param string $status Status key.
	 * @return string
	 */
	private function status_label( $status ) {
		$options = MediaLibraryStatusService::get_status_options();
		return $options[ $status ] ?? $status;
	}

	/**
	 * Build ordered size rows for the accordion table.
	 *
	 * @since 4.3.0
	 * @param int         $attachment_id      Attachment ID.
	 * @param array       $converted          Converted files by size.
	 * @param string      $media_type         Media type.
	 * @param string|null $mime_type          Mime type.
	 * @param string[]    $effective_formats  Formats to show.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_size_rows( $attachment_id, array $converted, $media_type, $mime_type, array $effective_formats ) {
		if ( 'video' === $media_type ) {
			return $this->build_video_rows( $attachment_id, $converted, $mime_type, $effective_formats );
		}

		if ( 'image' !== $media_type || empty( $converted ) ) {
			return [];
		}

		$valid   = function_exists( 'get_intermediate_image_sizes' )
			? get_intermediate_image_sizes()
			: [];
		$valid[] = 'full';

		$metadata = function_exists( 'wp_get_attachment_metadata' )
			? wp_get_attachment_metadata( $attachment_id )
			: [];
		$metadata = is_array( $metadata ) ? $metadata : [];

		$rows = [];
		foreach ( $converted as $size_name => $formats ) {
			if ( ! is_array( $formats ) || ! in_array( $size_name, $valid, true ) ) {
				continue;
			}

			$width  = 0;
			$height = 0;
			if ( 'full' === $size_name ) {
				$width  = (int) ( $metadata['width'] ?? 0 );
				$height = (int) ( $metadata['height'] ?? 0 );
			} elseif ( isset( $metadata['sizes'][ $size_name ] ) ) {
				$width  = (int) ( $metadata['sizes'][ $size_name ]['width'] ?? 0 );
				$height = (int) ( $metadata['sizes'][ $size_name ]['height'] ?? 0 );
			}

			$original = $this->resolve_original_variant( $attachment_id, $formats, $size_name, $mime_type, $metadata );
			$variants = [];
			foreach ( $effective_formats as $format ) {
				$variants[ $format ] = $this->format_variant( $attachment_id, $formats, $format, $size_name );
			}

			$best_converted_bytes = $this->best_converted_bytes( $variants );
			$original_bytes       = $original['bytes'] ?? 0;
			$savings              = self::calculate_savings( $original_bytes, $best_converted_bytes );

			$rows[] = [
				'name'           => (string) $size_name,
				'label'          => 'full' === $size_name
					? \__( 'Full Size', 'flux-media-optimizer' )
					: ucfirst( str_replace( '_', ' ', (string) $size_name ) ),
				'width'          => $width,
				'height'         => $height,
				'source'         => in_array( $size_name, self::CORE_SIZE_NAMES, true ) ? 'core' : 'custom',
				'original'       => $original,
				'variants'       => $variants,
				'savingsPercent' => $savings['percent'],
				'savingsBytes'   => $savings['bytes'],
				'savingsLabel'   => $savings['label'],
				'savedLabel'     => $savings['saved_label'],
			];
		}

		return $rows;
	}

	/**
	 * Build a single full-size row for video attachments.
	 *
	 * @since 4.3.0
	 * @param int         $attachment_id     Attachment ID.
	 * @param array       $converted         Converted files by size.
	 * @param string|null $mime_type         Mime type.
	 * @param string[]    $effective_formats Formats to show.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_video_rows( $attachment_id, array $converted, $mime_type, array $effective_formats ) {
		$formats = [];
		if ( isset( $converted['full'] ) && is_array( $converted['full'] ) ) {
			$formats = $converted['full'];
		} elseif ( ! empty( $converted ) ) {
			$first = reset( $converted );
			$formats = is_array( $first ) ? $first : [];
		}

		// Show a row when conversions exist or formats are expected (pending empty state uses status).
		if ( empty( $formats ) && empty( $effective_formats ) ) {
			return [];
		}

		if ( empty( $formats ) ) {
			return [];
		}

		$original = $this->resolve_original_variant( $attachment_id, $formats, 'full', $mime_type, [] );
		$variants = [];
		foreach ( $effective_formats as $format ) {
			$variants[ $format ] = $this->format_variant( $attachment_id, $formats, $format, 'full' );
		}

		$best_converted_bytes = $this->best_converted_bytes( $variants );
		$original_bytes       = $original['bytes'] ?? 0;
		$savings              = self::calculate_savings( $original_bytes, $best_converted_bytes );

		return [
			[
				'name'           => 'full',
				'label'          => \__( 'Full Size', 'flux-media-optimizer' ),
				'width'          => 0,
				'height'         => 0,
				'source'         => 'core',
				'original'       => $original,
				'variants'       => $variants,
				'savingsPercent' => $savings['percent'],
				'savingsBytes'   => $savings['bytes'],
				'savingsLabel'   => $savings['label'],
				'savedLabel'     => $savings['saved_label'],
			],
		];
	}

	/**
	 * Resolve original variant from conversion meta or WordPress attachment files.
	 *
	 * @since 4.3.0
	 * @param int         $attachment_id Attachment ID.
	 * @param array       $formats       Formats for the size.
	 * @param string      $size_name     Size name.
	 * @param string|null $mime_type     Mime type.
	 * @param array       $metadata      Attachment metadata.
	 * @return array<string, mixed>|null
	 */
	private function resolve_original_variant( $attachment_id, array $formats, $size_name, $mime_type, array $metadata ) {
		$from_meta = $this->format_variant( $attachment_id, $formats, 'original', $size_name, $mime_type );
		if ( is_array( $from_meta ) && ( $from_meta['bytes'] ?? 0 ) > 0 ) {
			return $from_meta;
		}

		$url   = '';
		$bytes = 0;
		$path  = '';

		if ( 'full' === $size_name ) {
			$url  = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $attachment_id ) : '';
			$path = function_exists( 'get_attached_file' ) ? (string) get_attached_file( $attachment_id ) : '';
		} elseif ( ! empty( $metadata['sizes'][ $size_name ]['file'] ) && ! empty( $metadata['file'] ) ) {
			$upload_dir = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : null;
			$file_dir   = dirname( (string) $metadata['file'] );
			$size_file  = (string) $metadata['sizes'][ $size_name ]['file'];
			if ( is_array( $upload_dir ) && ! empty( $upload_dir['baseurl'] ) && ! empty( $upload_dir['basedir'] ) ) {
				$url  = trailingslashit( $upload_dir['baseurl'] ) . trailingslashit( $file_dir ) . $size_file;
				$path = trailingslashit( $upload_dir['basedir'] ) . trailingslashit( $file_dir ) . $size_file;
			}
		}

		if ( $path && file_exists( $path ) ) {
			$bytes = (int) filesize( $path );
		}

		if ( $bytes <= 0 && is_array( $from_meta ) ) {
			return $from_meta;
		}

		if ( $bytes <= 0 && '' === $url ) {
			return $from_meta;
		}

		$label = $this->original_format_label( $mime_type, $url );

		return [
			'format'    => 'original',
			'label'     => $label,
			'bytes'     => $bytes,
			'sizeLabel' => $bytes > 0 && function_exists( 'size_format' ) ? size_format( $bytes ) : (string) $bytes,
			'url'       => $url,
		];
	}

	/**
	 * Build one format variant cell.
	 *
	 * @since 4.3.0
	 * @param int         $attachment_id Attachment ID.
	 * @param array       $formats       Formats for the size.
	 * @param string      $format        Format key.
	 * @param string      $size_name     Size name.
	 * @param string|null $mime_type     Mime type for original label.
	 * @return array<string, mixed>|null
	 */
	private function format_variant( $attachment_id, array $formats, $format, $size_name, $mime_type = null ) {
		if ( empty( $formats[ $format ] ) || ! is_array( $formats[ $format ] ) ) {
			return null;
		}

		$url   = AttachmentMetaHandler::get_converted_file_url( $attachment_id, $format, $size_name );
		$bytes = AttachmentMetaHandler::get_file_size( $attachment_id, $format, $size_name );
		$bytes = null !== $bytes ? (int) $bytes : (int) ( $formats[ $format ]['filesize'] ?? 0 );

		$label = 'original' === $format
			? $this->original_format_label( $mime_type, $url )
			: strtoupper( $format );

		return [
			'format'    => $format,
			'label'     => $label,
			'bytes'     => $bytes,
			'sizeLabel' => $bytes > 0 && function_exists( 'size_format' ) ? size_format( $bytes ) : (string) $bytes,
			'url'       => is_string( $url ) ? $url : '',
		];
	}

	/**
	 * Prefer the smaller converted format for savings display.
	 *
	 * @since 4.3.0
	 * @param array<string, array|null> $variants Format variants.
	 * @return int
	 */
	private function best_converted_bytes( array $variants ) {
		$candidates = [];
		foreach ( $variants as $variant ) {
			if ( is_array( $variant ) && ( $variant['bytes'] ?? 0 ) > 0 ) {
				$candidates[] = (int) $variant['bytes'];
			}
		}

		return empty( $candidates ) ? 0 : min( $candidates );
	}

	/**
	 * Calculate savings labels against the original size.
	 *
	 * @since 4.3.0
	 * @param int $original_bytes Original bytes.
	 * @param int $converted_bytes Best converted bytes.
	 * @return array{percent: float|null, bytes: int, label: string, saved_label: string}
	 */
	public static function calculate_savings( $original_bytes, $converted_bytes ) {
		$original_bytes  = (int) $original_bytes;
		$converted_bytes = (int) $converted_bytes;

		if ( $original_bytes <= 0 || $converted_bytes <= 0 ) {
			return [
				'percent'     => null,
				'bytes'       => 0,
				'label'       => '',
				'saved_label' => '',
			];
		}

		$diff    = $original_bytes - $converted_bytes;
		$percent = round( ( $diff / $original_bytes ) * 100, 1 );

		if ( $percent > 0 ) {
			return [
				'percent'     => $percent,
				'bytes'       => $diff,
				'label'       => sprintf(
					/* translators: %s: percentage smaller */
					\__( '%s%% smaller', 'flux-media-optimizer' ),
					$percent
				),
				'saved_label' => sprintf(
					/* translators: %s: human file size saved */
					\__( '%s saved', 'flux-media-optimizer' ),
					function_exists( 'size_format' ) ? size_format( $diff ) : (string) $diff
				),
			];
		}

		return [
			'percent'     => $percent,
			'bytes'       => $diff,
			'label'       => sprintf(
				/* translators: %s: percentage larger */
				\__( '%s%% larger', 'flux-media-optimizer' ),
				abs( $percent )
			),
			'saved_label' => '',
		];
	}

	/**
	 * Short original format label from mime or URL extension.
	 *
	 * @since 4.3.0
	 * @param string|null $mime_type Mime type.
	 * @param string|null $url       File URL.
	 * @return string
	 */
	private function original_format_label( $mime_type, $url ) {
		$map = [
			'image/jpeg' => 'JPG',
			'image/jpg'  => 'JPG',
			'image/png'  => 'PNG',
			'image/gif'  => 'GIF',
			'image/webp' => 'WEBP',
			'image/heic' => 'HEIC',
			'image/heif' => 'HEIF',
			'video/mp4'  => 'MP4',
			'video/webm' => 'WEBM',
			'video/ogg'  => 'OGG',
			'video/quicktime' => 'MOV',
		];

		if ( is_string( $mime_type ) && isset( $map[ $mime_type ] ) ) {
			return $map[ $mime_type ];
		}

		$extension = is_string( $url ) ? strtoupper( pathinfo( wp_parse_url( $url, PHP_URL_PATH ) ?: $url, PATHINFO_EXTENSION ) ) : '';
		if ( 'JPEG' === $extension ) {
			return 'JPG';
		}

		return $extension !== '' ? $extension : \__( 'Original', 'flux-media-optimizer' );
	}
}
