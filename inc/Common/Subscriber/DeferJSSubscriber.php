<?php

namespace OptimizadorPro\Common\Subscriber;

use OptimizadorPro\Engine\Optimization\DeferJS\DeferJSOptimizer;

/**
 * Defer JS Subscriber
 * 
 * This class connects to WordPress hooks and manages Defer JS functionality.
 * Uses the script_loader_tag filter for more precise control.
 */
class DeferJSSubscriber {

    /**
     * Defer JS Optimizer instance
     *
     * @var DeferJSOptimizer
     */
    private $defer_js_optimizer;

    /**
     * Constructor
     *
     * @param DeferJSOptimizer $defer_js_optimizer Defer JS optimizer instance
     */
    public function __construct(DeferJSOptimizer $defer_js_optimizer) {
        $this->defer_js_optimizer = $defer_js_optimizer;
        
        // Register WordPress hooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void {
        // Use script_loader_tag filter for more precise control
        add_filter('script_loader_tag', [$this, 'add_defer_attribute'], 10, 3);
        
        // Alternative: use output buffer for full HTML processing
        add_action('template_redirect', [$this, 'start_buffer'], 4);
    }

    /**
     * Add defer attribute to individual script tags
     *
     * @param string $tag Script tag HTML
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @return string Modified script tag
     */
    public function add_defer_attribute(string $tag, string $handle, string $src): string {
        // Don't process in admin or if defer is disabled
        if (is_admin() || !$this->is_defer_js_enabled()) {
            return $tag;
        }

        // Skip if already has defer or async
        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }

        // Skip if excluded by handle or src
        if ($this->is_excluded($handle) || $this->is_excluded($src)) {
            return $tag;
        }

        // Skip critical scripts
        if ($this->is_critical_script($handle) || $this->is_critical_script($src)) {
            return $tag;
        }

        // Add defer attribute
        return str_replace('<script ', '<script defer ', $tag);
    }

    /**
     * Start output buffer for full HTML processing (fallback)
     */
    public function start_buffer(): void {
        // Don't process in admin, during AJAX
        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        // Don't process if defer is disabled
        if (!$this->is_defer_js_enabled()) {
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

        // Start the buffer (lower priority than optimization buffer)
        ob_start([$this, 'process_buffer']);
    }

    /**
     * Process the captured HTML buffer (fallback method)
     *
     * @param string $html Captured HTML content
     * @return string Processed HTML content
     */
    public function process_buffer(string $html): string {
        // Skip processing if HTML is too small
        if (strlen($html) < 255) {
            return $html;
        }

        try {
            // Apply defer optimization
            $html = $this->defer_js_optimizer->optimize($html);

        } catch (\Exception $e) {
            // Log error but don't break the site
            error_log('OptimizadorPro Defer JS error: ' . $e->getMessage());
        }

        return $html;
    }

    /**
     * Check if defer JS is enabled
     *
     * @return bool
     */
    private function is_defer_js_enabled(): bool {
        return get_option('optimizador_pro_defer_js', false);
    }

    /**
     * Check if script is excluded
     *
     * @param string $script Script handle, src, or tag
     * @return bool
     */
    private function is_excluded(string $script): bool {
        $exclusions = get_option('optimizador_pro_defer_js_exclusions', '');
        if (empty($exclusions)) {
            return false;
        }

        $excluded_list = array_map('trim', explode("\n", $exclusions));
        
        foreach ($excluded_list as $excluded) {
            if (!empty($excluded) && strpos($script, $excluded) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if script is critical
     *
     * @param string $script Script handle or src
     * @return bool
     */
    private function is_critical_script(string $script): bool {
        $critical_scripts = [
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'wp-admin',
            'customize-controls',
            'admin-bar',
        ];
        
        foreach ($critical_scripts as $critical) {
            if (strpos($script, $critical) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if processing should run for logged-in users
     *
     * @return bool
     */
    private function should_process_for_logged_users(): bool {
        return get_option('optimizador_pro_defer_logged_users', false);
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

        return false;
    }
}
