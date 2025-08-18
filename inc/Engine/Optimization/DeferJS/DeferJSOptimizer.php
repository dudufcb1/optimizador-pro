<?php

namespace OptimizadorPro\Engine\Optimization\DeferJS;

/**
 * Defer JS Optimizer
 * 
 * Pure logic class for deferring JavaScript execution:
 * - Adds defer attribute to script tags
 * - Handles exclusions
 * - Maintains execution order
 */
class DeferJSOptimizer {

    /**
     * Scripts to exclude from defer
     *
     * @var array
     */
    private $excluded_scripts = [];

    /**
     * Constructor
     */
    public function __construct() {
        // Load exclusions from WordPress options
        $this->load_exclusions();
    }

    /**
     * Process HTML and add defer attributes
     *
     * @param string $html HTML content
     * @return string Optimized HTML
     */
    public function optimize(string $html): string {
        // Find all script tags with src attribute
        $pattern = '/<script([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $before_src = $matches[1];
            $src = $matches[2];
            $after_src = $matches[3];
            $full_tag = $matches[0];
            
            // Skip if already has defer or async
            if (preg_match('/\b(defer|async)\b/', $full_tag)) {
                return $full_tag;
            }
            
            // Skip if excluded
            if ($this->is_excluded($src) || $this->is_excluded($full_tag)) {
                return $full_tag;
            }
            
            // Skip external scripts (they might break)
            if ($this->is_external_url($src)) {
                return $full_tag;
            }
            
            // Skip critical scripts
            if ($this->is_critical_script($src)) {
                return $full_tag;
            }
            
            // Add defer attribute
            return '<script' . $before_src . ' defer src="' . $src . '"' . $after_src . '>';
            
        }, $html);
    }

    /**
     * Check if URL is external
     *
     * @param string $url URL to check
     * @return bool
     */
    private function is_external_url(string $url): bool {
        $site_url = site_url();
        return strpos($url, 'http') === 0 && strpos($url, $site_url) !== 0;
    }

    /**
     * Check if script is excluded
     *
     * @param string $script Script src or full tag
     * @return bool
     */
    private function is_excluded(string $script): bool {
        foreach ($this->excluded_scripts as $excluded) {
            if (strpos($script, $excluded) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if script is critical and shouldn't be deferred
     *
     * @param string $src Script src
     * @return bool
     */
    private function is_critical_script(string $src): bool {
        $critical_scripts = [
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'wp-includes/js/jquery',
            'wp-admin',
            'customize-controls',
            'admin-bar',
        ];
        
        foreach ($critical_scripts as $critical) {
            if (strpos($src, $critical) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Load exclusions from WordPress options
     */
    private function load_exclusions(): void {
        $exclusions = get_option('optimizador_pro_defer_js_exclusions', '');
        if (!empty($exclusions)) {
            $this->excluded_scripts = array_map('trim', explode("\n", $exclusions));
        }
        
        // Add default exclusions
        $default_exclusions = [
            'no-defer',
            'skip-defer',
            'data-no-defer',
        ];
        
        $this->excluded_scripts = array_merge($this->excluded_scripts, $default_exclusions);
    }
}
