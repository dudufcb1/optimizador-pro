<?php

namespace OptimizadorPro\Engine\Optimization\CSS;

use MatthiasMullie\Minify\CSS as CSSMinifier;

/**
 * CSS Optimizer
 * 
 * Pure logic class for CSS optimization:
 * - Finds CSS files in HTML
 * - Reads and minifies CSS content
 * - Combines multiple files into one
 * - Handles exclusions
 * - Manages cache
 */
class CSSOptimizer {

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
     * CSS files to exclude from optimization
     *
     * @var array
     */
    private $excluded_files = [];

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

        // Ensure cache directory exists
        $this->ensure_cache_directory();
    }

    /**
     * Lazy initialization - load exclusions and ensure cache directory
     */
    private function lazy_init(): void {
        static $initialized = false;

        if (!$initialized) {
            $this->load_exclusions();
            $this->ensure_cache_directory();
            $initialized = true;
        }
    }

    /**
     * Process HTML and optimize CSS files
     *
     * @param string $html HTML content
     * @return string Optimized HTML
     */
    public function optimize(string $html): string {
        // Find all CSS link tags
        $css_links = $this->extract_css_links($html);
        
        if (empty($css_links)) {
            return $html;
        }

        // Filter out excluded files and external files
        $optimizable_links = $this->filter_optimizable_links($css_links);
        
        if (empty($optimizable_links)) {
            return $html;
        }

        // Generate cache key based on files and their modification times
        $cache_key = $this->generate_cache_key($optimizable_links);
        $cached_file = $this->cache_dir . 'css/combined-' . $cache_key . '.css';
        $cached_url = $this->plugin_url . 'cache/css/combined-' . $cache_key . '.css';

        // Create combined CSS if not cached
        if (!file_exists($cached_file)) {
            $this->create_combined_css($optimizable_links, $cached_file);
        }

        // Replace original CSS links with combined one
        return $this->replace_css_links($html, $css_links, $cached_url);
    }

    /**
     * Extract CSS link tags from HTML
     *
     * @param string $html HTML content
     * @return array Array of CSS link information
     */
    private function extract_css_links(string $html): array {
        $links = [];
        
        // Regex to find CSS link tags
        $pattern = '/<link[^>]*rel=["\']stylesheet["\'][^>]*>/i';
        
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[0] as $link_tag) {
                // Extract href attribute
                if (preg_match('/href=["\']([^"\']+)["\']/', $link_tag, $href_match)) {
                    $links[] = [
                        'tag' => $link_tag,
                        'href' => $href_match[1],
                    ];
                }
            }
        }
        
        return $links;
    }

    /**
     * Filter links to only include optimizable ones
     *
     * @param array $links CSS links
     * @return array Filtered links
     */
    private function filter_optimizable_links(array $links): array {
        $optimizable = [];
        
        foreach ($links as $link) {
            $href = $link['href'];
            
            // Skip external files
            if ($this->is_external_url($href)) {
                continue;
            }
            
            // Skip excluded files
            if ($this->is_excluded($href)) {
                continue;
            }
            
            // Convert relative URLs to absolute paths
            $file_path = $this->url_to_path($href);
            
            if ($file_path && file_exists($file_path)) {
                $link['path'] = $file_path;
                $optimizable[] = $link;
            }
        }
        
        return $optimizable;
    }

    /**
     * Generate cache key based on files and modification times
     *
     * @param array $links CSS links
     * @return string Cache key
     */
    private function generate_cache_key(array $links): string {
        $key_data = [];
        
        foreach ($links as $link) {
            $key_data[] = $link['href'] . filemtime($link['path']);
        }
        
        return md5(implode('|', $key_data));
    }

    /**
     * Create combined and minified CSS file
     *
     * @param array $links CSS links
     * @param string $output_file Output file path
     */
    private function create_combined_css(array $links, string $output_file): void {
        $minifier = new CSSMinifier();
        
        foreach ($links as $link) {
            $css_content = file_get_contents($link['path']);
            if ($css_content !== false) {
                $minifier->add($css_content);
            }
        }
        
        // Ensure output directory exists
        $output_dir = dirname($output_file);
        if (!is_dir($output_dir)) {
            \wp_mkdir_p($output_dir);
        }
        
        // Save minified CSS
        file_put_contents($output_file, $minifier->minify());
    }

    /**
     * Replace original CSS links with combined one
     *
     * @param string $html Original HTML
     * @param array $all_links All CSS links found
     * @param string $combined_url URL to combined CSS
     * @return string Modified HTML
     */
    private function replace_css_links(string $html, array $all_links, string $combined_url): string {
        // Remove all optimizable CSS links
        foreach ($all_links as $link) {
            if (!$this->is_external_url($link['href']) && !$this->is_excluded($link['href'])) {
                $html = str_replace($link['tag'], '', $html);
            }
        }
        
        // Add combined CSS link at the end of head
        $combined_tag = '<link rel="stylesheet" href="' . \esc_url($combined_url) . '" />';
        $html = str_replace('</head>', $combined_tag . "\n</head>", $html);
        
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
     * @param string $href File href
     * @return bool
     */
    private function is_excluded(string $href): bool {
        foreach ($this->excluded_files as $excluded) {
            if (strpos($href, $excluded) !== false) {
                return true;
            }
        }
        return false;
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
        $exclusions = \get_option('optimizador_pro_css_exclusions', '');
        if (!empty($exclusions)) {
            $this->excluded_files = array_map('trim', explode("\n", $exclusions));
        }
    }

    /**
     * Ensure cache directory exists
     */
    private function ensure_cache_directory(): void {
        $css_cache_dir = $this->cache_dir . 'css/';
        if (!is_dir($css_cache_dir)) {
            \wp_mkdir_p($css_cache_dir);
        }
    }
}
