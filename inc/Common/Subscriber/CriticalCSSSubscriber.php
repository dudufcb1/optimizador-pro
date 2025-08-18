<?php

namespace OptimizadorPro\Common\Subscriber;

/**
 * Critical CSS Subscriber
 * 
 * Handles manual critical CSS injection and async loading of non-critical CSS
 * This is the pragmatic alternative to automated RUCSS (Remove Unused CSS)
 */
class CriticalCSSSubscriber {

    /**
     * Constructor
     */
    public function __construct() {
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void {
        // Only run on frontend (not admin, not AJAX, not REST API)
        if (!\is_admin() && !wp_doing_ajax() && !defined('REST_REQUEST')) {
            // Inject critical CSS in head with high priority
            \add_action('wp_head', [$this, 'inject_critical_css'], 1);
        }
    }

    /**
     * Inject critical CSS inline in the head
     */
    public function inject_critical_css(): void {
        // Skip if we're in admin, doing AJAX, or REST API
        if (\is_admin() || wp_doing_ajax() || defined('REST_REQUEST')) {
            return;
        }

        $critical_css = \get_option('optimizador_pro_critical_css', '');

        // Only inject if critical CSS is provided
        if (empty(trim($critical_css))) {
            return;
        }

        // Clean and minify the CSS
        $critical_css = $this->minify_css($critical_css);

        echo "\n<!-- OptimizadorPro Critical CSS -->\n";
        echo '<style id="optimizador-pro-critical-css">' . wp_strip_all_tags($critical_css) . '</style>' . "\n";
        echo "<!-- /OptimizadorPro Critical CSS -->\n\n";
    }

    /**
     * Basic CSS minification
     * 
     * @param string $css CSS content
     * @return string Minified CSS
     */
    private function minify_css(string $css): string {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove space around specific characters
        $css = str_replace([' {', '{ ', ' }', '} ', ': ', ' :', '; ', ' ;', ', ', ' ,'], ['{', '{', '}', '}', ':', ':', ';', ';', ',', ','], $css);
        
        return trim($css);
    }
}
