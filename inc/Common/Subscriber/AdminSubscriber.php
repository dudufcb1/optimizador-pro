<?php

namespace OptimizadorPro\Common\Subscriber;

/**
 * Admin Subscriber
 * 
 * This class connects to WordPress admin hooks and manages the settings page.
 * It keeps WordPress admin logic separate from business logic.
 */
class AdminSubscriber {

    /**
     * Plugin version
     *
     * @var string
     */
    private $plugin_version;

    /**
     * Plugin URL
     *
     * @var string
     */
    private $plugin_url;

    /**
     * Constructor
     *
     * @param string $plugin_version Plugin version
     * @param string $plugin_url Plugin URL
     */
    public function __construct(string $plugin_version, string $plugin_url) {
        $this->plugin_version = $plugin_version;
        $this->plugin_url = $plugin_url;
        
        // Register WordPress hooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks(): void {
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(OPTIMIZADOR_PRO_PLUGIN_FILE), [$this, 'add_settings_link']);
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Add admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // Handle cache clearing
        add_action('wp_ajax_optimizador_pro_clear_cache', [$this, 'handle_clear_cache']);
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void {
        add_options_page(
            'OptimizadorPro Settings',
            'OptimizadorPro',
            'manage_options',
            'optimizador-pro',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings using WordPress Settings API
     */
    public function register_settings(): void {
        // Register setting groups
        register_setting('optimizador_pro_css', 'optimizador_pro_minify_css');
        register_setting('optimizador_pro_css', 'optimizador_pro_combine_inline_css');
        register_setting('optimizador_pro_css', 'optimizador_pro_optimize_google_fonts');
        register_setting('optimizador_pro_css', 'optimizador_pro_css_exclusions');
        register_setting('optimizador_pro_css', 'optimizador_pro_critical_css');
        register_setting('optimizador_pro_css', 'optimizador_pro_google_fonts_exclusions');
        
        register_setting('optimizador_pro_js', 'optimizador_pro_minify_js');
        register_setting('optimizador_pro_js', 'optimizador_pro_js_exclusions');
        register_setting('optimizador_pro_js', 'optimizador_pro_defer_js');
        register_setting('optimizador_pro_js', 'optimizador_pro_defer_js_exclusions');
        register_setting('optimizador_pro_js', 'optimizador_pro_delay_js');
        register_setting('optimizador_pro_js', 'optimizador_pro_delay_js_exclusions');
        register_setting('optimizador_pro_js', 'optimizador_pro_dequeue_jquery');
        
        register_setting('optimizador_pro_media', 'optimizador_pro_lazyload_enabled');
        register_setting('optimizador_pro_media', 'optimizador_pro_lazyload_exclusions');
        register_setting('optimizador_pro_media', 'optimizador_pro_lazyload_excluded_pages');
        register_setting('optimizador_pro_media', 'optimizador_pro_lazyload_logged_users');
        
        register_setting('optimizador_pro_general', 'optimizador_pro_excluded_pages');
        register_setting('optimizador_pro_general', 'optimizador_pro_optimize_logged_users');
        register_setting('optimizador_pro_general', 'optimizador_pro_defer_logged_users');
        register_setting('optimizador_pro_general', 'optimizador_pro_enable_gzip');
        register_setting('optimizador_pro_general', 'optimizador_pro_restore_console');

        // Add settings sections
        add_settings_section(
            'optimizador_pro_css_section',
            'CSS Optimization',
            [$this, 'render_css_section_description'],
            'optimizador_pro_css'
        );

        add_settings_section(
            'optimizador_pro_js_section',
            'JavaScript Optimization',
            [$this, 'render_js_section_description'],
            'optimizador_pro_js'
        );

        add_settings_section(
            'optimizador_pro_media_section',
            'Media Optimization',
            [$this, 'render_media_section_description'],
            'optimizador_pro_media'
        );

        add_settings_section(
            'optimizador_pro_general_section',
            'General Settings',
            [$this, 'render_general_section_description'],
            'optimizador_pro_general'
        );

        // Add settings fields
        $this->add_settings_fields();
    }

    /**
     * Add all settings fields
     */
    private function add_settings_fields(): void {
        // CSS Settings
        add_settings_field(
            'optimizador_pro_minify_css',
            'Minify & Combine CSS',
            [$this, 'render_checkbox_field'],
            'optimizador_pro_css',
            'optimizador_pro_css_section',
            [
                'option_name' => 'optimizador_pro_minify_css',
                'description' => 'Minify and combine CSS files to reduce HTTP requests and file sizes.'
            ]
        );

        add_settings_field(
            'optimizador_pro_css_exclusions',
            'CSS Exclusions',
            [$this, 'render_textarea_field'],
            'optimizador_pro_css',
            'optimizador_pro_css_section',
            [
                'option_name' => 'optimizador_pro_css_exclusions',
                'description' => 'Enter CSS files to exclude from optimization (one per line). Use partial matches.',
                'placeholder' => "admin-bar\nwp-admin\ncustomize-controls"
            ]
        );

        // JS Settings
        add_settings_field(
            'optimizador_pro_minify_js',
            'Minify & Combine JS',
            [$this, 'render_checkbox_field'],
            'optimizador_pro_js',
            'optimizador_pro_js_section',
            [
                'option_name' => 'optimizador_pro_minify_js',
                'description' => 'Minify and combine JavaScript files to reduce HTTP requests and file sizes.'
            ]
        );

        add_settings_field(
            'optimizador_pro_defer_js',
            'Defer JavaScript',
            [$this, 'render_checkbox_field'],
            'optimizador_pro_js',
            'optimizador_pro_js_section',
            [
                'option_name' => 'optimizador_pro_defer_js',
                'description' => 'Add defer attribute to JavaScript files to prevent render blocking.'
            ]
        );

        add_settings_field(
            'optimizador_pro_dequeue_jquery',
            'Smart jQuery Dequeue',
            [$this, 'render_checkbox_field'],
            'optimizador_pro_js',
            'optimizador_pro_js_section',
            [
                'option_name' => 'optimizador_pro_dequeue_jquery',
                'description' => '<strong>Advanced:</strong> Allow jQuery to be dequeued when safe. Automatically detects jQuery usage.',
                'class' => 'advanced-option'
            ]
        );

        add_settings_field(
            'optimizador_pro_js_exclusions',
            'JS Exclusions',
            [$this, 'render_textarea_field'],
            'optimizador_pro_js',
            'optimizador_pro_js_section',
            [
                'option_name' => 'optimizador_pro_js_exclusions',
                'description' => 'Enter JavaScript files to exclude from optimization (one per line).',
                'placeholder' => "jquery\nadmin-bar\ncustomize-controls"
            ]
        );

        add_settings_field(
            'optimizador_pro_defer_js_exclusions',
            'Defer JS Exclusions',
            [$this, 'render_textarea_field'],
            'optimizador_pro_js',
            'optimizador_pro_js_section',
            [
                'option_name' => 'optimizador_pro_defer_js_exclusions',
                'description' => 'Enter JavaScript files to exclude from defer (one per line).',
                'placeholder' => "critical-script\ninline-script"
            ]
        );

        // Media Settings
        add_settings_field(
            'optimizador_pro_lazyload_enabled',
            'Enable LazyLoad',
            [$this, 'render_checkbox_field'],
            'optimizador_pro_media',
            'optimizador_pro_media_section',
            [
                'option_name' => 'optimizador_pro_lazyload_enabled',
                'description' => 'Enable lazy loading for images and iframes to improve page load speed.'
            ]
        );

        add_settings_field(
            'optimizador_pro_lazyload_exclusions',
            'LazyLoad Exclusions',
            [$this, 'render_textarea_field'],
            'optimizador_pro_media',
            'optimizador_pro_media_section',
            [
                'option_name' => 'optimizador_pro_lazyload_exclusions',
                'description' => 'Enter images/iframes to exclude from lazy loading (one per line).',
                'placeholder' => "logo.png\nhero-image\nno-lazy"
            ]
        );

        // General Settings
        add_settings_field(
            'optimizador_pro_excluded_pages',
            'Excluded Pages',
            [$this, 'render_textarea_field'],
            'optimizador_pro_general',
            'optimizador_pro_general_section',
            [
                'option_name' => 'optimizador_pro_excluded_pages',
                'description' => 'Enter URLs or URL patterns to exclude from all optimizations (one per line).',
                'placeholder' => "/admin\n/wp-login\n/checkout"
            ]
        );

        add_settings_field(
            'optimizador_pro_optimize_logged_users',
            'Optimize for Logged Users',
            [$this, 'render_checkbox_field'],
            'optimizador_pro_general',
            'optimizador_pro_general_section',
            [
                'option_name' => 'optimizador_pro_optimize_logged_users',
                'description' => 'Apply optimizations for logged-in users (useful for testing).'
            ]
        );

        add_settings_field(
            'optimizador_pro_restore_console',
            'Restore Console Debug',
            [$this, 'render_checkbox_field'],
            'optimizador_pro_general',
            'optimizador_pro_general_section',
            [
                'option_name' => 'optimizador_pro_restore_console',
                'description' => '<strong>Debug Tool:</strong> Restore browser console.log when suppressed by other plugins. Enable this to see OptimizadorPro debug messages.',
                'class' => 'debug-option'
            ]
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle form submission
        if (isset($_POST['submit'])) {
            $this->handle_settings_save();
        }

        // Handle cache clear
        if (isset($_POST['clear_cache'])) {
            $this->clear_cache();
            add_settings_error('optimizador_pro_messages', 'cache_cleared', 'Cache cleared successfully!', 'updated');
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('optimizador_pro_messages'); ?>

            <div class="nav-tab-wrapper">
                <a href="#css" class="nav-tab nav-tab-active" data-tab="css">CSS</a>
                <a href="#js" class="nav-tab" data-tab="js">JavaScript</a>
                <a href="#media" class="nav-tab" data-tab="media">Media</a>
                <a href="#general" class="nav-tab" data-tab="general">General</a>
                <a href="#tools" class="nav-tab" data-tab="tools">Tools</a>
            </div>

            <form action="" method="post">
                <?php wp_nonce_field('optimizador_pro_settings'); ?>
                <div id="css-tab" class="tab-content active">
                    <h2>CSS Optimization</h2>
                    <?php $this->render_css_settings(); ?>
                </div>

                <div id="js-tab" class="tab-content">
                    <h2>JavaScript Optimization</h2>
                    <?php $this->render_js_settings(); ?>
                </div>

                <div id="media-tab" class="tab-content">
                    <h2>Media Optimization</h2>
                    <?php $this->render_media_settings(); ?>
                </div>

                <div id="general-tab" class="tab-content">
                    <h2>General Settings</h2>
                    <?php $this->render_general_settings(); ?>
                </div>

                <div id="tools-tab" class="tab-content">
                    <h2>Tools</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Clear Cache</th>
                            <td>
                                <button type="submit" name="clear_cache" class="button button-secondary">
                                    Clear All Cache
                                </button>
                                <p class="description">Clear all cached CSS and JS files.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Cache Status</th>
                            <td>
                                <?php echo $this->get_cache_status(); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render checkbox field
     */
    public function render_checkbox_field(array $args): void {
        $option_name = $args['option_name'];
        $description = $args['description'] ?? '';
        $class = $args['class'] ?? '';
        $disabled = $args['disabled'] ?? false;

        $value = \get_option($option_name, false);

        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($option_name) . '" value="1" ' . checked(1, $value, false) . ' class="' . esc_attr($class) . '"' . ($disabled ? ' disabled' : '') . ' />';
        echo ' ' . $description;
        echo '</label>';
    }

    /**
     * Render textarea field
     */
    public function render_textarea_field(array $args): void {
        $option_name = $args['option_name'];
        $description = $args['description'] ?? '';
        $placeholder = $args['placeholder'] ?? '';

        $value = \get_option($option_name, '');

        echo '<textarea name="' . esc_attr($option_name) . '" rows="5" cols="50" class="large-text" placeholder="' . esc_attr($placeholder) . '">';
        echo esc_textarea($value);
        echo '</textarea>';

        if ($description) {
            echo '<p class="description">' . $description . '</p>';
        }
    }

    /**
     * Section descriptions
     */
    public function render_css_section_description(): void {
        echo '<p>Configure CSS optimization settings to improve page load speed.</p>';
    }

    public function render_js_section_description(): void {
        echo '<p>Configure JavaScript optimization settings. Be careful with advanced options.</p>';
    }

    public function render_media_section_description(): void {
        echo '<p>Configure media optimization settings for images and iframes.</p>';
    }

    public function render_general_section_description(): void {
        echo '<p>General plugin settings and exclusions.</p>';
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link(array $links): array {
        $settings_link = '<a href="' . admin_url('options-general.php?page=optimizador-pro') . '">Settings</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'settings_page_optimizador-pro') {
            return;
        }

        // Enqueue admin CSS and JS
        wp_enqueue_style(
            'optimizador-pro-admin',
            $this->plugin_url . 'assets/css/admin.css',
            [],
            $this->plugin_version
        );

        wp_enqueue_script(
            'optimizador-pro-admin',
            $this->plugin_url . 'assets/js/admin.js',
            ['jquery'],
            $this->plugin_version,
            true
        );
    }

    /**
     * Handle settings save
     */
    private function handle_settings_save(): void {
        // Verify nonce for security
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'optimizador_pro_settings')) {
            add_settings_error('optimizador_pro_messages', 'nonce_failed', 'Security check failed!', 'error');
            return;
        }

        // Define all our options
        $options = [
            // CSS Options
            'optimizador_pro_minify_css' => 'checkbox',
            'optimizador_pro_combine_inline_css' => 'checkbox',
            'optimizador_pro_optimize_google_fonts' => 'checkbox',
            'optimizador_pro_css_exclusions' => 'textarea',
            'optimizador_pro_critical_css' => 'textarea',
            'optimizador_pro_google_fonts_exclusions' => 'textarea',

            // JS Options
            'optimizador_pro_minify_js' => 'checkbox',
            'optimizador_pro_defer_js' => 'checkbox',
            'optimizador_pro_delay_js' => 'checkbox',
            'optimizador_pro_dequeue_jquery' => 'checkbox',
            'optimizador_pro_js_exclusions' => 'textarea',
            'optimizador_pro_defer_js_exclusions' => 'textarea',
            'optimizador_pro_delay_js_exclusions' => 'textarea',

            // Media Options
            'optimizador_pro_lazyload_enabled' => 'checkbox',
            'optimizador_pro_lazyload_exclusions' => 'textarea',
            'optimizador_pro_lazyload_excluded_pages' => 'textarea',
            'optimizador_pro_lazyload_logged_users' => 'checkbox',

            // General Options
            'optimizador_pro_excluded_pages' => 'textarea',
            'optimizador_pro_enable_gzip' => 'checkbox',
            'optimizador_pro_optimize_logged_users' => 'checkbox',
            'optimizador_pro_defer_logged_users' => 'checkbox',
        ];

        // Process each option
        foreach ($options as $option_name => $type) {
            if ($type === 'checkbox') {
                // Checkboxes: 1 if checked, 0 if not
                $value = isset($_POST[$option_name]) ? 1 : 0;
                \update_option($option_name, $value);
            } elseif ($type === 'textarea') {
                // Textareas: sanitize and save
                $value = isset($_POST[$option_name]) ? sanitize_textarea_field($_POST[$option_name]) : '';
                \update_option($option_name, $value);
            }
        }

        add_settings_error('optimizador_pro_messages', 'settings_saved', 'Settings saved successfully!', 'updated');
    }

    /**
     * Render CSS settings manually
     */
    private function render_css_settings(): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Minify & Combine CSS</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_minify_css', 'description' => 'Minify and combine CSS files to reduce HTTP requests and file sizes.']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Combine Inline Styles</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_combine_inline_css', 'description' => '<strong>⚠️ Experimental:</strong> Extract CSS from &lt;style&gt; tags in the head and add it to the combined CSS file. This cleans up the HTML but <strong>may affect your design</strong>. Enable and check visually that everything still works correctly.', 'class' => 'experimental-option']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Optimize Google Fonts</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_optimize_google_fonts', 'description' => 'Combine multiple Google Fonts requests into one, add preconnect hints, and optimize loading with font-display: swap.']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">CSS Exclusions</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_css_exclusions', 'description' => 'Enter CSS files to exclude from optimization (one per line). Use partial matches.', 'placeholder' => "admin-bar\nwp-admin\ncustomize-controls"]); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Critical CSS (Manual)</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_critical_css', 'description' => '<strong>Advanced:</strong> Paste your critical CSS here. This will be inserted inline in the &lt;head&gt; for optimal visual loading. The rest of the CSS will be loaded asynchronously. <br><br><strong>How to generate:</strong> Use tools like <a href="https://www.sitelocity.com/critical-path-css-generator" target="_blank">Critical Path CSS Generator</a> or <a href="https://jonassebastianohlsson.com/criticalpathcssgenerator/" target="_blank">Critical CSS Generator</a>.', 'placeholder' => "/* Paste your critical CSS here */\nbody { margin: 0; }\n.header { background: #fff; }\n/* Only include CSS for above-the-fold content */"]); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Google Fonts Exclusions</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_google_fonts_exclusions', 'description' => 'Enter Google Fonts URLs or font names to exclude from optimization (one per line).', 'placeholder' => "Open Sans\nRoboto\nfonts.googleapis.com/css?family=Custom"]); ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render JS settings manually
     */
    private function render_js_settings(): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Minify & Combine JS</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_minify_js', 'description' => 'Minify and combine JavaScript files to reduce HTTP requests and file sizes.']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Defer JavaScript</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_defer_js', 'description' => 'Add defer attribute to JavaScript files to prevent render blocking.']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Delay JavaScript Execution</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_delay_js', 'description' => '<strong>Advanced:</strong> Delay JavaScript execution until user interaction (click, scroll, keydown). This can significantly improve initial page load speed.', 'class' => 'advanced-option']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Smart jQuery Dequeue</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_dequeue_jquery', 'description' => '<strong>Advanced:</strong> Allow jQuery to be dequeued when safe. Automatically detects jQuery usage.', 'class' => 'advanced-option']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">JS Exclusions</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_js_exclusions', 'description' => 'Enter JavaScript files to exclude from optimization (one per line).', 'placeholder' => "jquery\nadmin-bar\ncustomize-controls"]); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Defer JS Exclusions</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_defer_js_exclusions', 'description' => 'Enter JavaScript files to exclude from defer (one per line).', 'placeholder' => "critical-script\ninline-script"]); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Delay JS Exclusions</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_delay_js_exclusions', 'description' => 'Enter JavaScript files to exclude from delay execution (one per line). These scripts will load normally.', 'placeholder' => "critical-script\nanalytics\ngtag"]); ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render Media settings manually
     */
    private function render_media_settings(): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Enable LazyLoad</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_lazyload_enabled', 'description' => 'Enable lazy loading for images and iframes to improve page load speed.']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">LazyLoad Exclusions</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_lazyload_exclusions', 'description' => 'Enter images to exclude from lazy loading (one per line). Use partial matches.', 'placeholder' => "logo.png\nhero-image\nno-lazy"]); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">LazyLoad for Logged Users</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_lazyload_logged_users', 'description' => 'Enable lazy loading for logged-in users.']); ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render General settings manually
     */
    private function render_general_settings(): void {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">Excluded Pages</th>
                <td>
                    <?php $this->render_textarea_field(['option_name' => 'optimizador_pro_excluded_pages', 'description' => 'Enter URLs or URL patterns to exclude from all optimizations (one per line).', 'placeholder' => "/admin\n/wp-login\n/checkout"]); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Optimize for Logged Users</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_optimize_logged_users', 'description' => 'Enable optimizations for logged-in users.']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Defer JS for Logged Users</th>
                <td>
                    <?php $this->render_checkbox_field(['option_name' => 'optimizador_pro_defer_logged_users', 'description' => 'Enable JavaScript defer for logged-in users.']); ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Enable GZIP Compression</th>
                <td>
                    <?php
                    $gzip_subscriber = new \OptimizadorPro\Common\Subscriber\GzipSubscriber();
                    $server_type = $gzip_subscriber->get_server_type();
                    $is_supported = $gzip_subscriber->is_gzip_supported();

                    if ($is_supported) {
                        $description = 'Add GZIP compression rules to your .htaccess file to reduce file sizes and improve loading speed. <strong>Server:</strong> ' . $server_type;
                    } else {
                        $description = '<strong>⚠️ Not supported:</strong> Your server (' . $server_type . ') doesn\'t support automatic GZIP configuration.';
                        if ($server_type === 'Nginx') {
                            $description .= '<br><br><strong>Manual Configuration Required:</strong><br><pre style="background:#f1f1f1;padding:10px;font-size:12px;">' . $gzip_subscriber->get_nginx_instructions() . '</pre>';
                        }
                    }

                    $this->render_checkbox_field([
                        'option_name' => 'optimizador_pro_enable_gzip',
                        'description' => $description,
                        'disabled' => !$is_supported
                    ]);
                    ?>
                </td>
            </tr>
            <tr>
                <th scope="row">Restore Console Debug</th>
                <td>
                    <?php $this->render_checkbox_field([
                        'option_name' => 'optimizador_pro_restore_console',
                        'description' => '<strong>🔧 Debug Tool:</strong> Restore browser console.log when suppressed by other plugins. Enable this to see OptimizadorPro debug messages in browser console.',
                        'class' => 'debug-option'
                    ]); ?>
                    <p class="description" style="color: #666; font-style: italic;">
                        💡 <strong>Tip:</strong> If you don\'t see OptimizadorPro logs in browser console, enable this option and refresh the page.
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Clear cache
     */
    private function clear_cache(): void {
        $cache_dir = WP_CONTENT_DIR . '/cache/optimizador-pro/';
        if (is_dir($cache_dir)) {
            $this->delete_directory_contents($cache_dir);
        }
    }

    /**
     * Delete directory contents recursively
     */
    private function delete_directory_contents(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->delete_directory_contents($file);
                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }

    /**
     * Get cache status
     */
    private function get_cache_status(): string {
        $cache_dir = WP_CONTENT_DIR . '/cache/optimizador-pro/';

        if (!is_dir($cache_dir)) {
            return '<span style="color: #666;">No cache directory found</span>';
        }

        $css_files = glob($cache_dir . 'css/*.css');
        $js_files = glob($cache_dir . 'js/*.js');

        $css_count = $css_files ? count($css_files) : 0;
        $js_count = $js_files ? count($js_files) : 0;

        $total_size = 0;
        foreach (array_merge($css_files ?: [], $js_files ?: []) as $file) {
            $total_size += filesize($file);
        }

        $size_formatted = size_format($total_size);

        return sprintf(
            '<span style="color: #0073aa;">%d CSS files, %d JS files (%s total)</span>',
            $css_count,
            $js_count,
            $size_formatted
        );
    }

    /**
     * Handle AJAX cache clear
     */
    public function handle_clear_cache(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_ajax_referer('optimizador_pro_nonce');

        $this->clear_cache();

        wp_send_json_success(['message' => 'Cache cleared successfully!']);
    }

    /**
     * Admin notices
     */
    public function admin_notices(): void {
        // Check if cache directory is writable
        $cache_dir = WP_CONTENT_DIR . '/cache/optimizador-pro/';
        if (!is_writable(dirname($cache_dir))) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>OptimizadorPro:</strong> Cache directory is not writable. ';
            echo 'Please check permissions for: ' . esc_html($cache_dir);
            echo '</p></div>';
        }
    }
}
