<?php

namespace Refynd\Modules;

use Refynd\Container\Container;

/**
 * Module - Base Class for Framework Modules
 * 
 * Self-contained service packages that register bindings,
 * configure services, and provide functionality to the framework.
 */
abstract class Module
{
    /**
     * Register services in the container
     * 
     * This method is called during the module registration phase.
     * Use this to bind classes, interfaces, and singletons to the container.
     */
    abstract public function register(Container $container): void;

    /**
     * Boot the module
     * 
     * This method is called after all modules have been registered.
     * Use this for any initialization that requires other services
     * to be available in the container.
     */
    abstract public function boot(): void;

    /**
     * Get module dependencies
     * 
     * Return an array of module class names that this module depends on.
     * Dependencies will be booted before this module.
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Get the module name
     */
    public function getName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Get the module version
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Get the module description
     */
    public function getDescription(): string
    {
        return 'A Refynd framework module';
    }

    /**
     * Determine if the module should be loaded
     */
    public function shouldLoad(): bool
    {
        return true;
    }
}
