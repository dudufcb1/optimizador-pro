<?php

namespace OptimizadorPro\Engine\Media\Lazyload;

/**
 * LazyLoad Optimizer
 * 
 * Pure logic class for LazyLoad optimization:
 * - Finds images and iframes in HTML
 * - Replaces src with data-src
 * - Adds lazyload class
 * - Handles exclusions
 */
class LazyloadOptimizer {

    /**
     * Plugin URL for serving assets
     *
     * @var string
     */
    private $plugin_url;

    /**
     * Elements to exclude from lazyload
     *
     * @var array
     */
    private $excluded_elements = [];

    /**
     * Constructor
     *
     * @param string $plugin_url Plugin URL
     */
    public function __construct(string $plugin_url) {
        $this->plugin_url = $plugin_url;
        
        // Load exclusions from WordPress options
        $this->load_exclusions();
    }

    /**
     * Process HTML and apply LazyLoad
     *
     * @param string $html HTML content
     * @return string Optimized HTML
     */
    public function optimize(string $html): string {
        // Apply LazyLoad to images
        $html = $this->lazyload_images($html);
        
        // Apply LazyLoad to iframes
        $html = $this->lazyload_iframes($html);
        
        // Enqueue LazyLoad script
        $html = $this->enqueue_lazyload_script($html);
        
        return $html;
    }

    /**
     * Apply LazyLoad to images
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function lazyload_images(string $html): string {
        // Regex to find img tags
        $pattern = '/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $before_src = $matches[1];
            $src = $matches[2];
            $after_src = $matches[3];
            
            // Skip if excluded
            if ($this->is_excluded($src) || $this->is_excluded($matches[0])) {
                return $matches[0];
            }
            
            // Skip if already has data-src (already processed)
            if (strpos($matches[0], 'data-src') !== false) {
                return $matches[0];
            }
            
            // Skip if has loading="eager" or similar
            if (preg_match('/loading=["\']eager["\']/', $matches[0])) {
                return $matches[0];
            }
            
            // Build new img tag
            $new_img = '<img' . $before_src;
            
            // Add lazyload class
            if (strpos($matches[0], 'class=') !== false) {
                $new_img = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 lazyload"', $new_img);
            } else {
                $new_img .= ' class="lazyload"';
            }
            
            // Replace src with data-src and add placeholder
            $new_img .= ' src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 1 1\'%3E%3C/svg%3E"';
            $new_img .= ' data-src="' . $src . '"';
            
            // Handle srcset if present
            if (preg_match('/srcset=["\']([^"\']+)["\']/', $matches[0], $srcset_match)) {
                $new_img .= ' data-srcset="' . $srcset_match[1] . '"';
                $after_src = str_replace($srcset_match[0], '', $after_src);
            }
            
            $new_img .= $after_src . '>';
            
            return $new_img;
        }, $html);
    }

    /**
     * Apply LazyLoad to iframes
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function lazyload_iframes(string $html): string {
        // Regex to find iframe tags
        $pattern = '/<iframe([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i';
        
        return preg_replace_callback($pattern, function($matches) {
            $before_src = $matches[1];
            $src = $matches[2];
            $after_src = $matches[3];
            
            // Skip if excluded
            if ($this->is_excluded($src) || $this->is_excluded($matches[0])) {
                return $matches[0];
            }
            
            // Skip if already has data-src
            if (strpos($matches[0], 'data-src') !== false) {
                return $matches[0];
            }
            
            // Build new iframe tag
            $new_iframe = '<iframe' . $before_src;
            
            // Add lazyload class
            if (strpos($matches[0], 'class=') !== false) {
                $new_iframe = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 lazyload"', $new_iframe);
            } else {
                $new_iframe .= ' class="lazyload"';
            }
            
            // Replace src with data-src
            $new_iframe .= ' data-src="' . $src . '"';
            $new_iframe .= $after_src . '>';
            
            return $new_iframe;
        }, $html);
    }

    /**
     * Enqueue LazyLoad script in footer
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function enqueue_lazyload_script(string $html): string {
        // Simple LazyLoad script
        $script = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    if ("IntersectionObserver" in window) {
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    let lazyElement = entry.target;
                    if (lazyElement.dataset.src) {
                        lazyElement.src = lazyElement.dataset.src;
                    }
                    if (lazyElement.dataset.srcset) {
                        lazyElement.srcset = lazyElement.dataset.srcset;
                    }
                    lazyElement.classList.remove("lazyload");
                    lazyImageObserver.unobserve(lazyElement);
                }
            });
        });

        document.querySelectorAll(".lazyload").forEach(function(lazyElement) {
            lazyImageObserver.observe(lazyElement);
        });
    } else {
        // Fallback for older browsers
        document.querySelectorAll(".lazyload").forEach(function(lazyElement) {
            if (lazyElement.dataset.src) {
                lazyElement.src = lazyElement.dataset.src;
            }
            if (lazyElement.dataset.srcset) {
                lazyElement.srcset = lazyElement.dataset.srcset;
            }
            lazyElement.classList.remove("lazyload");
        });
    }
});
</script>';
        
        // Add script before closing body tag
        return str_replace('</body>', $script . "\n</body>", $html);
    }

    /**
     * Check if element is excluded
     *
     * @param string $element Element src or full tag
     * @return bool
     */
    private function is_excluded(string $element): bool {
        foreach ($this->excluded_elements as $excluded) {
            if (strpos($element, $excluded) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Load exclusions from WordPress options
     */
    private function load_exclusions(): void {
        $exclusions = get_option('optimizador_pro_lazyload_exclusions', '');
        if (!empty($exclusions)) {
            $this->excluded_elements = array_map('trim', explode("\n", $exclusions));
        }
        
        // Add default exclusions
        $default_exclusions = [
            'data-skip-lazy',
            'skip-lazy',
            'no-lazy',
        ];
        
        $this->excluded_elements = array_merge($this->excluded_elements, $default_exclusions);
    }
}
