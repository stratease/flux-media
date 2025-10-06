<?php
/**
 * Main plugin class.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Core;

use FluxMedia\Admin\Admin;
use FluxMedia\Api\RestApiManager;
use FluxMedia\Services\ImageConverter;
use FluxMedia\Services\VideoConverter;
use FluxMedia\Services\ConversionTracker;
use FluxMedia\Utils\Logger;
use Psr\Container\ContainerInterface;

/**
 * Main plugin class that initializes all components.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Container instance.
	 *
	 * @since 1.0.0
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * Plugin constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->container = new Container();
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Initialize services.
		$this->init_services();

		// Initialize admin interface.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Initialize REST API.
		$this->init_rest_api();

		// Initialize hooks.
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 */
	private function init_services() {
		// Create database tables.
		$this->create_database_tables();

		// Register services in container.
		$this->container->set( 'logger', new Logger() );
		$this->container->set( 'image_converter', new ImageConverter( $this->container->get( 'logger' ) ) );
		$this->container->set( 'video_converter', new VideoConverter( $this->container->get( 'logger' ) ) );
		$this->container->set( 'conversion_tracker', new ConversionTracker() );
	}

	/**
	 * Initialize admin interface.
	 *
	 * @since 1.0.0
	 */
	private function init_admin() {
		$admin = new Admin( $this->container );
		$admin->init();
	}

	/**
	 * Initialize REST API.
	 *
	 * @since 1.0.0
	 */
	private function init_rest_api() {
		$rest_api_manager = new RestApiManager( $this->container );
		$rest_api_manager->init();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Hook into media uploads.
		add_action( 'add_attachment', [ $this, 'handle_media_upload' ] );
		add_action( 'edit_attachment', [ $this, 'handle_media_upload' ] );

		// Hook into async processing.
		add_action( 'wp_ajax_flux_media_convert_media', [ $this, 'handle_async_conversion' ] );
		add_action( 'wp_ajax_nopriv_flux_media_convert_media', [ $this, 'handle_async_conversion' ] );

		// Cleanup hook.
		add_action( 'flux_media_cleanup', [ $this, 'cleanup_old_files' ] );
	}

	/**
	 * Handle media upload for conversion.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The attachment ID.
	 */
	public function handle_media_upload( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type ) {
			return;
		}

		// Handle image conversion
		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			$this->process_image_upload( $attachment_id );
		}

		// Handle video conversion (for future implementation)
		if ( strpos( $mime_type, 'video/' ) === 0 ) {
			// TODO: Implement video conversion on upload
			// For now, just log that video was uploaded
			$logger = $this->container->get( 'logger' );
			$logger->info( "Video uploaded: {$attachment_id} (conversion not yet implemented)" );
		}
	}

	/**
	 * Process image upload - convert to WebP/AVIF.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The attachment ID.
	 */
	private function process_image_upload( $attachment_id ) {
		try {
			$image_converter = $this->container->get( 'image_converter' );
			
			// Check if image conversion is available
			if ( ! $image_converter->is_available() ) {
				$logger = $this->container->get( 'logger' );
				$logger->warning( "Image conversion not available for attachment: {$attachment_id}" );
				return;
			}

			// Process the uploaded image
			$results = $image_converter->process_uploaded_image( $attachment_id );

			if ( $results['success'] ) {
				$logger = $this->container->get( 'logger' );
				$formats = implode( ', ', $results['converted_formats'] );
				$logger->info( "Successfully converted image {$attachment_id} to: {$formats}" );
			} else {
				$logger = $this->container->get( 'logger' );
				$errors = implode( ', ', $results['errors'] );
				$logger->warning( "Failed to convert image {$attachment_id}: {$errors}" );
			}

		} catch ( \Exception $e ) {
			$logger = $this->container->get( 'logger' );
			$logger->error( "Exception during image conversion for attachment {$attachment_id}: {$e->getMessage()}" );
		}
	}

	/**
	 * Handle async conversion.
	 *
	 * @since 1.0.0
	 */
	public function handle_async_conversion() {
		// This will be implemented to handle background conversions.
		// TODO: Implement async conversion handling.
	}

	/**
	 * Cleanup old temporary files.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_old_files() {
		// TODO: Implement cleanup of old temporary files.
	}

	/**
	 * Create database tables for the plugin.
	 *
	 * @since 1.0.0
	 */
	private function create_database_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'flux_media_logs';
		
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		// Store the database version for future updates
		update_option( 'flux_media_db_version', '1.0.0' );
	}
}
