<?php
/**
 * Main plugin class.
 *
 * @package FluxMedia
 * @since 0.1.0
 */

namespace FluxMedia\App;

use FluxMedia\FluxPlugins\Common\Logger\Logger;
use FluxMedia\App\Services\WordPressProvider;
use FluxMedia\App\Services\Settings;
use FluxMedia\App\Services\ImageConverter;
use FluxMedia\App\Services\VideoConverter;
use FluxMedia\App\Services\FormatSupportDetector;
use FluxMedia\App\Services\ProcessorDetector;
use FluxMedia\FluxPlugins\Common\Services\MenuService;
use FluxMedia\App\Http\Controllers\AdminController;
use FluxMedia\App\Http\Controllers\OptionsController;
use FluxMedia\App\Http\Controllers\StatusController;
use FluxMedia\App\Http\Controllers\ConversionsController;
use FluxMedia\App\Http\Controllers\WebhookController;
use FluxMedia\App\Services\ExternalOptimizationProvider;
use FluxMedia\App\Services\ConversionTracker;
use FluxMedia\App\Services\Database;
use FluxMedia\App\Services\MediaProcessingServiceLocator;
use FluxMedia\App\Services\BulkConverter;
use FluxMedia\App\Services\ActionSchedulerService;
use FluxMedia\App\Services\CleanupService;
use FluxMedia\App\Services\MediaLibraryStatusService;
use FluxMedia\App\Services\ExternalApiClient;

/**
 * Main plugin class that initializes all components.
 *
 * @since 0.1.0
 */
class Plugin {

    /**
     * Logger instance.
     *
     * @since 0.1.0
     * @var Logger
     */
    private $logger;

    /**
     * WordPress provider instance.
     *
     * @since 0.1.0
     * @var WordPressProvider
     */
    private $wordpress_provider;

    /**
     * Settings instance.
     *
     * @since 0.1.0
     * @var Settings
     */
    private $settings;

    /**
     * Image converter instance.
     *
     * @since 0.1.0
     * @var ImageConverter
     */
    private $image_converter;

    /**
     * Video converter instance.
     *
     * @since 0.1.0
     * @var VideoConverter
     */
    private $video_converter;

    /**
     * Initialize the plugin.
     *
     * @since 0.1.0
     * @return void
     */
    public function init() {
        // Ensure database tables exist
        Database::maybe_update_database();

        // Setup our Settings and License pages (register during init to ensure pages are registered before menu.php loads).
        // Translations are available during init, so __() works fine here.
        if ( is_admin() ) {
            add_action( 'init', [ $this, 'register_menu_pages' ], 10 );
        }
        
        // Initialize logger from common library
        $this->logger = Logger::get_instance();
        
        // Initialize settings
        $this->settings = new Settings();
        
        // Initialize converters
        $this->image_converter = new ImageConverter( $this->logger );
        $this->video_converter = new VideoConverter( $this->logger );
        
        // Initialize WordPress provider
        $this->wordpress_provider = new WordPressProvider( $this->image_converter, $this->video_converter );
        
        // Initialize service locator and set it on WordPress provider
        $conversion_tracker = new ConversionTracker( $this->logger );
        $service_locator = new MediaProcessingServiceLocator(
            $this->image_converter,
            $this->video_converter,
            $conversion_tracker,
            null, // BulkConverter will be created after service locator
            $this->logger,
            $this->wordpress_provider
        );
        $bulk_converter = new BulkConverter( $this->logger, $service_locator, $conversion_tracker );
        $service_locator->init();
        $this->wordpress_provider->set_service_locator( $service_locator );

        // Daily cleanup and Media Library status (admin only).
        $external_provider = null;
        if ( Settings::is_external_service_enabled() ) {
            $external_provider = new ExternalOptimizationProvider( $this->logger );
        }
        $cleanup_service = new CleanupService( $this->logger, $external_provider );
        $cleanup_service->init();

        if ( is_admin() ) {
            $media_library_status_service = new MediaLibraryStatusService();
            $media_library_status_service->init();
        }
        
        // Initialize Action Scheduler service on 'init' hook after Action Scheduler is ready.
        // Action Scheduler initializes on 'init' priority 1, so we hook in after that.
        // @since 3.0.3
        $action_scheduler_service = new ActionSchedulerService( $this->logger, $service_locator, $bulk_converter );
        add_action( 'init', [ $action_scheduler_service, 'init' ], 10 );
        $this->wordpress_provider->set_action_scheduler_service( $action_scheduler_service );
        
        // Initialize WordPress provider (registers hooks)
        $this->wordpress_provider->init();
        
        // Compatibility validation is now handled by FluxPlugins::init() in the shared library.
        
        // Initialize admin functionality
        $this->init_admin();
        
        // Initialize REST API
        $this->init_rest_api();
    }

