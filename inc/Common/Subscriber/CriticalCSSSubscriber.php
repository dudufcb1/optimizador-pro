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

        // Remove WordPress slashes that might have been added
        $critical_css = wp_unslash($critical_css);

        // Clean and minify the CSS
        $critical_css = $this->minify_css($critical_css);

        // Sanitize CSS properly - avoid wp_strip_all_tags which can cause issues
        $critical_css = $this->sanitize_css($critical_css);

        echo "\n<!-- OptimizadorPro Critical CSS -->\n";
        echo '<style id="optimizador-pro-critical-css">' . $critical_css . '</style>' . "\n";
        echo "<!-- /OptimizadorPro Critical CSS -->\n\n";
    }

    /**
     * Basic CSS minification
     *
     * @param string $css CSS content
     * @return string Minified CSS
     */
    private function minify_css(string $css): string {
        // First, preserve important comments (like license headers)
        $preserved_comments = [];
        $css = preg_replace_callback('/\/\*(!.*?)\*\//s', function($matches) use (&$preserved_comments) {
            $key = '___PRESERVED_COMMENT_' . count($preserved_comments) . '___';
            $preserved_comments[$key] = $matches[0];
            return $key;
        }, $css);

        // Remove all other CSS comments properly
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Remove unnecessary whitespace
        $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
        $css = preg_replace('/\s+/', ' ', $css);

        // Remove space around specific characters
        $css = str_replace([
            ' {', '{ ',
            ' }', '} ',
            ': ', ' :',
            '; ', ' ;',
            ', ', ' ,',
            ' > ', ' + ', ' ~ '
        ], [
            '{', '{',
            '}', '}',
            ':', ':',
            ';', ';',
            ',', ',',
            '>', '+', '~'
        ], $css);

        // Restore preserved comments
        foreach ($preserved_comments as $key => $comment) {
            $css = str_replace($key, $comment, $css);
        }

        return trim($css);
    }

    /**
     * Sanitize CSS content safely
     *
     * @param string $css CSS content
     * @return string Sanitized CSS
     */
    private function sanitize_css(string $css): string {
        // Remove any script or style tags first (before strip_tags)
        $css = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/is', '', $css);

        // Remove any HTML tags that might have been injected
        $css = strip_tags($css);

        // Remove any potentially dangerous CSS content
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/behavior\s*:/i', '', $css);
        $css = preg_replace('/@import\s+["\']javascript:/i', '', $css);

        // Remove excessive whitespace and normalize
        $css = preg_replace('/\s+/', ' ', $css);

        return trim($css);
    }
}
