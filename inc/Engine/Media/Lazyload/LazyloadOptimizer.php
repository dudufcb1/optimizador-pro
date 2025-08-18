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
        error_log("OptimizadorPro LazyLoad: Iniciando optimización LazyLoad");
        error_log("OptimizadorPro LazyLoad: HTML length: " . strlen($html));

        // Count images before processing
        $img_count = preg_match_all('/<img[^>]*>/i', $html);
        error_log("OptimizadorPro LazyLoad: Encontradas $img_count imágenes en el HTML");

        // Apply LazyLoad to images
        $html = $this->lazyload_images($html);

        // Apply LazyLoad to iframes
        $html = $this->lazyload_iframes($html);

        // Count lazy elements after processing
        $lazy_count = preg_match_all('/class="[^"]*lazyload[^"]*"/', $html);
        error_log("OptimizadorPro LazyLoad: Creados $lazy_count elementos lazy");

        // Enqueue console restore script if enabled
        $html = $this->enqueue_console_restore_script($html);

        // Enqueue LazyLoad script
        $html = $this->enqueue_lazyload_script($html);

        error_log("OptimizadorPro LazyLoad: Optimización completada");
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

            // Log para debug
            error_log("OptimizadorPro LazyLoad: Procesando imagen: " . $src);

            // Skip if excluded
            if ($this->is_excluded($src) || $this->is_excluded($matches[0])) {
                error_log("OptimizadorPro LazyLoad: Imagen excluida: " . $src);
                return $matches[0];
            }

            // Skip if already has data-src (already processed)
            if (strpos($matches[0], 'data-src') !== false) {
                error_log("OptimizadorPro LazyLoad: Imagen ya procesada: " . $src);
                return $matches[0];
            }

            // Skip if has loading="eager" or similar
            if (preg_match('/loading=["\']eager["\']/', $matches[0])) {
                error_log("OptimizadorPro LazyLoad: Imagen con loading=eager: " . $src);
                return $matches[0];
            }

            // Obtener dimensiones para evitar CLS
            $width = '';
            $height = '';
            if (preg_match('/width=["\'](\d+)["\']/', $matches[0], $width_match)) {
                $width = $width_match[1];
            }
            if (preg_match('/height=["\'](\d+)["\']/', $matches[0], $height_match)) {
                $height = $height_match[1];
            }

            // Si no hay dimensiones, intentar obtenerlas del archivo
            if (empty($width) || empty($height)) {
                $image_path = $this->url_to_path($src);
                if ($image_path && file_exists($image_path)) {
                    $dims = getimagesize($image_path);
                    if ($dims) {
                        $width = $dims[0];
                        $height = $dims[1];
                    }
                }
            }

            // Build new img tag
            $new_img = '<img' . $before_src;

            // Add lazyload class - improved logic
            if (preg_match('/class=["\']([^"\']*)["\']/', $before_src, $class_match)) {
                // Replace existing class attribute
                $existing_classes = trim($class_match[1]);
                $new_classes = $existing_classes ? $existing_classes . ' lazyload' : 'lazyload';
                $new_img = str_replace($class_match[0], 'class="' . $new_classes . '"', $new_img);
                error_log('OptimizadorPro LazyLoad: Agregando lazyload a clases existentes: ' . $existing_classes . ' -> ' . $new_classes);
            } else {
                // Add new class attribute
                $new_img .= ' class="lazyload"';
                error_log('OptimizadorPro LazyLoad: Agregando nueva clase lazyload');
            }

            // Replace src with data-src and add placeholder with proper dimensions
            $new_img .= ' src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 ' . ($width ?: '1') . ' ' . ($height ?: '1') . '\'%3E%3C/svg%3E"';
            $new_img .= ' data-src="' . $src . '"';

            // Añadir width y height si no estaban
            if ($width && !strpos($matches[0], 'width=')) {
                $new_img .= ' width="' . $width . '"';
            }
            if ($height && !strpos($matches[0], 'height=')) {
                $new_img .= ' height="' . $height . '"';
            }
            
            // Handle srcset if present
            if (preg_match('/srcset=["\']([^"\']+)["\']/', $matches[0], $srcset_match)) {
                $new_img .= ' data-srcset="' . $srcset_match[1] . '"';
                $after_src = str_replace($srcset_match[0], '', $after_src);
            }
            
            $new_img .= $after_src . '>';

            // Log del resultado final
            error_log("OptimizadorPro LazyLoad: Imagen transformada de: " . substr($matches[0], 0, 100) . "...");
            error_log("OptimizadorPro LazyLoad: Imagen transformada a: " . substr($new_img, 0, 100) . "...");

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
        // Script de LazyLoad con logging detallado para debug
        $script = '
