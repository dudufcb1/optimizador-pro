<?php

namespace OptimizadorPro\Common\Subscriber;

/**
 * WordPress Bloat Subscriber
 * 
 * Removes unnecessary WordPress features that can slow down the site
 * without affecting SEO or critical functionality
 */
class WordPressBloatSubscriber {

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
        // Only run on frontend and admin
        \add_action('init', [$this, 'init_optimizations'], 1);
        \add_action('wp_enqueue_scripts', [$this, 'frontend_optimizations'], 100);
        \add_action('wp_default_scripts', [$this, 'script_optimizations']);
    }

    /**
     * Initialize optimizations
     */
    public function init_optimizations(): void {
        // Safe optimizations (always recommended)
        $this->disable_emojis();
        $this->disable_heartbeat_frontend();
        
        // Optional optimizations (user configurable)
        if (\get_option('optimizador_pro_disable_xmlrpc', false)) {
            $this->disable_xmlrpc();
        }
        
        if (\get_option('optimizador_pro_disable_embeds', false)) {
            $this->disable_embeds();
        }
        
        if (\get_option('optimizador_pro_clean_head', false)) {
            $this->clean_wp_head();
        }
        
        if (\get_option('optimizador_pro_disable_feeds', false)) {
            $this->disable_feeds();
        }
        
        if (\get_option('optimizador_pro_disable_rest_api_links', false)) {
            $this->disable_rest_api_links();
        }
    }

    /**
     * Frontend script and style optimizations
     */
    public function frontend_optimizations(): void {
        // Disable Dashicons for non-logged users
        if (\get_option('optimizador_pro_disable_dashicons', false)) {
            $this->disable_dashicons_frontend();
        }
        
        // Disable Gutenberg block styles if not needed
        if (\get_option('optimizador_pro_disable_block_styles', false)) {
            $this->disable_gutenberg_styles();
        }
        
        // Disable WooCommerce assets on non-shop pages
        if (\get_option('optimizador_pro_disable_woocommerce_bloat', false)) {
            $this->disable_woocommerce_bloat();
        }
    }

    /**
     * Script optimizations
     */
    public function script_optimizations($scripts): void {
        // Remove jQuery Migrate
        if (\get_option('optimizador_pro_disable_jquery_migrate', false)) {
            $this->remove_jquery_migrate($scripts);
        }
    }

    /**
     * Disable WordPress emojis (Safe optimization)
     */
    private function disable_emojis(): void {
        // Remove emoji detection script and styles
        \remove_action('wp_head', 'print_emoji_detection_script', 7);
        \remove_action('admin_print_scripts', 'print_emoji_detection_script');
        \remove_action('wp_print_styles', 'print_emoji_styles');
        \remove_action('admin_print_styles', 'print_emoji_styles');
        \remove_filter('the_content_feed', 'wp_staticize_emoji');
        \remove_filter('comment_text_rss', 'wp_staticize_emoji');
        \remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        
        // Remove from TinyMCE
        \add_filter('tiny_mce_plugins', [$this, 'disable_emojis_tinymce']);
        \add_filter('wp_resource_hints', [$this, 'disable_emojis_dns_prefetch'], 10, 2);
    }

    /**
     * Disable emojis in TinyMCE
     */
    public function disable_emojis_tinymce($plugins): array {
        if (is_array($plugins)) {
            return array_diff($plugins, ['wpemoji']);
        }
        return [];
    }

    /**
     * Remove emoji DNS prefetch
     */
    public function disable_emojis_dns_prefetch($urls, $relation_type): array {
        if ('dns-prefetch' === $relation_type) {
            $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/');
            $urls = array_diff($urls, [$emoji_svg_url]);
        }
        return $urls;
    }

    /**
     * Disable Heartbeat on frontend (Safe optimization)
     */
    private function disable_heartbeat_frontend(): void {
        if (!\is_admin()) {
            \wp_deregister_script('heartbeat');
        }
    }

    /**
     * Disable Dashicons for non-logged users
     */
    private function disable_dashicons_frontend(): void {
        if (!\is_user_logged_in()) {
            \wp_deregister_style('dashicons');
        }
    }

    /**
     * Remove jQuery Migrate
     */
    private function remove_jquery_migrate($scripts): void {
        if (isset($scripts->registered['jquery'])) {
            $script = $scripts->registered['jquery'];
            if ($script->deps) {
                $script->deps = array_diff($script->deps, ['jquery-migrate']);
            }
        }
    }

    /**
     * Disable Gutenberg block styles
     */
    private function disable_gutenberg_styles(): void {
        \wp_dequeue_style('wp-block-library');
        \wp_dequeue_style('wp-block-library-theme');
        \wp_dequeue_style('global-styles');
        
        // Also remove from admin if not using block editor
        if (\is_admin()) {
            \wp_dequeue_style('wp-block-editor');
        }
    }

    /**
     * Disable WooCommerce bloat on non-shop pages
     */
    private function disable_woocommerce_bloat(): void {
        // Only run if WooCommerce is active
        if (!\class_exists('WooCommerce')) {
            return;
        }
        
        // Don't disable on WooCommerce pages
        if (\is_woocommerce() || \is_cart() || \is_checkout() || \is_account_page()) {
            return;
        }
        
        // Disable WooCommerce styles and scripts
        \wp_dequeue_style('woocommerce-layout');
        \wp_dequeue_style('woocommerce-smallscreen');
        \wp_dequeue_style('woocommerce-general');
        \wp_dequeue_script('wc-cart-fragments');
        \wp_dequeue_script('woocommerce');
        \wp_dequeue_script('wc-add-to-cart');
    }

    /**
     * Disable XML-RPC (Optional - affects mobile apps)
     */
    private function disable_xmlrpc(): void {
        \add_filter('xmlrpc_enabled', '__return_false');
        
        // Remove RSD link
        \remove_action('wp_head', 'rsd_link');
        
        // Remove XML-RPC pingback
        \add_filter('wp_headers', [$this, 'remove_xmlrpc_pingback_header']);
    }

    /**
     * Remove XML-RPC pingback header
     */
    public function remove_xmlrpc_pingback_header($headers): array {
        unset($headers['X-Pingback']);
        return $headers;
    }

    /**
     * Disable WordPress embeds
     */
    private function disable_embeds(): void {
        // Remove embed script
        \wp_dequeue_script('wp-embed');
        
        // Remove embed discovery links
        \remove_action('wp_head', 'wp_oembed_add_discovery_links');
        
        // Remove embed host JavaScript
        \remove_action('wp_head', 'wp_oembed_add_host_js');
        
        // Disable embed endpoint
        \add_filter('embed_oembed_discover', '__return_false');
        
        // Remove embed rewrite rules
        \add_filter('rewrite_rules_array', [$this, 'disable_embeds_rewrites']);
    }

    /**
     * Remove embed rewrite rules
     */
    public function disable_embeds_rewrites($rules): array {
        foreach ($rules as $rule => $rewrite) {
            if (false !== strpos($rewrite, 'embed=true')) {
                unset($rules[$rule]);
            }
        }
        return $rules;
    }

    /**
     * Clean WordPress head
     */
    private function clean_wp_head(): void {
        // Remove generator meta tag
        \remove_action('wp_head', 'wp_generator');
        
        // Remove Windows Live Writer manifest
        \remove_action('wp_head', 'wlwmanifest_link');
        
        // Remove shortlink
        \remove_action('wp_head', 'wp_shortlink_wp_head');
        
        // Remove adjacent posts links
        \remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
    }

    /**
     * Disable REST API links in head
     */
    private function disable_rest_api_links(): void {
        \remove_action('wp_head', 'rest_output_link_wp_head');
        \remove_action('wp_head', 'wp_oembed_add_discovery_links');
        \remove_action('template_redirect', 'rest_output_link_header', 11);
    }

    /**
     * Disable RSS feeds
     */
    private function disable_feeds(): void {
        \add_action('do_feed', [$this, 'disable_feed_redirect'], 1);
        \add_action('do_feed_rdf', [$this, 'disable_feed_redirect'], 1);
        \add_action('do_feed_rss', [$this, 'disable_feed_redirect'], 1);
        \add_action('do_feed_rss2', [$this, 'disable_feed_redirect'], 1);
        \add_action('do_feed_atom', [$this, 'disable_feed_redirect'], 1);
        \add_action('do_feed_rss2_comments', [$this, 'disable_feed_redirect'], 1);
        \add_action('do_feed_atom_comments', [$this, 'disable_feed_redirect'], 1);
        
        // Remove feed links from head
        \remove_action('wp_head', 'feed_links_extra', 3);
        \remove_action('wp_head', 'feed_links', 2);
    }

    /**
     * Redirect feed requests
     */
    public function disable_feed_redirect(): void {
        \wp_die(__('No feed available, please visit the <a href="' . \esc_url(\home_url('/')) . '">homepage</a>!'));
    }
}
