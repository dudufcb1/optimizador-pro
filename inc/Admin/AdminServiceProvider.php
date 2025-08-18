<?php

namespace OptimizadorPro\Admin;

use League\Container\ServiceProvider\AbstractServiceProvider;
use OptimizadorPro\Common\Subscriber\AdminSubscriber;

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
    }
}
