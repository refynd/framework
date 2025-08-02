<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use RuntimeException;

/**
 * ModuleManager - Orchestrates Module Registration and Booting
 *
 * Manages the registration and booting of all framework modules,
 * ensuring proper initialization order and dependency resolution.
 */
class ModuleManager
{
    protected Container $container;
    protected array $modules = [];
    protected array $booted = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a module with the framework
     */
    public function register(string $moduleClass): void
    {
        if (!class_exists($moduleClass)) {
            throw new RuntimeException("Module class [{$moduleClass}] does not exist");
        }

        if (!is_subclass_of($moduleClass, Module::class)) {
            throw new RuntimeException("Module [{$moduleClass}] must extend " . Module::class);
        }

        if (isset($this->modules[$moduleClass])) {
            return; // Already registered
        }

        $module = new $moduleClass();
        $this->modules[$moduleClass] = $module;

        // Register the module with the container
        $module->register($this->container);
    }

    /**
     * Boot all registered modules
     */
    public function bootAll(): void
    {
        foreach ($this->modules as $moduleClass => $module) {
            $this->bootModule($moduleClass);
        }
    }

    /**
     * Boot a specific module
     */
    public function bootModule(string $moduleClass): void
    {
        if (isset($this->booted[$moduleClass])) {
            return; // Already booted
        }

        if (!isset($this->modules[$moduleClass])) {
            throw new RuntimeException("Module [{$moduleClass}] is not registered");
        }

        $module = $this->modules[$moduleClass];

        // Boot dependencies first
        $this->bootDependencies($module);

        // Boot the module
        $module->boot();

        $this->booted[$moduleClass] = true;
    }

    /**
     * Boot module dependencies
     */
    protected function bootDependencies(Module $module): void
    {
        $dependencies = $module->getDependencies();

        foreach ($dependencies as $dependency) {
            if (!isset($this->modules[$dependency])) {
                throw new RuntimeException("Module dependency [{$dependency}] is not registered");
            }

            $this->bootModule($dependency);
        }
    }

    /**
     * Get all registered modules
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    /**
     * Get a specific module
     */
    public function getModule(string $moduleClass): ?Module
    {
        return $this->modules[$moduleClass] ?? null;
    }

    /**
     * Check if a module is registered
     */
    public function hasModule(string $moduleClass): bool
    {
        return isset($this->modules[$moduleClass]);
    }

    /**
     * Check if a module is booted
     */
    public function isBooted(string $moduleClass): bool
    {
        return isset($this->booted[$moduleClass]);
    }
}
