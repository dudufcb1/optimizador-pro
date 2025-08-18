<?php

namespace OptimizadorPro\Core;

use OptimizadorPro\Core\DI_Container;
use OptimizadorPro\Engine\Optimization\OptimizationServiceProvider;
use OptimizadorPro\Engine\Media\MediaServiceProvider;
use OptimizadorPro\Engine\Cache\CacheServiceProvider;
use OptimizadorPro\Admin\AdminServiceProvider;

/**
 * Main Plugin Class - The Orchestrator
 * 
 * This class is responsible for:
 * 1. Initializing the DI Container
 * 2. Loading Service Providers
 * 3. Starting everything up
 */
class Plugin {

    /**
     * Instance of our DI Container
     *
     * @var DI_Container
     */
    private $container;

    /**
     * Plugin version
     *
     * @var string
     */
    private $version;

    /**
     * Plugin path
     *
     * @var string
     */
    private $plugin_path;

    /**
     * Constructor
     *
     * @param string $version Plugin version
     * @param string $plugin_path Plugin path
     */
    public function __construct(string $version, string $plugin_path) {
        $this->version = $version;
        $this->plugin_path = $plugin_path;
        $this->container = new DI_Container();
    }

    /**
     * Get the container instance
     *
     * @return DI_Container
     */
    public function get_container(): DI_Container {
        return $this->container;
    }

    /**
     * Load the plugin into WordPress
     *
     * This is where the magic happens - we register all our service providers
     * and let them handle the rest
     */
    public function load(): void {
        // Make container available globally through filter (only when WordPress is loaded)
        if (function_exists('add_filter')) {
            add_filter('optimizador_pro_container', [$this, 'get_container']);
        }

        // Add basic services to container
        $this->register_basic_services();

        // Register all service providers
        $this->register_service_providers();

        // Initialize subscribers based on context (admin vs frontend)
        $this->initialize_subscribers();
    }

    /**
     * Register basic services in the container
     */
    private function register_basic_services(): void {
        $this->container->add('plugin_version', $this->version);
        $this->container->add('plugin_path', $this->plugin_path);
        $this->container->add('plugin_url', \plugin_dir_url($this->plugin_path . 'optimizador-pro.php'));
        $this->container->add('cache_dir', $this->plugin_path . 'cache/');
    }

    /**
     * Register all service providers
     */
    private function register_service_providers(): void {
        // Core optimization services
        $this->container->add_service_provider(new OptimizationServiceProvider());
        
        // Media optimization services (LazyLoad, etc.)
        $this->container->add_service_provider(new MediaServiceProvider());
        
        // Cache management services
        $this->container->add_service_provider(new CacheServiceProvider());
        
        // Admin panel services (only if in admin)
        if (function_exists('is_admin') && is_admin()) {
            $this->container->add_service_provider(new AdminServiceProvider());
        }
    }

    /**
     * Initialize subscribers based on context
     */
    private function initialize_subscribers(): void {
        $subscribers = [];
        
        if (function_exists('is_admin') && is_admin()) {
            $subscribers = $this->get_admin_subscribers();
        } else {
            $subscribers = $this->get_frontend_subscribers();
        }
        
        // Initialize all subscribers
        foreach ($subscribers as $subscriber_id) {
            if ($this->container->has($subscriber_id)) {
                $subscriber = $this->container->get($subscriber_id);
                // Subscribers will auto-register their hooks in their constructors
            }
        }
    }

    /**
     * Get admin subscribers
     *
     * @return array<string>
     */
    private function get_admin_subscribers(): array {
        return [
            'admin_subscriber',
            'critical_css_subscriber',
            'delay_js_execution_subscriber',
            'google_fonts_subscriber',
            'gzip_subscriber',
            // Add more admin subscribers as we build them
        ];
    }

    /**
     * Get frontend subscribers
     *
     * @return array<string>
     */
    private function get_frontend_subscribers(): array {
        return [
            'optimization_subscriber',
            'defer_js_subscriber',
            'media_subscriber',
            'critical_css_subscriber',
            'delay_js_execution_subscriber',
            'google_fonts_subscriber',
            // Add more frontend subscribers as we build them
        ];
    }
}
