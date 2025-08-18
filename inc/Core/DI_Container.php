<?php

namespace OptimizadorPro\Core;

use League\Container\Container;
use League\Container\ReflectionContainer;

/**
 * Dependency Injection Container for OptimizadorPro
 * 
 * Wrapper around League Container to provide centralized dependency management
 */
class DI_Container {

    /**
     * Instance of League Container
     *
     * @var Container
     */
    private $container;

    /**
     * Constructor
     */
    public function __construct() {
        $this->container = new Container();
        
        // Enable auto-wiring for automatic dependency resolution
        $this->container->delegate(
            new ReflectionContainer()
        );
    }

    /**
     * Get the underlying League Container instance
     *
     * @return Container
     */
    public function get_container(): Container {
        return $this->container;
    }

    /**
     * Add a service to the container
     *
     * @param string $id Service identifier
     * @param mixed $concrete Service implementation
     * @return void
     */
    public function add(string $id, $concrete): void {
        $this->container->add($id, $concrete);
    }

    /**
     * Add a shared service to the container (singleton)
     *
     * @param string $id Service identifier
     * @param mixed $concrete Service implementation
     * @return void
     */
    public function add_shared(string $id, $concrete): void {
        $this->container->addShared($id, $concrete);
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return mixed
     */
    public function get(string $id) {
        return $this->container->get($id);
    }

    /**
     * Check if a service exists in the container
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool {
        return $this->container->has($id);
    }

    /**
     * Add a service provider to the container
     *
     * @param mixed $provider Service provider instance
     * @return void
     */
    public function add_service_provider($provider): void {
        $this->container->addServiceProvider($provider);
    }
}