    /**
     * Initialize admin functionality.
     *
     * @since 0.1.0
     * @return void
     */
    private function init_admin() {
        $admin_controller = new AdminController( $this->settings );
        $admin_controller->init();

        // Attachment AJAX handlers are registered in WordPressProvider::init() only (avoid duplicate hooks).
        
        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Register menu pages.
     *
     * Called during init (before menu.php loads) to ensure pages are registered before WordPress checks access.
     *
     * Note: The "Media Optimizer" submenu page is registered by AdminController::register_menu(),
     * so we register the Settings and License pages here.
     *
     * @since 4.0.0
     * @return void
     */
    public function register_menu_pages() {
        $menu_service = MenuService::get_instance();
        // Media Optimizer submenu is registered by AdminController, not here.
        
        // Register License page if this plugin needs it.
        // The common library provides the page, but individual plugins decide if they want to register it.
        $menu_service->register_license_page();
        
        // Logs admin UI and REST API: flux-plugins-common (MenuService::register_logs_page + RestApiService).
        $menu_service->register_logs_page();
    }

    /**
     * Initialize REST API.
     *
     * @since 0.1.0
     * @return void
     */
    private function init_rest_api() {
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    /**
     * Register REST API routes.
     *
     * @since 2.0.1
     * @return void
     */
    public function register_rest_routes() {
        // Initialize detectors and services
        $processor_detector = new ProcessorDetector();
        $format_detector = new FormatSupportDetector( $processor_detector );
        $conversion_tracker = new ConversionTracker( $this->logger );

        // Register controllers (logs: see flux-plugins-common/v1 via RestApiService).
        $options_controller = new OptionsController( $this->settings );
        $status_controller = new StatusController( $format_detector, $processor_detector );
        $conversions_controller = new ConversionsController( $conversion_tracker );
        $options_controller->register_routes();
        $status_controller->register_routes();
        $conversions_controller->register_routes();
        
        // Register webhook controller only when external SaaS is active with a valid license.
        if ( Settings::should_register_webhook_route() ) {
            $webhook_controller = new WebhookController();
            $webhook_controller->register_routes();
        }
    }

    /**
     * Get the logger instance.
     *
     * @since 0.1.0
     * @return Logger
     */
    public function get_logger() {
        return $this->logger;
    }

    /**
     * Get the WordPress provider instance.
     *
     * @since 0.1.0
     * @return WordPressProvider
     */
    public function get_wordpress_provider() {
        return $this->wordpress_provider;
    }

    /**
     * Get the settings instance.
     *
     * @since 0.1.0
     * @return Settings
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get the image converter instance.
     *
     * @since 0.1.0
     * @return ImageConverter
     */
    public function get_image_converter() {
        return $this->image_converter;
    }

    /**
     * Get the video converter instance.
     *
     * @since 0.1.0
     * @return VideoConverter
     */
    public function get_video_converter() {
        return $this->video_converter;
    }

    /**
     * Enqueue admin scripts.
     *
     * @since 0.1.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only enqueue on attachment pages
        if ( 'post.php' !== $hook && 'upload.php' !== $hook ) {
            return;
        }

        // Check if we're on an attachment page
        global $post;
        if ( 'post.php' === $hook && ( ! $post || 'attachment' !== $post->post_type ) ) {
            return;
        }

        // Enqueue attachment-specific JavaScript
        wp_enqueue_script(
            'flux-media-optimizer-attachment',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/dist/attachment.bundle.js',
            [],
            FLUX_MEDIA_OPTIMIZER_VERSION,
            true
        );

        // Localize script with admin data
        wp_localize_script( 'flux-media-optimizer-attachment', 'fluxMediaAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'convertNonce' => wp_create_nonce( 'flux_media_optimizer_convert_attachment' ),
            'disableNonce' => wp_create_nonce( 'flux_media_optimizer_disable_conversion' ),
            'enableNonce' => wp_create_nonce( 'flux_media_optimizer_enable_conversion' ),
        ] );
    }

}