<script id="optimizador-pro-lazyload-script">
(function() {
    console.log("🚀 OptimizadorPro LazyLoad: Script iniciado");
    console.log("📊 Document readyState:", document.readyState);

    function initLazyLoad() {
        console.log("🔍 OptimizadorPro LazyLoad: Buscando elementos lazy...");

        // Primero verificar cuántas imágenes hay en total
        const allImages = document.querySelectorAll("img");
        console.log("🖼️ Total de imágenes en la página:", allImages.length);

        const lazyElements = document.querySelectorAll(".lazyload");
        console.log("📋 OptimizadorPro LazyLoad: Encontrados", lazyElements.length, "elementos lazy");

        // Log de las primeras 3 imágenes para debug
        allImages.forEach((img, index) => {
            if (index < 3) {
                console.log("🖼️ Imagen", index + 1, ":", {
                    src: img.src,
                    classes: img.className,
                    hasDataSrc: !!img.dataset.src
                });
            }
        });

        // Log de cada elemento encontrado
        lazyElements.forEach(function(element, index) {
            console.log("🖼️ Elemento", index + 1, ":", {
                tagName: element.tagName,
                src: element.src,
                dataSrc: element.dataset.src,
                classes: element.className,
                width: element.width,
                height: element.height
            });
        });

        if (lazyElements.length === 0) {
            console.log("⚠️ OptimizadorPro LazyLoad: No se encontraron elementos lazy, saliendo");
            return;
        }

        if ("IntersectionObserver" in window) {
            console.log("✅ OptimizadorPro LazyLoad: IntersectionObserver disponible");

            const observer = new IntersectionObserver(function(entries) {
                console.log("👁️ OptimizadorPro LazyLoad: IntersectionObserver callback ejecutado con", entries.length, "entradas");

                entries.forEach(function(entry, index) {
                    console.log("🔍 Entrada", index + 1, ":", {
                        isIntersecting: entry.isIntersecting,
                        intersectionRatio: entry.intersectionRatio,
                        target: entry.target.tagName,
                        dataSrc: entry.target.dataset.src
                    });

                    if (entry.isIntersecting) {
                        const element = entry.target;
                        console.log("🎯 OptimizadorPro LazyLoad: Elemento visible, cargando:", element.dataset.src);

                        // Cargar la imagen/elemento
                        if (element.dataset.src) {
                            console.log("📥 Cambiando src de", element.src, "a", element.dataset.src);
                            element.src = element.dataset.src;
                        }
                        if (element.dataset.srcset) {
                            console.log("📥 Cambiando srcset a", element.dataset.srcset);
                            element.srcset = element.dataset.srcset;
                        }

                        // Limpiar
                        console.log("🧹 Removiendo clase lazyload y dejando de observar");
                        element.classList.remove("lazyload");
                        observer.unobserve(element);

                        console.log("✅ Elemento procesado completamente");
                    }
                });
            }, {
                rootMargin: "50px 0px",
                threshold: 0.01
            });

            console.log("👀 OptimizadorPro LazyLoad: Iniciando observación de", lazyElements.length, "elementos");
            lazyElements.forEach(function(element, index) {
                console.log("👁️ Observando elemento", index + 1);
                observer.observe(element);
            });
            console.log("✅ OptimizadorPro LazyLoad: Todos los elementos están siendo observados");

        } else {
            console.log("⚠️ OptimizadorPro LazyLoad: IntersectionObserver no disponible, usando fallback");
            // Fallback para navegadores antiguos
            lazyElements.forEach(function(element, index) {
                console.log("🔄 Fallback: Procesando elemento", index + 1);
                if (element.dataset.src) {
                    element.src = element.dataset.src;
                }
                if (element.dataset.srcset) {
                    element.srcset = element.dataset.srcset;
                }
                element.classList.remove("lazyload");
                console.log("✅ Fallback: Elemento", index + 1, "procesado");
            });
        }
    }

    // Ejecutar cuando el DOM esté listo
    if (document.readyState === "loading") {
        console.log("⏳ OptimizadorPro LazyLoad: DOM cargando, esperando DOMContentLoaded");
        document.addEventListener("DOMContentLoaded", function() {
            console.log("🎉 OptimizadorPro LazyLoad: DOMContentLoaded disparado");
            initLazyLoad();
        });
    } else {
        console.log("✅ OptimizadorPro LazyLoad: DOM ya listo, ejecutando inmediatamente");
        initLazyLoad();
    }
})();
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

    /**
     * Convert URL to file system path
     *
     * @param string $url URL to convert
     * @return string|false File path or false if not local
     */
    private function url_to_path(string $url) {
        $url = strtok($url, '?');
        $site_url = \site_url();
        if (strpos($url, $site_url) === 0) {
            return ABSPATH . substr($url, strlen($site_url));
        }
        if (strpos($url, '/') === 0) {
            return ABSPATH . ltrim($url, '/');
        }
        return false;
    }

    /**
     * Enqueue console restore script if enabled
     *
     * @param string $html HTML content
     * @return string Modified HTML
     */
    private function enqueue_console_restore_script(string $html): string {
        // Only inject if console restore is enabled
        if (!get_option('optimizador_pro_restore_console', false)) {
            return $html;
        }

        // Console restore script
        $script = '
<script id="optimizador-pro-console-restore">
(function() {
    "use strict";

    // Check if console has been overridden by other plugins
    var consoleOverridden = false;

    // Test if console.log is working
    try {
        var testConsole = console.log;
        if (typeof testConsole !== "function" || testConsole.toString().indexOf("silent") !== -1) {
            consoleOverridden = true;
        }
    } catch (e) {
        consoleOverridden = true;
    }

    if (consoleOverridden) {
        // Try to restore from global variables set by other plugins
        if (window.especialistaWpChatOriginalConsole) {
            console.log = window.especialistaWpChatOriginalConsole.log;
            console.warn = window.especialistaWpChatOriginalConsole.warn;
            console.error = window.especialistaWpChatOriginalConsole.error;
            console.info = window.especialistaWpChatOriginalConsole.info;
            console.debug = window.especialistaWpChatOriginalConsole.debug;

            console.log("🔧 OptimizadorPro: Console restored from especialistaWpChat backup");
        } else {
            // Fallback: create basic console functions
            var iframe = document.createElement("iframe");
            iframe.style.display = "none";
            document.body.appendChild(iframe);

            if (iframe.contentWindow && iframe.contentWindow.console) {
                console.log = iframe.contentWindow.console.log.bind(iframe.contentWindow.console);
                console.warn = iframe.contentWindow.console.warn.bind(iframe.contentWindow.console);
                console.error = iframe.contentWindow.console.error.bind(iframe.contentWindow.console);
                console.info = iframe.contentWindow.console.info.bind(iframe.contentWindow.console);
                console.debug = iframe.contentWindow.console.debug.bind(iframe.contentWindow.console);

                document.body.removeChild(iframe);
                console.log("🔧 OptimizadorPro: Console restored using iframe fallback");
            }
        }
    }

    // Mark console as restored
    window.optimizadorProConsoleRestored = true;
})();
</script>';

        // Add script before LazyLoad script (high priority)
        return str_replace('</head>', $script . "\n</head>", $html);
    }
}
