<?php

namespace Refynd\Plugin;

use Refynd\Container\Container;

/**
 * PluginInterface - Contract for Refynd Plugins
 *
 * Defines the structure for plugins that extend the framework's functionality.
 * Plugins are self-contained packages that can register services, routes, commands, etc.
 */
interface PluginInterface
{
    /**
     * Get the plugin identifier
     */
    public function getId(): string;

    /**
     * Get the plugin name
     */
    public function getName(): string;

    /**
     * Get the plugin version
     */
    public function getVersion(): string;

    /**
     * Get plugin description
     */
    public function getDescription(): string;

    /**
     * Get plugin dependencies (other plugin IDs)
     */
    public function getDependencies(): array;

    /**
     * Get required framework version
     */
    public function getRequiredFrameworkVersion(): string;

    /**
     * Register plugin services and bindings
     */
    public function register(Container $container): void;

    /**
     * Boot the plugin after all plugins are registered
     */
    public function boot(Container $container): void;

    /**
     * Install the plugin (run migrations, create directories, etc.)
     */
    public function install(): bool;

    /**
     * Uninstall the plugin (cleanup resources)
     */
    public function uninstall(): bool;

    /**
     * Check if plugin is compatible with current environment
     */
    public function isCompatible(): bool;

    /**
     * Get plugin configuration schema
     */
    public function getConfigSchema(): array;
}
