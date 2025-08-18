<?php

namespace OptimizadorPro\Common\Subscriber;

use OptimizadorPro\Engine\Optimization\CSS\CSSOptimizer;
use OptimizadorPro\Engine\Optimization\JS\JSOptimizer;

/**
 * Optimization Subscriber
 * 
 * This class connects to WordPress hooks and manages the output buffer
 * to process HTML and apply optimizations.
 * 
 * It keeps WordPress logic separate from business logic.
 */
class OptimizationSubscriber {

    /**
     * CSS Optimizer instance
     *
     * @var CSSOptimizer
     */
    private $css_optimizer;

    /**
     * JS Optimizer instance
     *
     * @var JSOptimizer
     */
    private $js_optimizer;

    /**
     * Flag to track if buffer is started
     *
     * @var bool
     */
    private $buffer_started = false;

    /**
     * Constructor
     *
     * @param CSSOptimizer $css_optimizer CSS optimizer instance
     * @param JSOptimizer $js_optimizer JS optimizer instance
     */
    public function __construct(CSSOptimizer $css_optimizer, JSOptimizer $js_optimizer) {
        error_log('OptimizadorPro: OptimizationSubscriber constructor ejecutado');

        $this->css_optimizer = $css_optimizer;
        $this->js_optimizer = $js_optimizer;

        // Register WordPress hooks
        $this->register_hooks();

        error_log('OptimizadorPro: OptimizationSubscriber hooks registrados');
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void {
        error_log('OptimizadorPro: Registrando hooks de OptimizationSubscriber');

        // Start output buffer early
        add_action('template_redirect', [$this, 'start_buffer'], 2);

        // Alternative hook for edge cases
        add_action('wp_head', [$this, 'start_buffer_fallback'], 0);

        error_log('OptimizadorPro: Hooks registrados - template_redirect y wp_head');
    }

    /**
     * Start output buffer to capture HTML
     */
    public function start_buffer(): void {
        error_log('OptimizadorPro: start_buffer llamado');

        // Don't optimize in admin, during AJAX, or if already started
        if (is_admin() || wp_doing_ajax() || $this->buffer_started) {
            error_log('OptimizadorPro: Saltando - admin: ' . (is_admin() ? 'true' : 'false') . ', ajax: ' . (wp_doing_ajax() ? 'true' : 'false') . ', buffer_started: ' . ($this->buffer_started ? 'true' : 'false'));
            return;
        }

        // Don't optimize if user is logged in and preview mode is disabled
        if (is_user_logged_in() && !$this->should_optimize_for_logged_users()) {
            error_log('OptimizadorPro: Usuario logueado y optimización deshabilitada para usuarios logueados');
            return;
        }

        // Don't optimize specific pages
        if ($this->should_skip_optimization()) {
            error_log('OptimizadorPro: Página excluida de optimización');
            return;
        }

        error_log('OptimizadorPro: Iniciando output buffer');
        // Start the buffer
        ob_start([$this, 'process_buffer']);
        $this->buffer_started = true;
    }

    /**
     * Fallback method to start buffer if template_redirect didn't work
     */
    public function start_buffer_fallback(): void {
        if (!$this->buffer_started) {
            $this->start_buffer();
        }
    }

    /**
     * Process the captured HTML buffer
     *
     * @param string $html Captured HTML content
     * @return string Optimized HTML content
     */
    public function process_buffer(string $html): string {
        error_log('OptimizadorPro: process_buffer ejecutado, HTML length: ' . strlen($html));

        // Skip processing if HTML is too small (likely not a full page)
        if (strlen($html) < 255) {
            error_log('OptimizadorPro: HTML muy pequeño, saltando');
            return $html;
        }

        // Skip if no HTML structure detected
        if (strpos($html, '<html') === false && strpos($html, '<!DOCTYPE') === false) {
            error_log('OptimizadorPro: No se detectó estructura HTML, saltando');
            return $html;
        }

        error_log('OptimizadorPro: Procesando buffer HTML válido');

        try {
            // Apply CSS optimization if enabled
            if ($this->is_css_optimization_enabled()) {
                $html = $this->css_optimizer->optimize($html);
            }

            // Apply JS optimization if enabled
            if ($this->is_js_optimization_enabled()) {
                $html = $this->js_optimizer->optimize($html);
            }

        } catch (\Exception $e) {
            // Log error but don't break the site
            error_log('OptimizadorPro optimization error: ' . $e->getMessage());
        }

        return $html;
    }

    /**
     * Check if CSS optimization is enabled
     *
     * @return bool
     */
    private function is_css_optimization_enabled(): bool {
        return get_option('optimizador_pro_minify_css', false);
    }

    /**
     * Check if JS optimization is enabled
     *
     * @return bool
     */
    private function is_js_optimization_enabled(): bool {
        return get_option('optimizador_pro_minify_js', false);
    }

    /**
     * Check if optimization should run for logged-in users
     *
     * @return bool
     */
    private function should_optimize_for_logged_users(): bool {
        return get_option('optimizador_pro_optimize_logged_users', false);
    }

    /**
     * Check if current page should skip optimization
     *
     * @return bool
     */
    private function should_skip_optimization(): bool {
        // Skip for specific pages
        if (is_feed() || is_preview() || is_customize_preview()) {
            return true;
        }

        // Skip for specific post types or pages based on settings
        $excluded_pages = get_option('optimizador_pro_excluded_pages', '');
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
