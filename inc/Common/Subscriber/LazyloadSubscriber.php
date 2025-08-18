<?php

namespace OptimizadorPro\Common\Subscriber;

use OptimizadorPro\Engine\Media\Lazyload\LazyloadOptimizer;

/**
 * LazyLoad Subscriber
 * 
 * This class connects to WordPress hooks and manages LazyLoad functionality.
 * It uses the same output buffer pattern as OptimizationSubscriber.
 */
class LazyloadSubscriber {

    /**
     * LazyLoad Optimizer instance
     *
     * @var LazyloadOptimizer
     */
    private $lazyload_optimizer;

    /**
     * Flag to track if buffer is started
     *
     * @var bool
     */
    private $buffer_started = false;

    /**
     * Constructor
     *
     * @param LazyloadOptimizer $lazyload_optimizer LazyLoad optimizer instance
     */
    public function __construct(LazyloadOptimizer $lazyload_optimizer) {
        $this->lazyload_optimizer = $lazyload_optimizer;

        // Register WordPress hooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void {
        // Start output buffer BEFORE OptimizationSubscriber (priority 1 vs 2)
        add_action('template_redirect', [$this, 'start_buffer'], 1);
    }

    /**
     * Start output buffer to capture HTML
     */
    public function start_buffer(): void {
        // Don't process in admin, during AJAX, or if already started
        if (is_admin() || wp_doing_ajax() || $this->buffer_started) {
            return;
        }

        // Don't process if LazyLoad is disabled
        if (!$this->is_lazyload_enabled()) {
            return;
        }

        // Don't process if user is logged in and preview mode is disabled
        if (is_user_logged_in() && !$this->should_process_for_logged_users()) {
            return;
        }

        // Don't process specific pages
        if ($this->should_skip_processing()) {
            return;
        }

        // Start the buffer
        ob_start([$this, 'process_buffer']);
        $this->buffer_started = true;
    }

    /**
     * Process the captured HTML buffer
     *
     * @param string $html Captured HTML content
     * @return string Processed HTML content
     */
    public function process_buffer(string $html): string {
        // Skip processing if HTML is too small
        if (strlen($html) < 255) {
            return $html;
        }

        // Skip if no HTML structure detected
        if (strpos($html, '<html') === false && strpos($html, '<!DOCTYPE') === false) {
            return $html;
        }

        try {
            // Apply LazyLoad optimization
            $html = $this->lazyload_optimizer->optimize($html);

        } catch (\Exception $e) {
            // Log error but don't break the site
            error_log('OptimizadorPro LazyLoad error: ' . $e->getMessage());
        }

        return $html;
    }

    /**
     * Check if LazyLoad is enabled
     *
     * @return bool
     */
    private function is_lazyload_enabled(): bool {
        return get_option('optimizador_pro_lazyload_enabled', false);
    }

    /**
     * Check if processing should run for logged-in users
     *
     * @return bool
     */
    private function should_process_for_logged_users(): bool {
        return get_option('optimizador_pro_lazyload_logged_users', false);
    }

    /**
     * Check if current page should skip processing
     *
     * @return bool
     */
    private function should_skip_processing(): bool {
        // Skip for specific pages
        if (is_feed() || is_preview() || is_customize_preview()) {
            return true;
        }

        // Skip for AMP pages
        if (function_exists('is_amp_endpoint') && is_amp_endpoint()) {
            return true;
        }

        // Skip for specific post types or pages based on settings
        $excluded_pages = get_option('optimizador_pro_lazyload_excluded_pages', '');
        if (!empty($excluded_pages)) {
            $current_url = $_SERVER['REQUEST_URI'] ?? '';
            $excluded_list = array_map('trim', explode("\n", $excluded_pages));
            
            foreach ($excluded_list as $excluded) {
                if (!empty($excluded) && strpos($current_url, $excluded) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
