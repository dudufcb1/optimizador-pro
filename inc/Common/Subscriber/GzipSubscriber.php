<?php

namespace OptimizadorPro\Common\Subscriber;

/**
 * GZIP Compression Subscriber
 * 
 * Manages GZIP compression rules in .htaccess for Apache/LiteSpeed servers
 * For Nginx servers, provides instructions for manual configuration
 */
class GzipSubscriber {

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
        // Update .htaccess when GZIP option changes
        \add_action('update_option_optimizador_pro_enable_gzip', [$this, 'handle_gzip_option_change'], 10, 2);
        
        // Add GZIP rules when plugin is activated
        \add_action('optimizador_pro_activated', [$this, 'maybe_add_gzip_rules']);
        
        // Remove GZIP rules when plugin is deactivated
        \add_action('optimizador_pro_deactivated', [$this, 'remove_gzip_rules']);
    }

    /**
     * Handle GZIP option change
     * 
     * @param mixed $old_value Old option value
     * @param mixed $new_value New option value
     */
    public function handle_gzip_option_change($old_value, $new_value): void {
        if ($new_value) {
            $this->add_gzip_rules();
        } else {
            $this->remove_gzip_rules();
        }
    }

    /**
     * Maybe add GZIP rules if option is enabled
     */
    public function maybe_add_gzip_rules(): void {
        if (\get_option('optimizador_pro_enable_gzip', false)) {
            $this->add_gzip_rules();
        }
    }

    /**
     * Add GZIP compression rules to .htaccess
     * 
     * @return bool True on success, false on failure
     */
    public function add_gzip_rules(): bool {
        // Only works on Apache/LiteSpeed servers
        if (!$this->is_apache_server()) {
            return false;
        }

        $htaccess_path = $this->get_htaccess_path();
        if (!$htaccess_path) {
            return false;
        }

        // Get current .htaccess content
        $htaccess_content = $this->get_htaccess_content($htaccess_path);
        
        // Remove existing OptimizadorPro rules first
        $htaccess_content = $this->remove_optimizador_pro_rules($htaccess_content);
        
        // Add new GZIP rules
        $gzip_rules = $this->get_gzip_rules();
        $new_content = $this->add_optimizador_pro_rules($htaccess_content, $gzip_rules);
        
        // Write back to .htaccess
        return $this->write_htaccess($htaccess_path, $new_content);
    }

    /**
     * Remove GZIP compression rules from .htaccess
     * 
     * @return bool True on success, false on failure
     */
    public function remove_gzip_rules(): bool {
        $htaccess_path = $this->get_htaccess_path();
        if (!$htaccess_path) {
            return false;
        }

        // Get current .htaccess content
        $htaccess_content = $this->get_htaccess_content($htaccess_path);
        
        // Remove OptimizadorPro rules
        $new_content = $this->remove_optimizador_pro_rules($htaccess_content);
        
        // Write back to .htaccess
        return $this->write_htaccess($htaccess_path, $new_content);
    }

    /**
     * Check if server is Apache or LiteSpeed
     * 
     * @return bool
     */
    private function is_apache_server(): bool {
        global $is_apache;
        
        // Check global WordPress variable
        if (isset($is_apache) && $is_apache) {
            return true;
        }
        
        // Check server software
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        
        return (
            \stripos($server_software, 'apache') !== false ||
            \stripos($server_software, 'litespeed') !== false
        );
    }

    /**
     * Get .htaccess file path
     * 
     * @return string|false Path to .htaccess or false if not available
     */
    private function get_htaccess_path() {
        if (!\function_exists('get_home_path')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        
        $home_path = \get_home_path();
        $htaccess_path = $home_path . '.htaccess';
        
        // Check if .htaccess is writable
        if (\file_exists($htaccess_path) && !\is_writable($htaccess_path)) {
            return false;
        }
        
        // Check if directory is writable (for creating .htaccess)
        if (!\file_exists($htaccess_path) && !\is_writable($home_path)) {
            return false;
        }
        
        return $htaccess_path;
    }

    /**
     * Get current .htaccess content
     * 
     * @param string $htaccess_path Path to .htaccess file
     * @return string Current content or empty string
     */
    private function get_htaccess_content(string $htaccess_path): string {
        if (!\file_exists($htaccess_path)) {
            return '';
        }
        
        $content = \file_get_contents($htaccess_path);
        return $content !== false ? $content : '';
    }

    /**
     * Write content to .htaccess file
     * 
     * @param string $htaccess_path Path to .htaccess file
     * @param string $content Content to write
     * @return bool True on success, false on failure
     */
    private function write_htaccess(string $htaccess_path, string $content): bool {
        return \file_put_contents($htaccess_path, $content, LOCK_EX) !== false;
    }

    /**
     * Get GZIP compression rules
     * 
     * @return string GZIP rules for .htaccess
     */
    private function get_gzip_rules(): string {
        $rules = '# GZIP Compression' . PHP_EOL;
        $rules .= '<IfModule mod_deflate.c>' . PHP_EOL;
        $rules .= '    # Active compression' . PHP_EOL;
        $rules .= '    SetOutputFilter DEFLATE' . PHP_EOL;
        $rules .= '    # Force deflate for mangled headers' . PHP_EOL;
        $rules .= '    <IfModule mod_setenvif.c>' . PHP_EOL;
        $rules .= '        <IfModule mod_headers.c>' . PHP_EOL;
        $rules .= '            SetEnvIfNoCase ^(Accept-EncodXng|X-cept-Encoding|X{15}|~{15}|-{15})$ ^((gzip|deflate)\s*,?\s*)+|[X~-]{4,13}$ HAVE_Accept-Encoding' . PHP_EOL;
        $rules .= '            RequestHeader append Accept-Encoding "gzip,deflate" env=HAVE_Accept-Encoding' . PHP_EOL;
        $rules .= '            # Don\'t compress images and other uncompressible content' . PHP_EOL;
        $rules .= '            SetEnvIfNoCase Request_URI \\' . PHP_EOL;
        $rules .= '            \\.(?:gif|jpe?g|png|rar|zip|exe|flv|mov|wma|mp3|avi|swf|mp?g|mp4|webm|webp|pdf)$ no-gzip dont-vary' . PHP_EOL;
        $rules .= '        </IfModule>' . PHP_EOL;
        $rules .= '    </IfModule>' . PHP_EOL . PHP_EOL;
        $rules .= '    # Compress all output labeled with one of the following MIME-types' . PHP_EOL;
        $rules .= '    <IfModule mod_filter.c>' . PHP_EOL;
        $rules .= '        AddOutputFilterByType DEFLATE application/atom+xml \\' . PHP_EOL;
        $rules .= '                              application/javascript \\' . PHP_EOL;
        $rules .= '                              application/json \\' . PHP_EOL;
        $rules .= '                              application/rss+xml \\' . PHP_EOL;
        $rules .= '                              application/vnd.ms-fontobject \\' . PHP_EOL;
        $rules .= '                              application/x-font-ttf \\' . PHP_EOL;
        $rules .= '                              application/xhtml+xml \\' . PHP_EOL;
        $rules .= '                              application/xml \\' . PHP_EOL;
        $rules .= '                              font/opentype \\' . PHP_EOL;
        $rules .= '                              image/svg+xml \\' . PHP_EOL;
        $rules .= '                              image/x-icon \\' . PHP_EOL;
        $rules .= '                              text/css \\' . PHP_EOL;
        $rules .= '                              text/html \\' . PHP_EOL;
        $rules .= '                              text/plain \\' . PHP_EOL;
        $rules .= '                              text/x-component \\' . PHP_EOL;
        $rules .= '                              text/xml' . PHP_EOL;
        $rules .= '    </IfModule>' . PHP_EOL;
        $rules .= '    <IfModule mod_headers.c>' . PHP_EOL;
        $rules .= '        Header append Vary: Accept-Encoding' . PHP_EOL;
        $rules .= '    </IfModule>' . PHP_EOL;
        $rules .= '</IfModule>' . PHP_EOL;
        
        return $rules;
    }

    /**
     * Add OptimizadorPro rules to .htaccess content
     * 
     * @param string $content Current .htaccess content
     * @param string $rules Rules to add
     * @return string Modified content
     */
    private function add_optimizador_pro_rules(string $content, string $rules): string {
        $marker_start = '# BEGIN OptimizadorPro';
        $marker_end = '# END OptimizadorPro';
        
        $optimizador_rules = $marker_start . PHP_EOL;
        $optimizador_rules .= $rules . PHP_EOL;
        $optimizador_rules .= $marker_end . PHP_EOL . PHP_EOL;
        
        // Add at the beginning of the file
        return $optimizador_rules . $content;
    }

    /**
     * Remove OptimizadorPro rules from .htaccess content
     * 
     * @param string $content Current .htaccess content
     * @return string Content without OptimizadorPro rules
     */
    private function remove_optimizador_pro_rules(string $content): string {
        $marker_start = '# BEGIN OptimizadorPro';
        $marker_end = '# END OptimizadorPro';
        
        // Pattern to match everything between markers (including markers)
        $pattern = '/^' . preg_quote($marker_start, '/') . '.*?' . preg_quote($marker_end, '/') . '\s*$/ms';
        
        return \preg_replace($pattern, '', $content);
    }

    /**
     * Get server type for display purposes
     * 
     * @return string Server type
     */
    public function get_server_type(): string {
        if ($this->is_apache_server()) {
            return 'Apache/LiteSpeed';
        }
        
        $server_software = $_SERVER['SERVER_SOFTWARE'] ?? '';
        
        if (\stripos($server_software, 'nginx') !== false) {
            return 'Nginx';
        }
        
        return 'Unknown';
    }

    /**
     * Check if GZIP is supported by the server
     * 
     * @return bool
     */
    public function is_gzip_supported(): bool {
        return \function_exists('gzencode') && $this->is_apache_server();
    }

    /**
     * Get Nginx configuration instructions
     * 
     * @return string Nginx configuration
     */
    public function get_nginx_instructions(): string {
        return '# Add this to your Nginx server block:
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_proxied expired no-cache no-store private auth;
gzip_types
    text/plain
    text/css
    text/xml
    text/javascript
    application/javascript
    application/xml+rss
    application/atom+xml
    image/svg+xml;';
    }
}
