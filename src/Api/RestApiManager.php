<?php
/**
 * REST API manager for Flux Media.
 *
 * @package FluxMedia
 * @since 1.0.0
 */

namespace FluxMedia\Api;

use FluxMedia\Core\Container;
use FluxMedia\Api\Controllers\SystemController;
use FluxMedia\Api\Controllers\LogsController;
use FluxMedia\Api\Controllers\OptionsController;
use FluxMedia\Api\Controllers\ConversionsController;
use FluxMedia\Api\Controllers\QuotaController;
use FluxMedia\Api\Controllers\FilesController;
use FluxMedia\Api\Controllers\CleanupController;

/**
 * REST API manager that coordinates all controllers.
 *
 * @since 1.0.0
 */
class RestApiManager {

	/**
	 * Container instance.
	 *
	 * @since 1.0.0
	 * @var Container
	 */
	private $container;

	/**
	 * Array of controller instances.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $controllers = [];

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Container $container Container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
		$this->init_controllers();
	}

	/**
	 * Initialize all controllers.
	 *
	 * @since 1.0.0
	 */
	private function init_controllers() {
		$this->controllers = [
			'system' => new SystemController( $this->container ),
			'logs' => new LogsController(),
			'options' => new OptionsController(),
			'conversions' => new ConversionsController( $this->container ),
			'quota' => new QuotaController(),
			'files' => new FilesController(),
			'cleanup' => new CleanupController( $this->container ),
		];
	}

	/**
	 * Initialize REST API.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		
		// Also register routes immediately if REST API is already initialized
		if ( did_action( 'rest_api_init' ) ) {
			$this->register_routes();
		}
	}

	/**
	 * Register all REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		foreach ( $this->controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Get a specific controller.
	 *
	 * @since 1.0.0
	 * @param string $name Controller name.
	 * @return object|null Controller instance or null if not found.
	 */
	public function get_controller( $name ) {
		return $this->controllers[ $name ] ?? null;
	}

	/**
	 * Get all controllers.
	 *
	 * @since 1.0.0
	 * @return array Array of controller instances.
	 */
	public function get_controllers() {
		return $this->controllers;
	}
}
