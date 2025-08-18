<?php

namespace OptimizadorPro\Common\Subscriber;

/**
 * Google Fonts Optimization Subscriber
 * 
 * Optimizes Google Fonts loading for better performance:
 * - Combines multiple Google Fonts requests into one
 * - Adds preconnect hints for faster DNS resolution
 * - Provides font-display: swap for better loading experience
 * - Optional local hosting of Google Fonts
 */
class GoogleFontsSubscriber {

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
        // Only run on frontend
        if (!\is_admin()) {
            // Process HTML buffer to optimize Google Fonts
            \add_action('template_redirect', [$this, 'start_buffer'], 1);
            
            // Add preconnect hints for Google Fonts
            \add_action('wp_head', [$this, 'add_preconnect_hints'], 1);
        }
    }



    /**
     * Start output buffering to process HTML
     */
    public function start_buffer(): void {
        // Only process if Google Fonts optimization is enabled
        if (!\get_option('optimizador_pro_optimize_google_fonts', false)) {
            return;
        }



        // Don't process admin pages, login, etc.
        if ($this->should_exclude_page()) {
            return;
        }

        \ob_start([$this, 'process_html']);
    }

    /**
     * Process HTML to optimize Google Fonts
     * 
     * @param string $html HTML content
     * @return string Modified HTML
     */
    public function process_html(string $html): string {
        // Only process if Google Fonts optimization is enabled
        if (!\get_option('optimizador_pro_optimize_google_fonts', false)) {
            return $html;
        }

        // Find all Google Fonts links
        $google_fonts = $this->extract_google_fonts($html);

        if (empty($google_fonts)) {
            return $html;
        }

        // Remove original Google Fonts links first
        $html = $this->remove_google_fonts($html, $google_fonts);

        // Choose optimization method based on settings
        if ($this->is_async_loading_enabled()) {
            // Use async loading to prevent render blocking
            $async_html = $this->convert_to_async_loading($google_fonts);
            if (!empty($async_html)) {
                $html = $this->add_async_fonts($html, $async_html);
            }
        } else {
            // Default: combine Google Fonts into a single request
            $combined_url = $this->combine_google_fonts($google_fonts);
            if (!empty($combined_url)) {
                $html = $this->add_combined_google_fonts($html, $combined_url);
            }
        }

        return $html;
    }

    /**
     * Extract Google Fonts links from HTML
     * 
     * @param string $html HTML content
     * @return array Array of Google Fonts information
     */
    private function extract_google_fonts(string $html): array {
        $fonts = [];
        
        // Pattern to match Google Fonts links
        $pattern = '/<link[^>]*href=["\']([^"\']*fonts\.googleapis\.com[^"\']*)["\'][^>]*>/i';
        
        if (\preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $full_tag = $match[0];
                $url = $match[1];
                
                // Skip if this font should be excluded
                if ($this->should_exclude_font($url)) {
                    continue;
                }
                
                // Parse font families and weights from URL
                $font_data = $this->parse_google_font_url($url);
                
                if (!empty($font_data)) {
                    $fonts[] = [
                        'tag' => $full_tag,
                        'url' => $url,
                        'families' => $font_data['families'],
                        'display' => $font_data['display'] ?? 'swap'
                    ];
                }
            }
        }
        
        return $fonts;
    }

    /**
     * Parse Google Font URL to extract families and weights
     * 
     * @param string $url Google Fonts URL
     * @return array Parsed font data
     */
    private function parse_google_font_url(string $url): array {
        $families = [];
        $display = 'swap';
        
        // Parse URL parameters
        $parsed = \parse_url($url);
        if (!isset($parsed['query'])) {
            return [];
        }
        
        \parse_str($parsed['query'], $params);
        
        // Extract font families
        if (isset($params['family'])) {
            // Handle both old and new Google Fonts API formats
            if (\is_array($params['family'])) {
                foreach ($params['family'] as $family) {
                    $families[] = $this->parse_font_family($family);
                }
            } else {
                $families[] = $this->parse_font_family($params['family']);
            }
        }
        
        // Extract display parameter
        if (isset($params['display'])) {
            $display = $params['display'];
        }
        
        return [
            'families' => array_filter($families),
            'display' => $display
        ];
    }

    /**
     * Parse individual font family string
     * 
     * @param string $family_string Font family string
     * @return array Parsed family data
     */
    private function parse_font_family(string $family_string): array {
        // Handle formats like "Open+Sans:300,400,600" or "Open Sans:wght@300;400;600"
        if (\strpos($family_string, ':') !== false) {
            list($name, $weights) = \explode(':', $family_string, 2);
        } else {
            $name = $family_string;
            $weights = '400'; // Default weight
        }
        
        // Clean up font name
        $name = \str_replace('+', ' ', $name);
        $name = \urldecode($name);
        
        // Parse weights
        $weight_list = [];
        if (\strpos($weights, 'wght@') !== false) {
            // New API format: "wght@300;400;600"
            $weights = \str_replace('wght@', '', $weights);
            $weight_list = \explode(';', $weights);
        } else {
            // Old API format: "300,400,600"
            $weight_list = \explode(',', $weights);
        }
        
        return [
            'name' => \trim($name),
            'weights' => array_map('trim', $weight_list)
        ];
    }

    /**
     * Combine multiple Google Fonts into a single request
     * 
     * @param array $fonts Array of Google Fonts data
     * @return string Combined Google Fonts URL
     */
    private function combine_google_fonts(array $fonts): string {
        if (empty($fonts)) {
            return '';
        }
        
        $combined_families = [];
        $display = 'swap'; // Default display
        
        foreach ($fonts as $font) {
            foreach ($font['families'] as $family) {
                $family_name = $family['name'];
                $weights = $family['weights'];
                
                // Combine weights for the same family
                if (isset($combined_families[$family_name])) {
                    $combined_families[$family_name] = \array_unique(
                        \array_merge($combined_families[$family_name], $weights)
                    );
                } else {
                    $combined_families[$family_name] = $weights;
                }
            }
            
            // Use display from first font (they should all be the same)
            if (!empty($font['display'])) {
                $display = $font['display'];
            }
        }
        
        // Build combined URL using Google Fonts API v2
        $family_strings = [];
        foreach ($combined_families as $name => $weights) {
            // Sort weights numerically
            \sort($weights, SORT_NUMERIC);
            
            // Format: "Open Sans:wght@300;400;600"
            $family_strings[] = \urlencode($name) . ':wght@' . \implode(';', $weights);
        }
        
        if (empty($family_strings)) {
            return '';
        }
        
        // Build final URL
        $base_url = 'https://fonts.googleapis.com/css2';
        $params = [
            'family' => \implode('&family=', $family_strings),
            'display' => $display
        ];
        
        return $base_url . '?' . \http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Remove original Google Fonts links from HTML
     * 
     * @param string $html HTML content
     * @param array $fonts Array of Google Fonts to remove
     * @return string Modified HTML
     */
    private function remove_google_fonts(string $html, array $fonts): string {
        foreach ($fonts as $font) {
            $html = \str_replace($font['tag'], '', $html);
        }
        
        return $html;
    }

    /**
     * Add combined Google Fonts link to HTML
     * 
     * @param string $html HTML content
     * @param string $combined_url Combined Google Fonts URL
     * @return string Modified HTML
     */
    private function add_combined_google_fonts(string $html, string $combined_url): string {
        // Create optimized link tag
        $link_tag = \sprintf(
            '<link rel="stylesheet" href="%s" media="all">',
            \esc_url($combined_url)
        );
        
        // Add before closing </head> tag
        $html = \str_replace('</head>', $link_tag . "\n</head>", $html);
        
        return $html;
    }



    /**
     * Add async fonts HTML
     *
     * @param string $html HTML content
     * @param string $async_html Async loading HTML
     * @return string Modified HTML
     */
    private function add_async_fonts(string $html, string $async_html): string {
        // Add before closing </head> tag
        $html = \str_replace('</head>', $async_html . "</head>", $html);

        return $html;
    }

    /**
     * Add preconnect hints for Google Fonts
     */
    public function add_preconnect_hints(): void {
        // Only add if Google Fonts optimization is enabled
        if (!\get_option('optimizador_pro_optimize_google_fonts', false)) {
            return;
        }
        
        // Add preconnect hints for faster DNS resolution
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }

    /**
     * Check if font should be excluded from optimization
     * 
     * @param string $url Font URL
     * @return bool
     */
    private function should_exclude_font(string $url): bool {
        // Check user-defined exclusions
        $exclusions = \get_option('optimizador_pro_google_fonts_exclusions', '');
        if (!empty($exclusions)) {
            $exclusion_list = array_filter(array_map('trim', explode("\n", $exclusions)));
            
            foreach ($exclusion_list as $exclusion) {
                if (!empty($exclusion) && \strpos($url, $exclusion) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if current page should be excluded from Google Fonts optimization
     * 
     * @return bool
     */
    private function should_exclude_page(): bool {
        // Exclude admin pages
        if (\is_admin() || \is_login()) {
            return true;
        }

        // Exclude customizer
        if (\is_customize_preview()) {
            return true;
        }

        // Check general page exclusions
        $excluded_pages = \get_option('optimizador_pro_excluded_pages', '');
        if (!empty($excluded_pages)) {
            $current_url = $_SERVER['REQUEST_URI'] ?? '';
            $exclusions = array_filter(array_map('trim', explode("\n", $excluded_pages)));
            
            foreach ($exclusions as $exclusion) {
                if (!empty($exclusion) && \strpos($current_url, $exclusion) !== false) {
                    return true;
                }
            }
        }

        return false;
    }



    /**
     * Check if async loading is enabled
     */
    private function is_async_loading_enabled(): bool {
        return \get_option('optimizador_pro_google_fonts_async_loading', false);
    }



    /**
     * Convert Google Fonts links to async loading
     */
    private function convert_to_async_loading(array $google_fonts): string {
        if (empty($google_fonts)) {
            return '';
        }

        $async_html = '';
        foreach ($google_fonts as $font_url) {
            $async_html .= '<link rel="preload" href="' . esc_url($font_url) . '" as="style" onload="this.rel=\'stylesheet\'">' . "\n";
            $async_html .= '<noscript><link rel="stylesheet" href="' . esc_url($font_url) . '"></noscript>' . "\n";
        }

        return $async_html;
    }
}
