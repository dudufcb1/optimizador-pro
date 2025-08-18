<?php

namespace OptimizadorPro\Engine\Optimization\JS;

use MatthiasMullie\Minify\JS as JSMinifier;

/**
 * JS Optimizer
 *
 * Pure logic class for JavaScript optimization:
 * - Finds JS files in HTML
 * - Reads and minifies JS content
 * - Combines multiple files into one
 * - Handles exclusions
 * - Manages cache
 */
class JSOptimizer {

    /**
     * Cache directory path
     *
     * @var string
     */
    private $cache_dir;

    /**
     * Plugin URL for serving cached files
     *
     * @var string
     */
    private $plugin_url;

    /**
     * JS files to exclude from optimization
     *
     * @var array
     */
    private $excluded_files = [];

    /**
     * Whether to allow jQuery dequeuing
     *
     * @var bool
     */
    private $allow_jquery_dequeue = false;

    /**
     * Constructor
     *
     * @param string $cache_dir Cache directory path
     * @param string $plugin_url Plugin URL
     */
    public function __construct(string $cache_dir, string $plugin_url) {
        $this->cache_dir = $cache_dir;
        $this->plugin_url = $plugin_url;

        // Load exclusions from WordPress options
        $this->load_exclusions();

        // Load jQuery dequeue setting
        $this->allow_jquery_dequeue = \get_option('optimizador_pro_dequeue_jquery', false);

        // Ensure cache directory exists
        $this->ensure_cache_directory();
    }

    /**
     * Process HTML and optimize JS files
     *
     * @param string $html HTML content
     * @return string Optimized HTML
     */
    public function optimize(string $html): string {
        // Find all JS script tags
        $js_scripts = $this->extract_js_scripts($html);

        if (empty($js_scripts)) {
            return $html;
        }

        // If jQuery dequeuing is enabled, check if it's safe to do so
        if ($this->allow_jquery_dequeue && !$this->is_jquery_safe_to_dequeue($html)) {
            // Temporarily disable jQuery dequeuing for this request
            $this->allow_jquery_dequeue = false;
        }

        // Filter out excluded files and external files
        $optimizable_scripts = $this->filter_optimizable_scripts($js_scripts);

        if (empty($optimizable_scripts)) {
            return $html;
        }

        // Generate cache key based on files and their modification times
        $cache_key = $this->generate_cache_key($optimizable_scripts);
        $cached_file = $this->cache_dir . 'js/combined-' . $cache_key . '.js';
        $cached_url = $this->plugin_url . 'cache/js/combined-' . $cache_key . '.js';

        // Create combined JS if not cached
        if (!file_exists($cached_file)) {
            $this->create_combined_js($optimizable_scripts, $cached_file);
        }

        // Replace original JS scripts with combined one
        return $this->replace_js_scripts($html, $js_scripts, $cached_url);
    }

