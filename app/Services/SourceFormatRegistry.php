<?php
/**
 * Single source of truth for supported input image formats.
 *
 * @package FluxMedia
 * @since 4.3.0
 */

namespace FluxMedia\App\Services;

/**
 * Registry of supported input image extensions, MIME types, and processing rules.
 *
 * @since 4.3.0
 */
class SourceFormatRegistry {

	/**
	 * Extension to MIME type map for WordPress upload integration.
	 *
	 * @since 4.3.0
	 * @var array<string, string>
	 */
	private const EXTENSION_MIME_MAP = [
		'jpg'   => 'image/jpeg',
		'jpeg'  => 'image/jpeg',
		'png'   => 'image/png',
		'gif'   => 'image/gif',
		'webp'  => 'image/webp',
		'heic'  => 'image/heic',
		'heif'  => 'image/heif',
		'heics' => 'image/heic-sequence',
		'heifs' => 'image/heif-sequence',
	];

	/**
	 * Extensions that require Imagick for local decode (sequences may also use FFmpeg).
	 *
	 * @since 4.3.0
	 * @var array<int, string>
	 */
	private const IMAGICK_ONLY_EXTENSIONS = [ 'heic', 'heif', 'heics', 'heifs' ];

	/**
	 * Check whether an extension is a supported input format.
	 *
	 * @since 4.3.0
	 * @param string $extension File extension without dot.
	 * @return bool
	 */
	public function is_supported_extension( $extension ) {
		$extension = strtolower( $extension );

		return array_key_exists( $extension, self::EXTENSION_MIME_MAP );
	}

	/**
	 * Check whether a file path is a supported input image.
	 *
	 * @since 4.3.0
	 * @param string $file_path File path.
	 * @return bool
	 */
	public function is_supported_path( $file_path ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		return $this->is_supported_extension( $extension );
	}

	/**
	 * Get MIME type for a supported extension.
	 *
	 * @since 4.3.0
	 * @param string $extension File extension without dot.
	 * @return string|null MIME type or null when unsupported.
	 */
	public function get_mime_for_extension( $extension ) {
		$extension = strtolower( $extension );

		return self::EXTENSION_MIME_MAP[ $extension ] ?? null;
	}

	/**
	 * Get all supported extensions.
	 *
	 * @since 4.3.0
	 * @return array<int, string>
	 */
	public function get_supported_extensions() {
		return array_keys( self::EXTENSION_MIME_MAP );
	}

	/**
	 * Get upload MIME map entries for HEIC/HEIF when locally supported.
	 *
	 * @since 4.3.0
	 * @param bool $heic_supported Whether static HEIC decode is available.
	 * @return array<string, string>
	 */
	public function get_heic_upload_mimes( $heic_supported ) {
		if ( ! $heic_supported ) {
			return [];
		}

		return [
			'heic'  => 'image/heic',
			'heif'  => 'image/heif',
			'heics' => 'image/heic-sequence',
			'heifs' => 'image/heif-sequence',
		];
	}

	/**
	 * Whether the extension requires Imagick for local processing.
	 *
	 * @since 4.3.0
	 * @param string $extension File extension without dot.
	 * @return bool
	 */
	public function requires_imagick( $extension ) {
		return in_array( strtolower( $extension ), self::IMAGICK_ONLY_EXTENSIONS, true );
	}

	/**
	 * Resolve a normalized source format constant from an extension.
	 *
	 * @since 4.3.0
	 * @param string $extension File extension without dot.
	 * @return string
	 */
	public function get_source_format( $extension ) {
		$extension = strtolower( $extension );

		switch ( $extension ) {
			case 'jpg':
			case 'jpeg':
				return Converter::FORMAT_JPEG;
			case 'png':
				return Converter::FORMAT_PNG;
			case 'gif':
				return Converter::FORMAT_GIF;
			case 'webp':
				return Converter::FORMAT_WEBP;
			case 'heic':
			case 'heics':
				return Converter::FORMAT_HEIC;
			case 'heif':
			case 'heifs':
				return Converter::FORMAT_HEIF;
			default:
				return $extension;
		}
	}

	/**
	 * Whether local processing is possible for the given context.
	 *
	 * @since 4.3.0
	 * @param SourceImageContext $context Source context.
	 * @param bool               $heic_supported Static HEIC support flag.
	 * @return bool
	 */
	public function can_process_locally( SourceImageContext $context, $heic_supported ) {
		if ( ! $this->is_supported_extension( $context->get_extension() ) ) {
			return false;
		}

		if ( $context->requires_imagick() && ! $heic_supported ) {
			return false;
		}

		return true;
	}
}
