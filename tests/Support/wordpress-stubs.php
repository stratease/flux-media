<?php
/**
 * Shared WordPress function stubs for unit tests (global namespace).
 *
 * @package FluxMedia\Tests\Support
 * @since 4.3.0
 */

$GLOBALS['fmo_test_attached_files']       = $GLOBALS['fmo_test_attached_files'] ?? [];
$GLOBALS['fmo_test_original_files']       = $GLOBALS['fmo_test_original_files'] ?? [];
$GLOBALS['fmo_test_attachment_metadata']  = $GLOBALS['fmo_test_attachment_metadata'] ?? [];
$GLOBALS['fmo_test_mimetypes']            = $GLOBALS['fmo_test_mimetypes'] ?? [];
$GLOBALS['fmo_test_post_meta']            = $GLOBALS['fmo_test_post_meta'] ?? [];

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Stub absint.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Stub get_post_meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Return single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$post_id = (int) $post_id;
		if ( ! isset( $GLOBALS['fmo_test_post_meta'][ $post_id ][ $key ] ) ) {
			return $single ? '' : [];
		}

		$value = $GLOBALS['fmo_test_post_meta'][ $post_id ][ $key ];
		return $single ? $value : [ $value ];
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Stub update_post_meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$post_id = (int) $post_id;
		$GLOBALS['fmo_test_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * Stub delete_post_meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	function delete_post_meta( $post_id, $key ) {
		$post_id = (int) $post_id;
		unset( $GLOBALS['fmo_test_post_meta'][ $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	/**
	 * Stub get_attached_file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function get_attached_file( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( array_key_exists( $attachment_id, $GLOBALS['fmo_test_attached_files'] ) ) {
			return $GLOBALS['fmo_test_attached_files'][ $attachment_id ];
		}

		return false;
	}
}

if ( ! function_exists( 'wp_get_original_image_path' ) ) {
	/**
	 * Stub wp_get_original_image_path.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function wp_get_original_image_path( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( array_key_exists( $attachment_id, $GLOBALS['fmo_test_original_files'] ) ) {
			return $GLOBALS['fmo_test_original_files'][ $attachment_id ];
		}

		return false;
	}
}

if ( ! function_exists( 'wp_get_attachment_metadata' ) ) {
	/**
	 * Stub wp_get_attachment_metadata.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	function wp_get_attachment_metadata( $attachment_id ) {
		return $GLOBALS['fmo_test_attachment_metadata'][ (int) $attachment_id ] ?? [];
	}
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	/**
	 * Stub get_post_mime_type.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	function get_post_mime_type( $attachment_id ) {
		return $GLOBALS['fmo_test_mimetypes'][ (int) $attachment_id ] ?? '';
	}
}

if ( ! function_exists( 'wp_check_filetype' ) ) {
	/**
	 * Stub wp_check_filetype.
	 *
	 * @param string $filename Filename.
	 * @return array{ext: string, type: string}
	 */
	function wp_check_filetype( $filename ) {
		$extension = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
		$map       = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'heic' => 'image/heic',
			'heif' => 'image/heif',
			'mp4'  => 'video/mp4',
			'pdf'  => 'application/pdf',
		];

		return [
			'ext'  => $extension,
			'type' => $map[ $extension ] ?? '',
		];
	}
}

$GLOBALS['fmo_test_options'] = $GLOBALS['fmo_test_options'] ?? [];

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stub get_option with in-memory option store for Settings tests.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_option( $option, $default = false ) {
		if ( array_key_exists( $option, $GLOBALS['fmo_test_options'] ) ) {
			return $GLOBALS['fmo_test_options'][ $option ];
		}

		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Stub update_option.
	 *
	 * @param string $option   Option name.
	 * @param mixed  $value    Option value.
	 * @param bool   $autoload Autoload flag (ignored in stubs).
	 * @return bool
	 */
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['fmo_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	/**
	 * Stub get_site_option.
	 *
	 * Disables suite DB logging during unit tests.
	 *
	 * @param string $option  Option name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_site_option( $option, $default = false ) {
		if ( 'flux-plugins_common_options' === $option ) {
			return [ 'enable_logging' => false ];
		}

		return $default;
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/fmo-fake-abspath/' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

$GLOBALS['fmo_test_as_scheduled_actions'] = $GLOBALS['fmo_test_as_scheduled_actions'] ?? [];
$GLOBALS['fmo_test_as_next_action']       = $GLOBALS['fmo_test_as_next_action'] ?? [];
$GLOBALS['fmo_test_actions']              = $GLOBALS['fmo_test_actions'] ?? [];

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Stub do_action.
	 *
	 * @param string $hook Hook name.
	 * @param mixed  ...$args Hook arguments.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		$GLOBALS['fmo_test_actions'][] = [
			'hook' => $hook,
			'args' => $args,
		];

		if ( empty( $GLOBALS['fmo_test_action_callbacks'][ $hook ] ) ) {
			return;
		}

		foreach ( $GLOBALS['fmo_test_action_callbacks'][ $hook ] as $callback ) {
			call_user_func_array( $callback, $args );
		}
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Stub add_action.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $accepted Accepted args.
	 * @return true
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted = 1 ) {
		$GLOBALS['fmo_test_action_callbacks'][ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	/**
	 * Stub as_schedule_single_action.
	 *
	 * @param int    $timestamp Timestamp.
	 * @param string $hook      Hook.
	 * @param array  $args      Args.
	 * @param string $group     Group.
	 * @return int
	 */
	function as_schedule_single_action( $timestamp, $hook, $args = [], $group = '' ) {
		$action_id = count( $GLOBALS['fmo_test_as_scheduled_actions'] ) + 1;
		$GLOBALS['fmo_test_as_scheduled_actions'][] = [
			'id'        => $action_id,
			'timestamp' => (int) $timestamp,
			'hook'      => $hook,
			'args'      => $args,
			'group'     => $group,
		];
		$GLOBALS['fmo_test_as_next_action'][ $hook . '|' . wp_json_encode( $args ) . '|' . (string) $group ] = (int) $timestamp;
		$GLOBALS['fmo_test_as_next_action'][ $hook . '|' . wp_json_encode( $args ) ] = (int) $timestamp;
		return $action_id;
	}
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	/**
	 * Stub as_next_scheduled_action.
	 *
	 * @param string     $hook  Hook.
	 * @param array|null $args  Args.
	 * @param string     $group Group.
	 * @return int|false
	 */
	function as_next_scheduled_action( $hook, $args = null, $group = '' ) {
		$key = $hook . '|' . wp_json_encode( $args ?? [] ) . '|' . (string) $group;
		if ( isset( $GLOBALS['fmo_test_as_next_action'][ $key ] ) ) {
			return $GLOBALS['fmo_test_as_next_action'][ $key ];
		}

		$legacy = $hook . '|' . wp_json_encode( $args ?? [] );
		return $GLOBALS['fmo_test_as_next_action'][ $legacy ] ?? false;
	}
}

if ( ! function_exists( 'as_unschedule_action' ) ) {
	/**
	 * Stub as_unschedule_action.
	 *
	 * @param string     $hook  Hook.
	 * @param array|null $args  Args.
	 * @param string     $group Group.
	 * @return void
	 */
	function as_unschedule_action( $hook, $args = null, $group = '' ) {
		$key    = $hook . '|' . wp_json_encode( $args ?? [] ) . '|' . (string) $group;
		$legacy = $hook . '|' . wp_json_encode( $args ?? [] );
		unset( $GLOBALS['fmo_test_as_next_action'][ $key ], $GLOBALS['fmo_test_as_next_action'][ $legacy ] );
		$GLOBALS['fmo_test_as_scheduled_actions'] = array_values(
			array_filter(
				$GLOBALS['fmo_test_as_scheduled_actions'],
				static function ( $action ) use ( $hook, $args, $group ) {
					if ( ( $action['hook'] ?? '' ) !== $hook ) {
						return true;
					}
					if ( ( $action['args'] ?? null ) != ( $args ?? [] ) ) {
						return true;
					}
					if ( '' !== $group && ( $action['group'] ?? '' ) !== $group ) {
						return true;
					}
					return false;
				}
			)
		);
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * Stub wp_json_encode.
	 *
	 * @param mixed $data Data.
	 * @return string|false
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Stub wp_parse_url.
	 *
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'size_format' ) ) {
	/**
	 * Stub size_format.
	 *
	 * @param int $bytes Bytes.
	 * @return string
	 */
	function size_format( $bytes ) {
		$bytes = (int) $bytes;
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1048576 ) {
			return round( $bytes / 1024 ) . ' KB';
		}
		return round( $bytes / 1048576, 1 ) . ' MB';
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Stub esc_attr.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Stub esc_html.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Stub esc_url.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Stub esc_url_raw.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Stub current_time.
	 *
	 * @param string $type Type of time to retrieve.
	 * @return string|int
	 */
	function current_time( $type ) {
		if ( 'mysql' === $type ) {
			return gmdate( 'Y-m-d H:i:s' );
		}

		return time();
	}
}

if ( ! function_exists( 'get_intermediate_image_sizes' ) ) {
	/**
	 * Stub get_intermediate_image_sizes.
	 *
	 * @return string[]
	 */
	function get_intermediate_image_sizes() {
		return [ 'thumbnail', 'medium', 'medium_large', 'large', '1536x1536' ];
	}
}

if ( ! function_exists( 'WP_Filesystem' ) ) {
	/**
	 * Stub WP_Filesystem for unit tests.
	 *
	 * @return bool
	 */
	function WP_Filesystem() {
		global $wp_filesystem;

		$wp_filesystem = new class() {
			/**
			 * @param string $path Path.
			 * @return bool
			 */
			public function is_writable( $path ) {
				return is_writable( $path );
			}

			/**
			 * @param string $path Path.
			 * @return bool
			 */
			public function exists( $path ) {
				return file_exists( $path );
			}

			/**
			 * @param string $path Path.
			 * @return int|false
			 */
			public function size( $path ) {
				return filesize( $path );
			}

			/**
			 * @param string $path Path.
			 * @return bool
			 */
			public function delete( $path ) {
				return is_file( $path ) ? unlink( $path ) : false;
			}

			/**
			 * @param string $path Path.
			 * @return bool
			 */
			public function is_dir( $path ) {
				return is_dir( $path );
			}

			/**
			 * List directory contents (WP_Filesystem-compatible shape).
			 *
			 * @since 4.3.0
			 * @param string $path Directory path.
			 * @return array<string, array{name: string, type: string}>|false
			 */
			public function dirlist( $path ) {
				if ( ! is_dir( $path ) ) {
					return false;
				}

				$entries = [];
				foreach ( scandir( $path ) as $name ) {
					if ( '.' === $name || '..' === $name ) {
						continue;
					}
					$full = $path . '/' . $name;
					$entries[ $name ] = [
						'name' => $name,
						'type' => is_dir( $full ) ? 'd' : 'f',
					];
				}

				return $entries;
			}
		};

		return true;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	/**
	 * Stub trailingslashit.
	 *
	 * @param string $string Path.
	 * @return string
	 */
	function trailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * Stub untrailingslashit.
	 *
	 * @param string $string Path.
	 * @return string
	 */
	function untrailingslashit( $string ) {
		return rtrim( (string) $string, '/\\' );
	}
}

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * Stub wp_basename.
	 *
	 * @param string $path   Path.
	 * @param string $suffix Optional suffix.
	 * @return string
	 */
	function wp_basename( $path, $suffix = '' ) {
		return basename( str_replace( '\\', '/', (string) $path ), $suffix );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * Stub sanitize_file_name.
	 *
	 * @param string $filename Filename.
	 * @return string
	 */
	function sanitize_file_name( $filename ) {
		$filename = (string) $filename;
		$filename = preg_replace( '/[^A-Za-z0-9._-]/', '', $filename );
		return (string) $filename;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	/**
	 * Stub wp_normalize_path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', (string) $path );
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	/**
	 * Stub wp_upload_dir using optional global override.
	 *
	 * @return array{basedir: string, baseurl: string}
	 */
	function wp_upload_dir() {
		if ( ! empty( $GLOBALS['fmo_test_upload_dir'] ) && is_array( $GLOBALS['fmo_test_upload_dir'] ) ) {
			return $GLOBALS['fmo_test_upload_dir'];
		}

		$base = sys_get_temp_dir() . '/fmo-uploads';
		return [
			'basedir' => $base,
			'baseurl' => 'https://example.com/wp-content/uploads',
			'error'   => false,
		];
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Stub __().
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}

$GLOBALS['fmo_test_posts'] = $GLOBALS['fmo_test_posts'] ?? [];
$GLOBALS['fmo_test_capabilities'] = $GLOBALS['fmo_test_capabilities'] ?? [];
$GLOBALS['fmo_test_attachment_urls'] = $GLOBALS['fmo_test_attachment_urls'] ?? [];

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Stub get_post.
	 *
	 * @param int|null $post_id Post ID.
	 * @return object|null
	 */
	function get_post( $post_id = null ) {
		$post_id = (int) $post_id;
		if ( isset( $GLOBALS['fmo_test_posts'][ $post_id ] ) ) {
			return (object) $GLOBALS['fmo_test_posts'][ $post_id ];
		}

		return null;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Stub current_user_can.
	 *
	 * @param string $capability Capability.
	 * @param mixed  ...$args    Extra args.
	 * @return bool
	 */
	function current_user_can( $capability, ...$args ) {
		$key = $capability;
		if ( ! empty( $args ) ) {
			$key .= ':' . implode( ':', array_map( 'strval', $args ) );
		}

		if ( array_key_exists( $key, $GLOBALS['fmo_test_capabilities'] ) ) {
			return (bool) $GLOBALS['fmo_test_capabilities'][ $key ];
		}

		// Default allow edit_post for tests unless overridden.
		if ( 'edit_post' === $capability ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	/**
	 * Stub wp_get_attachment_url.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function wp_get_attachment_url( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( array_key_exists( $attachment_id, $GLOBALS['fmo_test_attachment_urls'] ) ) {
			return $GLOBALS['fmo_test_attachment_urls'][ $attachment_id ];
		}

		return false;
	}
}

if ( ! function_exists( 'rest_authorization_required_code' ) ) {
	/**
	 * Stub rest_authorization_required_code.
	 *
	 * @return int
	 */
	function rest_authorization_required_code() {
		return 401;
	}
}

$GLOBALS['fmo_test_wp_query_pages'] = $GLOBALS['fmo_test_wp_query_pages'] ?? [];

if ( ! class_exists( 'WP_Query' ) ) {
	/**
	 * Minimal WP_Query stub driven by fmo_test_wp_query_pages.
	 */
	class WP_Query {
		/**
		 * Post IDs for the current page.
		 *
		 * @var int[]
		 */
		public $posts = [];

		/**
		 * @param array<string, mixed> $args Query args.
		 */
		public function __construct( $args = [] ) {
			$page = max( 1, (int) ( $args['paged'] ?? 1 ) );
			$pages = $GLOBALS['fmo_test_wp_query_pages'] ?? [];
			$this->posts = array_map( 'intval', $pages[ $page ] ?? [] );
		}
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub.
	 */
	class WP_Error {
		/**
		 * @var string
		 */
		public $code;

		/**
		 * @var string
		 */
		public $message;

		/**
		 * @var mixed
		 */
		public $data;

		/**
		 * @param string $code    Error code.
		 * @param string $message Message.
		 * @param mixed  $data    Data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}
	}
}
