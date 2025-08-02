<?php

namespace Refynd\Plugin;

use Refynd\Container\Container;

/**
 * Plugin - Base class for Refynd Plugins
 *
 * Provides common functionality for plugins with sensible defaults.
 * Plugins can extend this class for basic functionality.
 */
abstract class Plugin implements PluginInterface
{
    protected Container $container;
    protected array $config = [];
    protected bool $booted = false;
    protected bool $registered = false;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Get the plugin identifier (default: lowercase class name)
     */
    public function getId(): string
    {
        return strtolower(class_basename(static::class));
    }

    /**
     * Get plugin description (default: empty)
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * Get plugin dependencies (default: none)
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Get required framework version (default: current version)
     */
    public function getRequiredFrameworkVersion(): string
    {
        return '^2.1';
    }

    /**
     * Default registration (override in concrete plugins)
     */
    public function register(Container $container): void
    {
        $this->registered = true;
    }

    /**
     * Default boot (override in concrete plugins)
     */
    public function boot(Container $container): void
    {
        $this->booted = true;
    }

    /**
     * Default install (override if needed)
     */
    public function install(): bool
    {
        return true;
    }

    /**
     * Default uninstall (override if needed)
     */
    public function uninstall(): bool
    {
        return true;
    }

    /**
     * Default compatibility check
     */
    public function isCompatible(): bool
    {
        // Check PHP version
        if (PHP_VERSION_ID < 80400) {
            return false;
        }

        // Check required extensions
        foreach ($this->getRequiredExtensions() as $extension) {
            if (!extension_loaded($extension)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get plugin configuration schema (default: empty)
     */
    public function getConfigSchema(): array
    {
        return [];
    }

    /**
     * Get required PHP extensions
     */
    protected function getRequiredExtensions(): array
    {
        return [];
    }

    /**
     * Get plugin configuration
     */
    protected function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    /**
     * Set plugin configuration
     */
    protected function setConfig(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    /**
     * Check if plugin is booted
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Check if plugin is registered
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }
}
