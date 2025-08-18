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
        if ($this->should_exclude_script($full_tag, $script_content)) {
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
        // Use a different type for inline scripts to handle them safely
        return sprintf(
            '<script type="optimizador-pro-delayed-inline">%s</script>',
            \base64_encode($content) // Base64 encode to prevent parsing issues
        );
    }

    /**
     * Check if script should be excluded from delaying
     * 
     * @param string $script_tag Full script tag
     * @param string $script_content Inline script content
     * @return bool
     */
    private function should_exclude_script(string $script_tag, string $script_content): bool {
        // Always exclude scripts that are already delayed
        if (\strpos($script_tag, 'optimizador-pro-delayed') !== false) {
            return true;
        }

        // Exclude JSON-LD, importmaps, and other structured data
        if (\preg_match('/type=["\']application\/(ld\+json|json|importmap)["\']/', $script_tag)) {
            return true;
        }
        
        // Exclude ES Modules, which cannot be delayed this way
        if (\preg_match('/type=["\']module["\']/', $script_tag)) {
            return true;
        }

        // Exclude critical scripts by pattern in the tag
        $critical_patterns = [
            'jquery',
            'wp-includes/js/jquery',
            '@wordpress/interactivity', // Critical for new WP features
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
        
        // Exclude scripts that are likely JSON (like importmaps)
        if (!empty($script_content) && strpos(trim($script_content), '{') === 0) {
            json_decode($script_content);
            if (json_last_error() === JSON_ERROR_NONE) {
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
        if (empty(trim($content)) || \strlen(\trim($content)) < 50) {
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
        if (\is_admin()) {
            return true;
        }

        // Exclude login page
        if (\in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php'])) {
            return true;
        }

        // Exclude customizer
        if (\is_customize_preview()) {
            return true;
        }

        // Exclude if user is logged in and viewing admin bar
        if (\is_user_logged_in() && \is_admin_bar_showing()) {
            // Allow delay JS but be more conservative
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

        // Don't add on excluded pages
        if ($this->should_exclude_page()) {
            return;
        }

        ?>
        <script id="optimizador-pro-delay-js-loader">
        (function() {
            'use strict';

            var delayedScripts = [];
            var userInteracted = false;
            var interactionEvents = ['click', 'scroll', 'keydown', 'touchstart', 'mousedown'];

            // Collect all delayed scripts
            function collectDelayedScripts() {
                var scripts = document.querySelectorAll('script[type="optimizador-pro-delayed"], script[type="optimizador-pro-delayed-inline"]');
                delayedScripts = Array.from(scripts);
            }

            // Execute all delayed scripts
            function executeDelayedScripts() {
                if (userInteracted || delayedScripts.length === 0) {
                    return;
                }

                userInteracted = true;

                // Remove event listeners
                interactionEvents.forEach(function(event) {
                    document.removeEventListener(event, executeDelayedScripts, { passive: true });
                });

                // Process each delayed script
                delayedScripts.forEach(function(script) {
                    if (!script || !script.parentNode) {
                        return;
                    }

                    try {
                        var newScript = document.createElement('script');

                        // Handle external scripts
                        if (script.hasAttribute('data-src')) {
                            newScript.src = script.getAttribute('data-src');
                        }

                        // Handle inline scripts
                        if (script.type === 'optimizador-pro-delayed-inline') {
                            try {
                                // Decode base64 content
                                var content = atob(script.textContent || script.innerHTML);
                                newScript.textContent = content;
                            } catch (e) {
                                // Fallback to direct content if base64 fails
                                newScript.textContent = script.textContent || script.innerHTML;
                            }
                        }

                        // Copy attributes (except type and data-src)
                        Array.from(script.attributes).forEach(function(attr) {
                            if (attr.name !== 'type' && attr.name !== 'data-src') {
                                newScript.setAttribute(attr.name, attr.value);
                            }
                        });

                        // Replace the script
                        script.parentNode.replaceChild(newScript, script);

                    } catch (e) {
                        console.warn('OptimizadorPro: Failed to execute delayed script', e);
                    }
                });

                // Clear the array
                delayedScripts = [];
            }

            // Initialize when DOM is ready
            function init() {
                collectDelayedScripts();

                if (delayedScripts.length === 0) {
                    return;
                }

                // Add event listeners for user interaction
                interactionEvents.forEach(function(event) {
                    document.addEventListener(event, executeDelayedScripts, { passive: true });
                });

                // Fallback: execute after 5 seconds regardless of interaction
                setTimeout(executeDelayedScripts, 5000);
            }

            // Start when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }

        })();
        </script>
        <?php
    }
}
