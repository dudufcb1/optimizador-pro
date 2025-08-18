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
     * Flag to indicate if critical CSS is active for this request
     *
     * @var bool
     */
    private $is_critical_css_active = false;

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
        // Check if critical CSS is active for this request
        $this->is_critical_css_active = !empty(\get_option('optimizador_pro_critical_css'));

        // Find all CSS link tags
        $css_links = $this->extract_css_links($html);

        // Extract inline styles if option is enabled
        $inline_styles = [];
        if (\get_option('optimizador_pro_combine_inline_css', false)) {
            $inline_styles = $this->extract_inline_styles($html);
        }

        if (empty($css_links) && empty($inline_styles)) {
            return $html;
        }

        // Filter out excluded files and external files
        $optimizable_links = $this->filter_optimizable_links($css_links);

        if (empty($optimizable_links) && empty($inline_styles)) {
            return $html;
        }

        // Generate cache key based on files, their modification times, and inline styles
        $cache_key = $this->generate_cache_key($optimizable_links, $inline_styles);
        $cached_file = $this->cache_dir . 'css/combined-' . $cache_key . '.css';
        $cached_url = $this->plugin_url . 'cache/css/combined-' . $cache_key . '.css';

        // Create combined CSS if not cached
        if (!file_exists($cached_file)) {
            $this->create_combined_css($optimizable_links, $cached_file, $inline_styles);
        }

        // Replace original CSS links with combined one
        $html = $this->replace_css_links($html, $css_links, $cached_url);

        // Remove inline styles if they were combined
        if (!empty($inline_styles)) {
            $html = $this->remove_inline_styles($html, $inline_styles);
        }

        return $html;
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
     * Generate cache key based on files, modification times, and inline styles
     *
     * @param array $links CSS links
     * @param array $inline_styles Inline styles (optional)
     * @return string Cache key
     */
    private function generate_cache_key(array $links, array $inline_styles = []): string {
        $key_data = [];

        foreach ($links as $link) {
            $key_data[] = $link['href'] . filemtime($link['path']);
        }

        // Add inline styles to cache key
        foreach ($inline_styles as $style) {
            $key_data[] = 'inline:' . md5($style['content']);
        }

        return md5(implode('|', $key_data));
    }

    /**
     * Create combined and minified CSS file
     *
     * @param array $links CSS links
     * @param string $output_file Output file path
     * @param array $inline_styles Inline styles to include (optional)
     */
    private function create_combined_css(array $links, string $output_file, array $inline_styles = []): void {
        $minifier = new CSSMinifier();

        // Add CSS files first
        foreach ($links as $link) {
            $css_content = file_get_contents($link['path']);
            if ($css_content !== false) {
                $minifier->add($css_content);
            }
        }

        // Add inline styles at the end to preserve cascade order
        if (!empty($inline_styles)) {
            $inline_css = "\n\n/* === Inline styles from <style> tags === */\n";
            foreach ($inline_styles as $style) {
                $inline_css .= "\n/* Inline style block */\n" . $style['content'] . "\n";
            }
            $minifier->add($inline_css);
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
        $combined_tag = '';
        if ($this->is_critical_css_active) {
            // Si el CSS crítico está activo, cargamos el combinado de forma asíncrona
            $escaped_url = \esc_url($combined_url);
            $combined_tag = "<link rel='preload' href='{$escaped_url}' as='style' onload=\"this.rel='stylesheet'\">";
            $combined_tag .= "<noscript><link rel='stylesheet' href='{$escaped_url}'></noscript>";
        } else {
            // Si no, lo cargamos de forma normal (bloqueante)
            $combined_tag = '<link rel="stylesheet" href="' . \esc_url($combined_url) . '" />';
        }

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

    /**
     * Extract inline styles from <style> tags in the head
     *
     * @param string $html HTML content
     * @return array Array of inline style information
     */
    private function extract_inline_styles(string $html): array {
        $styles = [];

        // Only extract styles from the <head> section
        if (preg_match('/<head[^>]*>(.*?)<\/head>/is', $html, $head_match)) {
            $head_content = $head_match[1];

            // Find all <style> tags in the head
            $pattern = '/<style[^>]*>(.*?)<\/style>/is';

            if (preg_match_all($pattern, $head_content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $full_tag = $match[0];
                    $css_content = $match[1];

                    // Skip empty styles
                    if (empty(trim($css_content))) {
                        continue;
                    }

                    // Skip styles that should be excluded
                    if ($this->should_exclude_inline_style($full_tag, $css_content)) {
                        continue;
                    }

                    $styles[] = [
                        'tag' => $full_tag,
                        'content' => $css_content
                    ];
                }
            }
        }

        return $styles;
    }

    /**
     * Check if inline style should be excluded
     *
     * @param string $style_tag Full style tag
     * @param string $css_content CSS content
     * @return bool
     */
    private function should_exclude_inline_style(string $style_tag, string $css_content): bool {
        // Exclude styles with specific attributes that indicate they're critical
        $critical_patterns = [
            'id=["\']wp-custom-css["\']',  // WordPress Customizer CSS
            'id=["\']customizer-css["\']', // Theme customizer
            'data-ampdevmode',             // AMP development mode
            'data-no-optimize',            // Manual exclusion
        ];

        foreach ($critical_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $style_tag)) {
                return true;
            }
        }

        // Exclude very small CSS (likely critical)
        if (strlen(trim($css_content)) < 50) {
            return true;
        }

        // Exclude CSS that contains critical selectors
        $critical_css_patterns = [
            '@media\s+print',           // Print styles
            '@keyframes',               // Animations
            'body\s*{[^}]*display\s*:\s*none', // Hidden body
        ];

        foreach ($critical_css_patterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $css_content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove inline styles from HTML
     *
     * @param string $html HTML content
     * @param array $inline_styles Array of inline styles to remove
     * @return string Modified HTML
     */
    private function remove_inline_styles(string $html, array $inline_styles): string {
        foreach ($inline_styles as $style) {
            $html = str_replace($style['tag'], '', $html);
        }

        return $html;
    }
}
