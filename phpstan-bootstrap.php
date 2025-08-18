<?php
/**
 * PHPStan Bootstrap File
 * Define WordPress functions and constants for static analysis
 */

// WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

// WordPress functions stubs
if (!function_exists('add_action')) {
    function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool { return true; }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool { return true; }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = null): string { return ''; }
}

if (!function_exists('add_options_page')) {
    function add_options_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback = null): string { return ''; }
}

if (!function_exists('add_settings_error')) {
    function add_settings_error(string $setting, string $code, string $message, string $type = 'error'): void {}
}

if (!function_exists('add_option')) {
    function add_option(string $option, $value = '', string $deprecated = '', string $autoload = 'yes'): bool { return true; }
}

if (!function_exists('add_settings_field')) {
    function add_settings_field(string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = []): void {}
}

if (!function_exists('add_settings_section')) {
    function add_settings_section(string $id, string $title, callable $callback, string $page): void {}
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string { return 'http://example.com/wp-admin/' . $path; }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, bool $echo = true): string { return $checked == $current ? 'checked="checked"' : ''; }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool { return true; }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections(string $page): void {}
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('get_admin_page_title')) {
    function get_admin_page_title(): string { return 'Admin Page'; }
}

if (!function_exists('get_option')) {
    function get_option(string $option, $default = false) { return $default; }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool { return false; }
}

if (!function_exists('is_customize_preview')) {
    function is_customize_preview(): bool { return false; }
}

if (!function_exists('is_feed')) {
    function is_feed(): bool { return false; }
}

if (!function_exists('is_preview')) {
    function is_preview(): bool { return false; }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool { return false; }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string { return basename(dirname($file)) . '/' . basename($file); }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string { return 'http://example.com/wp-content/plugins/' . basename(dirname($file)) . '/'; }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void {}
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void {}
}

if (!function_exists('register_setting')) {
    function register_setting(string $option_group, string $option_name, array $args = []): void {}
}

if (!function_exists('settings_errors')) {
    function settings_errors(string $setting = '', bool $sanitize = false, bool $hide_on_update = false): void {}
}

if (!function_exists('settings_fields')) {
    function settings_fields(string $option_group): void {}
}

if (!function_exists('site_url')) {
    function site_url(string $path = '', string $scheme = null): string { return 'http://example.com' . $path; }
}

if (!function_exists('size_format')) {
    function size_format(int $bytes, int $decimals = 0): string { return $bytes . ' bytes'; }
}

if (!function_exists('submit_button')) {
    function submit_button(string $text = null, string $type = 'primary', string $name = 'submit', bool $wrap = true, array $other_attributes = []): void {}
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax(): bool { return false; }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], $ver = false, bool $in_footer = false): void {}
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = false, string $media = 'all'): void {}
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $target): bool { return mkdir($target, 0755, true); }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, int $status_code = null): void {}
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, string $query_arg = '_ajax_nonce', bool $die = true): bool { return true; }
}

if (!function_exists('wp_die')) {
    function wp_die(string $message = '', string $title = '', array $args = []): void { die($message); }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(bool $hard = true): void {}
}

if (!function_exists('error_log')) {
    function error_log(string $message, int $message_type = 0, string $destination = null, string $extra_headers = null): bool { return true; }
}

// Global variables
global $wp_version;
$wp_version = '6.4.0';

// Superglobals for PHPStan
$_SERVER = [];
$_POST = [];
$_GET = [];
$_REQUEST = [];
$_SESSION = [];
$_COOKIE = [];
$_FILES = [];
