<?php

namespace OptimizadorPro\Engine\Optimization;

use League\Container\ServiceProvider\AbstractServiceProvider;
use OptimizadorPro\Engine\Optimization\CSS\CSSOptimizer;
use OptimizadorPro\Engine\Optimization\JS\JSOptimizer;
use OptimizadorPro\Engine\Optimization\DeferJS\DeferJSOptimizer;
use OptimizadorPro\Common\Subscriber\OptimizationSubscriber;
use OptimizadorPro\Common\Subscriber\DeferJSSubscriber;
use OptimizadorPro\Common\Subscriber\CriticalCSSSubscriber;
use OptimizadorPro\Common\Subscriber\DelayJSExecutionSubscriber;
use OptimizadorPro\Common\Subscriber\GoogleFontsSubscriber;

/**
 * Optimization Service Provider
 * 
 * Registers all optimization-related services in the container:
 * - CSS Optimizer (minification and combination)
 * - JS Optimizer (minification and combination)
 * - Optimization Subscriber (connects to WordPress hooks)
 */
class OptimizationServiceProvider extends AbstractServiceProvider {

    /**
     * Services provided by this provider
     *
     * @var array<string>
     */
    protected $provides = [
        'css_optimizer',
        'js_optimizer',
        'defer_js_optimizer',
        'optimization_subscriber',
        'defer_js_subscriber',
        'critical_css_subscriber',
        'delay_js_execution_subscriber',
        'google_fonts_subscriber',
    ];

    /**
     * Check if service provider provides a specific service
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function provides(string $id): bool {
        return in_array($id, $this->provides);
    }

    /**
     * Register services in the container
     */
    public function register(): void {
        // Define cache directory and plugin URL
        $cache_dir = WP_CONTENT_DIR . '/cache/optimizador-pro/';
        $plugin_url = plugin_dir_url(OPTIMIZADOR_PRO_PLUGIN_FILE);

        // Register CSS Optimizer
        $this->getContainer()->add('css_optimizer', CSSOptimizer::class)
            ->addArgument($cache_dir)
            ->addArgument($plugin_url);

        // Register JS Optimizer
        $this->getContainer()->add('js_optimizer', JSOptimizer::class)
            ->addArgument($cache_dir)
            ->addArgument($plugin_url);

        // Register Defer JS Optimizer
        $this->getContainer()->add('defer_js_optimizer', DeferJSOptimizer::class);

        // Register Optimization Subscriber
        $this->getContainer()->add('optimization_subscriber', OptimizationSubscriber::class)
            ->addArgument($this->getContainer()->get('css_optimizer'))
            ->addArgument($this->getContainer()->get('js_optimizer'));

        // Register Defer JS Subscriber
        $this->getContainer()->add('defer_js_subscriber', DeferJSSubscriber::class)
            ->addArgument($this->getContainer()->get('defer_js_optimizer'));

        // Register Critical CSS Subscriber
        $this->getContainer()->add('critical_css_subscriber', CriticalCSSSubscriber::class);

        // Register Delay JS Execution Subscriber
        $this->getContainer()->add('delay_js_execution_subscriber', DelayJSExecutionSubscriber::class);

        // Register Google Fonts Subscriber
        $this->getContainer()->add('google_fonts_subscriber', GoogleFontsSubscriber::class);
    }
}
