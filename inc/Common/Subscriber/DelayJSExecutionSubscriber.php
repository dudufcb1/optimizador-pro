<?php

namespace OptimizadorPro\Common\Subscriber;

/**
 * Delay JS Execution Subscriber
 * 
 * Delays JavaScript execution until user interaction (click, scroll, keydown)
 * This is similar to WP Rocket's "Delay JavaScript execution" feature
 */
class DelayJSExecutionSubscriber {

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
            // Process HTML buffer to delay JS
            \add_action('template_redirect', [$this, 'start_buffer'], 2);
            
            // Add the delay JS loader script
            \add_action('wp_footer', [$this, 'add_delay_js_loader'], 999);
        }
    }

    /**
     * Start output buffering to process HTML
     */
    public function start_buffer(): void {
        // Only process if delay JS is enabled
        if (!\get_option('optimizador_pro_delay_js', false)) {
            return;
        }

        // Don't process admin pages, login, etc.
        if ($this->should_exclude_page()) {
            return;
        }

        \ob_start([$this, 'process_html']);
    }

    /**
     * Process HTML to delay JavaScript execution
     * 
     * @param string $html HTML content
     * @return string Modified HTML
     */
    public function process_html(string $html): string {
        // Only process if delay JS is enabled
        if (!\get_option('optimizador_pro_delay_js', false)) {
            return $html;
        }

        // Find all script tags
        $pattern = '/<script\b[^>]*>(.*?)<\/script>/is';
        
        return \preg_replace_callback($pattern, [$this, 'delay_script_tag'], $html);
    }

    /**
     * Process individual script tag for delaying
     * 
     * @param array $matches Regex matches
     * @return string Modified script tag
     */
    private function delay_script_tag(array $matches): string {
        $full_tag = $matches[0];
        $script_content = $matches[1] ?? '';

        // Don't delay if script should be excluded
        if ($this->should_exclude_script($full_tag)) {
            return $full_tag;
        }

        // Don't delay inline scripts that are too critical
        if ($this->is_critical_inline_script($script_content)) {
            return $full_tag;
        }

        // For external scripts with src
        if (\preg_match('/src=["\']([^"\']+)["\']/', $full_tag, $src_matches)) {
            $src = $src_matches[1];
            
            // Don't delay excluded external scripts
            if ($this->should_exclude_external_script($src)) {
                return $full_tag;
            }
            
            // Convert to delayed loading
            return $this->create_delayed_external_script($full_tag, $src);
        }

        // For inline scripts
        if (!empty(trim($script_content))) {
            return $this->create_delayed_inline_script($script_content);
        }

        return $full_tag;
    }

    /**
     * Create delayed external script tag
     * 
     * @param string $original_tag Original script tag
     * @param string $src Script source URL
     * @return string Delayed script tag
     */
    private function create_delayed_external_script(string $original_tag, string $src): string {
        // Extract attributes
        $attributes = $this->extract_script_attributes($original_tag);
        $attr_string = $this->build_attribute_string($attributes);
        
        return sprintf(
            '<script type="optimizador-pro-delayed" data-src="%s"%s></script>',
            \esc_attr($src),
            $attr_string
        );
    }

    /**
     * Create delayed inline script
     * 
     * @param string $content Script content
     * @return string Delayed script tag
     */
    private function create_delayed_inline_script(string $content): string {
        return sprintf(
            '<script type="optimizador-pro-delayed">%s</script>',
            $content
        );
    }

    /**
     * Check if script should be excluded from delaying
     * 
     * @param string $script_tag Full script tag
     * @return bool
     */
    private function should_exclude_script(string $script_tag): bool {
        // Always exclude scripts that are already delayed
        if (\strpos($script_tag, 'optimizador-pro-delayed') !== false) {
            return true;
        }

        // Exclude JSON-LD and other structured data
        if (\preg_match('/type=["\']application\/(ld\+json|json)["\']/', $script_tag)) {
            return true;
        }

        // Exclude critical scripts by pattern
        $critical_patterns = [
            'jquery',
            'wp-includes/js/jquery',
            'wp-admin',
            'wp-login',
            'customizer',
            'admin-bar'
        ];

        foreach ($critical_patterns as $pattern) {
            if (\stripos($script_tag, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if external script should be excluded
     * 
     * @param string $src Script source URL
     * @return bool
     */
    private function should_exclude_external_script(string $src): bool {
        // Check user-defined exclusions
        $exclusions = \get_option('optimizador_pro_delay_js_exclusions', '');
        if (!empty($exclusions)) {
            $exclusion_list = array_filter(array_map('trim', explode("\n", $exclusions)));
            
            foreach ($exclusion_list as $exclusion) {
                if (!empty($exclusion) && \strpos($src, $exclusion) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if inline script is critical and shouldn't be delayed
     * 
     * @param string $content Script content
     * @return bool
     */
    private function is_critical_inline_script(string $content): bool {
        // Very short scripts are usually critical
        if (\strlen(\trim($content)) < 50) {
            return true;
        }

        // Scripts that set critical variables
        $critical_patterns = [
            'var ajaxurl',
            'window.wp',
            'document.documentElement.className',
            'dataLayer',
            'gtag',
            'ga(',
            '_gaq'
        ];

        foreach ($critical_patterns as $pattern) {
            if (\stripos($content, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract attributes from script tag
     * 
     * @param string $script_tag Script tag
     * @return array Attributes
     */
    private function extract_script_attributes(string $script_tag): array {
        $attributes = [];
        
        // Extract common attributes
        if (\preg_match('/async\b/', $script_tag)) {
            $attributes['async'] = 'async';
        }
        
        if (\preg_match('/defer\b/', $script_tag)) {
            $attributes['defer'] = 'defer';
        }
        
        if (\preg_match('/id=["\']([^"\']+)["\']/', $script_tag, $matches)) {
            $attributes['id'] = $matches[1];
        }

        return $attributes;
    }

    /**
     * Build attribute string from array
     * 
     * @param array $attributes Attributes array
     * @return string Attribute string
     */
    private function build_attribute_string(array $attributes): string {
        $attr_parts = [];
        
        foreach ($attributes as $name => $value) {
            if ($value === $name) {
                $attr_parts[] = $name;
            } else {
                $attr_parts[] = sprintf('%s="%s"', $name, \esc_attr($value));
            }
        }
        
        return empty($attr_parts) ? '' : ' ' . implode(' ', $attr_parts);
    }

    /**
     * Check if current page should be excluded from delay JS
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
     * Add the delay JS loader script to footer
     */
    public function add_delay_js_loader(): void {
        // Only add if delay JS is enabled
        if (!\get_option('optimizador_pro_delay_js', false)) {
            return;
        }

        ?>
        <script id="optimizador-pro-delay-js-loader">
        (function() {
            'use strict';
            
            var delayedScripts = [];
            var userInteracted = false;
            
            // Collect all delayed scripts
            function collectDelayedScripts() {
                var scripts = document.querySelectorAll('script[type="optimizador-pro-delayed"]');
                scripts.forEach(function(script) {
                    delayedScripts.push(script);
                });
            }
            
            // Execute delayed scripts
            function executeDelayedScripts() {
                if (userInteracted) return;
                userInteracted = true;
                
                delayedScripts.forEach(function(script) {
                    // Skip scripts that might be problematic
                    if (!script || !script.parentNode) {
                        return;
                    }

                    var newScript = document.createElement('script');

                    // Copy attributes safely
                    try {
                        Array.from(script.attributes).forEach(function(attr) {
                            if (attr.name === 'type') return;
                            if (attr.name === 'data-src') {
                                newScript.src = attr.value;
                            } else {
                                newScript.setAttribute(attr.name, attr.value);
                            }
                        });
                    } catch (e) {
                        // Skip this script if attribute copying fails
                        return;
                    }
                    
                    // Copy inline content safely
                    if (script.innerHTML) {
                        try {
                            newScript.innerHTML = script.innerHTML;
                        } catch (e) {
                            // Fallback: use textContent for problematic content
                            newScript.textContent = script.textContent || script.innerHTML;
                        }
                    }

                    // Replace the delayed script safely
                    try {
                        script.parentNode.replaceChild(newScript, script);
                    } catch (e) {
                        // Fallback: insert before and remove original
                        script.parentNode.insertBefore(newScript, script);
                        script.parentNode.removeChild(script);
                    }
                });
                
                // Clear the array
                delayedScripts = [];
            }
            
            // Event listeners for user interaction
            var events = ['click', 'scroll', 'keydown', 'mousemove', 'touchstart'];
            
            function addEventListeners() {
                events.forEach(function(event) {
                    document.addEventListener(event, executeDelayedScripts, {
                        once: true,
                        passive: true
                    });
                });
            }
            
            // Initialize when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    collectDelayedScripts();
                    addEventListeners();
                });
            } else {
                collectDelayedScripts();
                addEventListeners();
            }
            
            // Fallback: execute after 5 seconds regardless
            setTimeout(executeDelayedScripts, 5000);
        })();
        </script>
        <?php
    }
}
