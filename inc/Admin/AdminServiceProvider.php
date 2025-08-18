<?php

namespace OptimizadorPro\Admin;

use League\Container\ServiceProvider\AbstractServiceProvider;
use OptimizadorPro\Common\Subscriber\AdminSubscriber;
use OptimizadorPro\Common\Subscriber\CriticalCSSSubscriber;
use OptimizadorPro\Common\Subscriber\DelayJSExecutionSubscriber;
use OptimizadorPro\Common\Subscriber\GoogleFontsSubscriber;
use OptimizadorPro\Common\Subscriber\GzipSubscriber;

/**
 * Admin Service Provider
 *
 * Registers admin-related services:
 * - Settings page
 * - Admin UI components
 * - Settings API integration
 */
class AdminServiceProvider extends AbstractServiceProvider {

    /**
     * Services provided by this provider
     *
     * @var array<string>
     */
    protected $provides = [
        'admin_subscriber',
        'critical_css_subscriber',
        'delay_js_execution_subscriber',
        'google_fonts_subscriber',
        'gzip_subscriber',
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
        // Register admin subscriber
        $this->getContainer()->add('admin_subscriber', AdminSubscriber::class)
            ->addArgument('plugin_version')
            ->addArgument('plugin_url');

        // Register critical CSS subscriber
        $this->getContainer()->add('critical_css_subscriber', CriticalCSSSubscriber::class);

        // Register delay JS execution subscriber
        $this->getContainer()->add('delay_js_execution_subscriber', DelayJSExecutionSubscriber::class);

        // Register Google Fonts subscriber
        $this->getContainer()->add('google_fonts_subscriber', GoogleFontsSubscriber::class);

        // Register GZIP subscriber
        $this->getContainer()->add('gzip_subscriber', GzipSubscriber::class);
    }
}
