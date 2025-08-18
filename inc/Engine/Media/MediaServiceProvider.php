<?php

namespace OptimizadorPro\Engine\Media;

use League\Container\ServiceProvider\AbstractServiceProvider;
use OptimizadorPro\Engine\Media\Lazyload\LazyloadOptimizer;
use OptimizadorPro\Common\Subscriber\LazyloadSubscriber;

/**
 * Media Service Provider
 *
 * Registers media optimization services:
 * - LazyLoad for images and iframes
 * - Future: WebP conversion, image optimization, etc.
 */
class MediaServiceProvider extends AbstractServiceProvider {

    /**
     * Services provided by this provider
     *
     * @var array<string>
     */
    protected $provides = [
        'lazyload_optimizer',
        'media_subscriber',
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
        // Register LazyLoad Optimizer
        $this->getContainer()->add('lazyload_optimizer', LazyloadOptimizer::class)
            ->addArgument('plugin_url');

        // Register Media Subscriber (LazyLoad)
        $this->getContainer()->add('media_subscriber', LazyloadSubscriber::class)
            ->addArgument('lazyload_optimizer');
    }
}
