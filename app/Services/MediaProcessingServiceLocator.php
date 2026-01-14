<?php
/**
 * Media processing service locator.
 *
 * Central service locator that routes media processing requests to either
 * local or external processing services based on settings and license validity.
 *
 * @package FluxMedia\App\Services
 * @since 3.0.0
 */

namespace FluxMedia\App\Services;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\FluxPlugins\Common\License\LicenseService;

/**
 * Media processing service locator.
 *
 * Provides the appropriate processing service based on configuration.
 *
 * @since 3.0.0
 */
class MediaProcessingServiceLocator {

	/**
	 * Local processing service instance.
	 *
	 * @since 3.0.0
	 * @var LocalProcessingService|null
	 */
	private $local_service;

	/**
	 * External processing service instance.
	 *
	 * @since 3.0.0
	 * @var ExternalProcessingService|null
	 */
	private $external_service;

	/**
	 * External optimization provider instance.
	 *
	 * @since 3.0.0
	 * @var ExternalOptimizationProvider|null
	 */
	private $external_provider;

	/**
	 * Logger instance.
	 *
	 * @since 3.0.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Image converter instance.
	 *
	 * @since 3.0.0
	 * @var ImageConverter
	 */
	private $image_converter;

	/**
	 * Video converter instance.
	 *
	 * @since 3.0.0
	 * @var VideoConverter
	 */
	private $video_converter;

	/**
	 * Conversion tracker instance.
	 *
	 * @since 3.0.0
	 * @var ConversionTracker
	 */
	private $conversion_tracker;

	/**
	 * Bulk converter instance.
	 *
	 * @since 3.0.0
	 * @var BulkConverter|null
	 */
	private $bulk_converter;

	/**
	 * WordPress provider instance.
	 *
	 * @since 3.0.0
	 * @var WordPressProvider
	 */
	private $wordpress_provider;

	/**
	 * Constructor.
	 *
	 * @since 3.0.0
	 * @since 4.1.0 Removed LicenseValidationCache parameter - now uses common library LicenseService.
	 * @param ImageConverter         $image_converter Image converter service.
	 * @param VideoConverter         $video_converter Video converter service.
	 * @param ConversionTracker      $conversion_tracker Conversion tracker service.
	 * @param BulkConverter|null     $bulk_converter Bulk converter service (optional, can be set later).
	 * @param Logger        $logger Logger instance.
	 * @param WordPressProvider       $wordpress_provider WordPress provider instance.
	 */
	public function __construct(
		ImageConverter $image_converter,
		VideoConverter $video_converter,
		ConversionTracker $conversion_tracker,
		BulkConverter $bulk_converter = null,
		Logger $logger,
		WordPressProvider $wordpress_provider
	) {
		$this->image_converter = $image_converter;
		$this->video_converter = $video_converter;
		$this->conversion_tracker = $conversion_tracker;
		$this->bulk_converter = $bulk_converter;
		$this->logger = $logger;
		$this->wordpress_provider = $wordpress_provider;
	}

	/**
	 * Initialize the service locator.
	 *
	 * Initializes external provider if external service is enabled.
	 *
	 * @since 3.0.0
	 * @since 4.1.0 Updated to use common library LicenseService.
	 * @return void
	 */
	public function init() {
		$license_service = LicenseService::get_instance();
		// Initialize external provider if external service is enabled and license is valid.
		if ( Settings::is_external_service_enabled() && $license_service->is_license_valid() ) {
			$this->external_provider = new ExternalOptimizationProvider( $this->logger );
			$this->external_provider->init();
		}
	}

	/**
	 * Get the appropriate processing service.
	 *
	 * Returns ExternalProcessingService if external service is enabled and license is valid,
	 * otherwise returns LocalProcessingService.
	 *
	 * @since 3.0.0
	 * @since 4.1.0 Updated to use common library LicenseService.
	 * @return ProcessingServiceInterface Processing service instance.
	 */
	public function get_processor() {
		$license_service = LicenseService::get_instance();
		// Check if external service is enabled and license is valid.
		if ( Settings::is_external_service_enabled() && $license_service->is_license_valid() ) {
			return $this->get_external_service();
		}

		return $this->get_local_service();
	}

	/**
	 * Get local processing service instance.
	 *
	 * @since 3.0.0
	 * @return LocalProcessingService Local processing service.
	 */
	private function get_local_service() {
		if ( null === $this->local_service ) {
			// Create BulkConverter if not already set (to avoid circular dependency)
			if ( null === $this->bulk_converter ) {
				$this->bulk_converter = new BulkConverter( $this->logger, $this, $this->conversion_tracker );
			}

			$this->local_service = new LocalProcessingService(
				$this->image_converter,
				$this->video_converter,
				$this->conversion_tracker,
				$this->bulk_converter,
			    $this->logger
			);
		}

		return $this->local_service;
	}

	/**
	 * Get external processing service instance.
	 *
	 * @since 3.0.0
	 * @return ExternalProcessingService External processing service.
	 */
	private function get_external_service() {
		if ( null === $this->external_service ) {
			// Ensure external provider is initialized.
			if ( null === $this->external_provider ) {
				$this->external_provider = new ExternalOptimizationProvider( $this->logger );
				$this->external_provider->init();
			}

			$this->external_service = new ExternalProcessingService( $this->external_provider );
		}

		return $this->external_service;
	}
}

