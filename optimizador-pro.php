<?php
/**
 * Plugin Name: OptimizadorPro
 * Plugin URI: https://github.com/dudufcb1/optimizador-pro
 * Description: Comprehensive WordPress performance optimization plugin with CSS/JS minification, critical CSS, lazy loading, and advanced caching system.
 * Version: 1.0.0
 * Author: Luis Eduardo G. González
 * Author URI: https://especialistaenwp.com/especialista-en-wordpress-chat
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: optimizador-pro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OPTIMIZADOR_PRO_VERSION', '1.0.0');
define('OPTIMIZADOR_PRO_PLUGIN_FILE', __FILE__);
define('OPTIMIZADOR_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OPTIMIZADOR_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OPTIMIZADOR_PRO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require Composer autoloader
if (!file_exists(OPTIMIZADOR_PRO_PLUGIN_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>OptimizadorPro:</strong> Las dependencias de Composer no están instaladas. ';
        echo 'Por favor ejecuta <code>composer install</code> en el directorio del plugin.';
        echo '</p></div>';
    });
    return;
}

require_once OPTIMIZADOR_PRO_PLUGIN_DIR . 'vendor/autoload.php';

use OptimizadorPro\Core\Plugin;

/**
 * Initialize OptimizadorPro
 *
 * Following WP Rocket pattern - load everything on plugins_loaded
 */
function optimizador_pro_init(): void {
    // Nothing to do if autosave
    if (defined('DOING_AUTOSAVE')) {
        return;
    }

    // Check minimum requirements
    if (!optimizador_pro_check_requirements()) {
        return;
    }

    // Create and load plugin instance
    $plugin = new OptimizadorPro\Core\Plugin(
        OPTIMIZADOR_PRO_VERSION,
        OPTIMIZADOR_PRO_PLUGIN_DIR
    );

    // Load the plugin
    $plugin->load();
}
add_action('plugins_loaded', 'optimizador_pro_init');

/**
 * Check minimum requirements
 *
 * @return bool
 */
function optimizador_pro_check_requirements(): bool {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>OptimizadorPro:</strong> Requiere PHP 7.4 o superior. ';
            echo 'Versión actual: ' . PHP_VERSION;
            echo '</p></div>';
        });
        return false;
    }

    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, '5.0', '<')) {
        add_action('admin_notices', function() {
            global $wp_version;
            echo '<div class="notice notice-error"><p>';
            echo '<strong>OptimizadorPro:</strong> Requiere WordPress 5.0 o superior. ';
            echo 'Versión actual: ' . $wp_version;
            echo '</p></div>';
        });
        return false;
    }

    return true;
}

/**
 * Plugin activation
 */
function optimizador_pro_activate(): void {
    // Create cache directory
    $cache_dir = WP_CONTENT_DIR . '/cache/optimizador-pro/';
    if (!is_dir($cache_dir)) {
        wp_mkdir_p($cache_dir);
        wp_mkdir_p($cache_dir . 'css/');
        wp_mkdir_p($cache_dir . 'js/');
    }

    // Set default options
    add_option('optimizador_pro_minify_css', false);
    add_option('optimizador_pro_minify_js', false);
    add_option('optimizador_pro_lazyload_enabled', false);
    add_option('optimizador_pro_defer_js', false);
    add_option('optimizador_pro_dequeue_jquery', false);
    add_option('optimizador_pro_css_exclusions', '');
    add_option('optimizador_pro_js_exclusions', '');
    add_option('optimizador_pro_lazyload_exclusions', '');
    add_option('optimizador_pro_defer_js_exclusions', '');
    add_option('optimizador_pro_excluded_pages', '');
    add_option('optimizador_pro_lazyload_excluded_pages', '');
    add_option('optimizador_pro_optimize_logged_users', false);
    add_option('optimizador_pro_lazyload_logged_users', false);
    add_option('optimizador_pro_defer_logged_users', false);
    add_option('optimizador_pro_critical_css', '');
    add_option('optimizador_pro_restore_console', false);

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'optimizador_pro_activate');

/**
 * Plugin deactivation
 */
function optimizador_pro_deactivate(): void {
    // Clean up cache
    optimizador_pro_clear_cache();

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'optimizador_pro_deactivate');

/**
 * Clear plugin cache
 */
function optimizador_pro_clear_cache(): void {
    $cache_dir = WP_CONTENT_DIR . '/cache/optimizador-pro/';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}