    /**
     * Extract JS script tags from HTML
     *
     * @param string $html HTML content
     * @return array Array of JS script information
     */
    private function extract_js_scripts(string $html): array {
        $scripts = [];

        // Regex to find JS script tags with src attribute
        $pattern = '/<script[^>]*src=["\']([^"\']+)["\'][^>]*><\/script>/i';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $scripts[] = [
                    'tag' => $match[0],
                    'src' => $match[1],
                ];
            }
        }

        return $scripts;
    }

    /**
     * Filter scripts to only include optimizable ones
     *
     * @param array $scripts JS scripts
     * @return array Filtered scripts
     */
    private function filter_optimizable_scripts(array $scripts): array {
        $optimizable = [];

        foreach ($scripts as $script) {
            $src = $script['src'];

            // Skip external files
            if ($this->is_external_url($src)) {
                continue;
            }

            // Skip excluded files
            if ($this->is_excluded($src)) {
                continue;
            }

            // Skip ES6 modules (cannot be safely combined)
            if ($this->is_es6_module($script['tag'], $src)) {
                continue;
            }

            // Skip jQuery and other critical scripts by default
            if ($this->is_critical_script($src)) {
                continue;
            }

            // Convert relative URLs to absolute paths
            $file_path = $this->url_to_path($src);

            if ($file_path && file_exists($file_path)) {
                $script['path'] = $file_path;
                $optimizable[] = $script;
            }
        }

        return $optimizable;
    }

    /**
     * Generate cache key based on files and modification times
     *
     * @param array $scripts JS scripts
     * @return string Cache key
     */
    private function generate_cache_key(array $scripts): string {
        $key_data = [];

        foreach ($scripts as $script) {
            $key_data[] = $script['src'] . filemtime($script['path']);
        }

        return md5(implode('|', $key_data));
    }

    /**
     * Create combined and minified JS file
     *
     * @param array $scripts JS scripts
     * @param string $output_file Output file path
     */
    private function create_combined_js(array $scripts, string $output_file): void {
        $minifier = new JSMinifier();

        foreach ($scripts as $script) {
            $js_content = file_get_contents($script['path']);
            if ($js_content !== false) {
                $minifier->add($js_content);
            }
        }

        // Ensure output directory exists
        $output_dir = dirname($output_file);
        if (!is_dir($output_dir)) {
            \wp_mkdir_p($output_dir);
        }

        // Save minified JS
        file_put_contents($output_file, $minifier->minify());
    }

    /**
     * Replace original JS scripts with combined one
     *
     * @param string $html Original HTML
     * @param array $all_scripts All JS scripts found
     * @param string $combined_url URL to combined JS
     * @return string Modified HTML
     */
    private function replace_js_scripts(string $html, array $all_scripts, string $combined_url): string {
        // Remove all optimizable JS scripts
        foreach ($all_scripts as $script) {
            if (!$this->is_external_url($script['src']) &&
                !$this->is_excluded($script['src']) &&
                !$this->is_es6_module($script['tag'], $script['src']) &&
                !$this->is_critical_script($script['src'])) {
                $html = str_replace($script['tag'], '', $html);
            }
        }

        // Add combined JS script before closing body tag
        $combined_tag = '<script src="' . \esc_url($combined_url) . '"></script>';
        $html = str_replace('</body>', $combined_tag . "\n</body>", $html);

        return $html;
    }

    /**
     * Check if URL is external
     *
     * @param string $url URL to check
     * @return bool
     */
    private function is_external_url(string $url): bool {
        $site_url = \site_url();
        return strpos($url, 'http') === 0 && strpos($url, $site_url) !== 0;
    }

    /**
     * Check if file is excluded
     *
     * @param string $src File src
     * @return bool
     */
    private function is_excluded(string $src): bool {
        foreach ($this->excluded_files as $excluded) {
            if (strpos($src, $excluded) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if script is an ES6 module that cannot be safely combined
     *
     * @param string $script_tag Full script tag
     * @param string $src Script src
     * @return bool
     */
    private function is_es6_module(string $script_tag, string $src): bool {
        // Check if script tag has type="module"
        if (preg_match('/type=["\']module["\']/', $script_tag)) {
            return true;
        }

        // Check for WordPress Interactivity API (always ES6 modules)
        if (strpos($src, '@wordpress/interactivity') !== false ||
            strpos($src, 'wp-includes/js/dist/') !== false) {
            return true;
        }

        // Try to read file content to detect ES6 syntax
        $file_path = $this->get_local_file_path($src);
        if ($file_path && file_exists($file_path)) {
            $content = file_get_contents($file_path);
            if ($content !== false) {
                // Check for ES6 import/export statements
                if (preg_match('/\b(import\s+.*from|export\s+)/i', $content)) {
                    return true;
                }

                // Check for dynamic imports
                if (preg_match('/import\s*\(/i', $content)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get local file path from URL
     *
     * @param string $src Script URL
     * @return string|null
     */
    private function get_local_file_path(string $src): ?string {
        // Remove query parameters
        $src = strtok($src, '?');

        // Convert URL to local path
        $site_url = \get_site_url();
        if (strpos($src, $site_url) === 0) {
            $relative_path = str_replace($site_url, '', $src);
            return \ABSPATH . ltrim($relative_path, '/');
        }

        return null;
    }

    /**
     * Check if script is critical (jQuery, etc.)
     *
     * @param string $src File src
     * @return bool
     */
    private function is_critical_script(string $src): bool {
        // jQuery scripts - check if dequeuing is allowed
        $jquery_scripts = [
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'wp-includes/js/jquery',
        ];

        foreach ($jquery_scripts as $jquery_script) {
            if (strpos($src, $jquery_script) !== false) {
                // If jQuery dequeuing is allowed, don't treat as critical
                return !$this->allow_jquery_dequeue;
            }
        }

        // Other always-critical scripts
        $always_critical = [
            'wp-admin',
            'wp-login',
            'customize-controls',
            'admin-bar',
        ];

        foreach ($always_critical as $critical) {
            if (strpos($src, $critical) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if it's safe to dequeue jQuery
     *
     * @param string $html HTML content to analyze
     * @return bool
     */
    private function is_jquery_safe_to_dequeue(string $html): bool {
        // Don't dequeue jQuery if we detect jQuery usage in inline scripts
        if (preg_match('/\$\(|\bjQuery\(|\.ready\(|\.click\(|\.on\(/', $html)) {
            return false;
        }

        // Don't dequeue if we detect common jQuery-dependent plugins
        $jquery_dependent_patterns = [
            'wp-admin',
            'customize-preview',
            'woocommerce',
            'contact-form-7',
            'elementor',
            'wpforms',
            'gravity',
        ];

        foreach ($jquery_dependent_patterns as $pattern) {
            if (strpos($html, $pattern) !== false) {
                return false;
            }
        }

        // Check if current page is admin or customizer
        if (\is_admin() || \is_customize_preview()) {
            return false;
        }

        return true;
    }

    /**
     * Convert URL to file path
     *
     * @param string $url URL
     * @return string|false File path or false
     */
    private function url_to_path(string $url) {
        // Remove query parameters
        $url = strtok($url, '?');

        // Convert site URL to ABSPATH
        $site_url = \site_url();
        if (strpos($url, $site_url) === 0) {
            return ABSPATH . substr($url, strlen($site_url) + 1);
        }

        // Handle relative URLs
        if (strpos($url, '/') === 0) {
            return ABSPATH . ltrim($url, '/');
        }

        return false;
    }

    /**
     * Load exclusions from WordPress options
     */
    private function load_exclusions(): void {
        $exclusions = \get_option('optimizador_pro_js_exclusions', '');
        if (!empty($exclusions)) {
            $this->excluded_files = array_map('trim', explode("\n", $exclusions));
        }
    }

    /**
     * Ensure cache directory exists
     */
    private function ensure_cache_directory(): void {
        $js_cache_dir = $this->cache_dir . 'js/';
        if (!is_dir($js_cache_dir)) {
            \wp_mkdir_p($js_cache_dir);
        }
    }
}
