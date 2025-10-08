<?php
/**
 * Main plugin class.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\Core;

use FluxMedia\Admin\Admin;
use FluxMedia\Api\RestApiManager;
use FluxMedia\Services\ImageConverter;
use FluxMedia\Services\VideoConverter;
use FluxMedia\Services\ConversionTracker;
use FluxMedia\Services\QuotaManager;
use FluxMedia\Utils\Logger;
use FluxMedia\Providers\WordPressProvider;
use Psr\Container\ContainerInterface;

/**
 * Main plugin class that initializes all components.
 *
 * @since 0.1.0
 */
class Plugin {

	/**
	 * Container instance.
	 *
	 * @since 0.1.0
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * WordPress provider instance.
	 *
	 * @since 0.1.0
	 * @var WordPressProvider
	 */
	private $wordpress_provider;

	/**
	 * Plugin constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->container = new Container();
	}

	/**
	 * Initialize the plugin.
	 *
	 * @since 0.1.0
	 */
	public function init() {
		// Initialize services.
		$this->init_services();

		// Initialize WordPress provider (handles all WordPress hooks).
		$this->init_wordpress_provider();

		// Initialize admin interface.
		if ( is_admin() ) {
			$this->init_admin();
		}

		// Initialize REST API.
		$this->init_rest_api();
	}

	/**
	 * Initialize services.
	 *
	 * @since 0.1.0
	 */
	private function init_services() {
		// Create database tables.
		$this->create_database_tables();

		// Register services in container.
		$this->container->set( 'logger', new Logger() );
		$this->container->set( 'quota_manager', new QuotaManager( $this->container->get( 'logger' ) ) );
		$this->container->set( 'conversion_tracker', new ConversionTracker( $this->container->get( 'logger' ) ) );
		
		// Create converter instances (pure business logic, no WordPress dependencies)
		$logger = $this->container->get( 'logger' );
		$image_converter = new ImageConverter( $logger );
		$video_converter = new VideoConverter( $logger );
		
		// Register converters
		$this->container->set( 'image_converter', $image_converter );
		$this->container->set( 'video_converter', $video_converter );
		
		
		// Initialize quota tracking
		$quota_manager = $this->container->get( 'quota_manager' );
		$quota_manager->initialize_quota_tracking();
	}

	/**
	 * Initialize WordPress provider.
	 *
	 * @since 0.1.0
	 */
	private function init_wordpress_provider() {
		$image_converter = $this->container->get( 'image_converter' );
		$video_converter = $this->container->get( 'video_converter' );
		
		$this->wordpress_provider = new WordPressProvider( $image_converter, $video_converter );
		$this->wordpress_provider->init();
	}

	/**
	 * Initialize admin interface.
	 *
	 * @since 0.1.0
	 */
	private function init_admin() {
		$admin = new Admin( $this->container );
		$admin->init();
	}


	/**
	 * Initialize REST API.
	 *
	 * @since 0.1.0
	 */
	private function init_rest_api() {
		$rest_api_manager = new RestApiManager( $this->container );
		$rest_api_manager->init();
	}



	/**
	 * Create database tables for the plugin.
	 *
	 * @since 0.1.0
	 */
	private function create_database_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// Create logs table
		$logs_table = $wpdb->prefix . 'flux_media_logs';
		$logs_sql = "CREATE TABLE $logs_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL,
			message text NOT NULL,
			context longtext,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $logs_sql );

		// Create conversion tracking table
		$conversions_table = $wpdb->prefix . 'flux_media_conversions';
		$conversions_sql = "CREATE TABLE $conversions_table (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) NOT NULL,
			file_type varchar(20) NOT NULL,
			converted_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY attachment_file_type (attachment_id, file_type),
			KEY attachment_id (attachment_id),
			KEY file_type (file_type),
			KEY converted_at (converted_at)
		) $charset_collate;";

		dbDelta( $conversions_sql );

		// Store the database version for future updates
		update_option( 'flux_media_db_version', '1.1.0' );
	}
}
