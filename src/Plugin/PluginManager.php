<?php

namespace Refynd\Plugin;

use Refynd\Container\Container;
use RuntimeException;
use InvalidArgumentException;

/**
 * PluginManager - Manages plugin lifecycle and dependencies
 *
 * Handles plugin discovery, registration, dependency resolution,
 * and lifecycle management for the Refynd framework.
 */
class PluginManager
{
    protected Container $container;
    protected array $plugins = [];
    protected array $registeredPlugins = [];
    protected array $bootedPlugins = [];
    protected array $dependencyGraph = [];
    protected array $pluginPaths = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->pluginPaths = ['vendor' => getcwd() . '/vendor',
            'plugins' => getcwd() . '/plugins',
            'app/Plugins' => getcwd() . '/app/Plugins',];
    }

    /**
     * Register a plugin instance
     */
    public function register(PluginInterface $plugin): void
    {
        $id = $plugin->getId();

        if (isset($this->plugins[$id])) {
            throw new InvalidArgumentException("Plugin '{$id}' is already registered");
        }

        // Check compatibility
        if (!$plugin->isCompatible()) {
            throw new RuntimeException("Plugin '{$id}' is not compatible with current environment");
        }

        // Store plugin
        $this->plugins[$id] = $plugin;
        $this->dependencyGraph[$id] = $plugin->getDependencies();

        // Register with container
        $plugin->register($this->container);
        $this->registeredPlugins[$id] = true;
    }

    /**
     * Register a plugin by class name
     */
    public function registerClass(string $pluginClass): void
    {
        if (!class_exists($pluginClass)) {
            throw new InvalidArgumentException("Plugin class '{$pluginClass}' does not exist");
        }

        if (!is_subclass_of($pluginClass, PluginInterface::class)) {
            throw new InvalidArgumentException("Plugin class '{$pluginClass}' must implement PluginInterface");
        }

        $plugin = new $pluginClass($this->container);
        $this->register($plugin);
    }

    /**
     * Discover and register plugins from configured paths
     */
    public function discover(): array
    {
        $discovered = [];

        foreach ($this->pluginPaths as $name => $path) {
            if (!is_dir($path)) {
                continue;
            }

            $plugins = $this->discoverInPath($path);
            $discovered = array_merge($discovered, $plugins);
        }

        return $discovered;
    }

    /**
     * Boot all registered plugins in dependency order
     */
    public function bootAll(): void
    {
        $bootOrder = $this->resolveDependencyOrder();

        foreach ($bootOrder as $pluginId) {
            $this->bootPlugin($pluginId);
        }
    }

    /**
     * Boot a specific plugin
     */
    public function bootPlugin(string $pluginId): void
    {
        if (isset($this->bootedPlugins[$pluginId])) {
            return; // Already booted
        }

        if (!isset($this->plugins[$pluginId])) {
            throw new RuntimeException("Plugin '{$pluginId}' is not registered");
        }

        $plugin = $this->plugins[$pluginId];

        // Boot dependencies first
        foreach ($plugin->getDependencies() as $dependency) {
            $this->bootPlugin($dependency);
        }

        // Boot the plugin
        $plugin->boot($this->container);
        $this->bootedPlugins[$pluginId] = true;
    }

    /**
     * Get a registered plugin
     */
    public function getPlugin(string $pluginId): ?PluginInterface
    {
        return $this->plugins[$pluginId] ?? null;
    }

    /**
     * Get all registered plugins
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Check if a plugin is registered
     */
    public function hasPlugin(string $pluginId): bool
    {
        return isset($this->plugins[$pluginId]);
    }

    /**
     * Check if a plugin is booted
     */
    public function isPluginBooted(string $pluginId): bool
    {
        return isset($this->bootedPlugins[$pluginId]);
    }

    /**
     * Install a plugin
     */
    public function installPlugin(string $pluginId): bool
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new RuntimeException("Plugin '{$pluginId}' is not registered");
        }

        return $this->plugins[$pluginId]->install();
    }

    /**
     * Uninstall a plugin
     */
    public function uninstallPlugin(string $pluginId): bool
    {
        if (!isset($this->plugins[$pluginId])) {
            throw new RuntimeException("Plugin '{$pluginId}' is not registered");
        }

        return $this->plugins[$pluginId]->uninstall();
    }

    /**
     * Add a plugin discovery path
     */
    public function addPath(string $name, string $path): void
    {
        $this->pluginPaths[$name] = $path;
    }

    /**
     * Get plugin statistics
     */
    public function getStats(): array
    {
        return ['total_plugins' => count($this->plugins),
            'registered_plugins' => count($this->registeredPlugins),
            'booted_plugins' => count($this->bootedPlugins),
            'discovery_paths' => count($this->pluginPaths),];
    }

    /**
     * Discover plugins in a specific path
     */
    protected function discoverInPath(string $path): array
    {
        $discovered = [];

        // Look for composer.json files with plugin configuration
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'composer.json') {
                $plugin = $this->parseComposerPlugin($file->getPathname());
                if ($plugin) {
                    $discovered[] = $plugin;
                }
            }
        }

        return $discovered;
    }

    /**
     * Parse a composer.json file for plugin information
     */
    protected function parseComposerPlugin(string $composerPath): ?array
    {
        $composer = json_decode(file_get_contents($composerPath), true);

        if (!isset($composer['extra']['refynd-plugin'])) {
            return null;
        }

        $pluginConfig = $composer['extra']['refynd-plugin'];

        return ['name' => $composer['name'] ?? 'unknown',
            'class' => $pluginConfig['class'] ?? null,
            'path' => dirname($composerPath),
            'config' => $pluginConfig,];
    }

    /**
     * Resolve plugin dependency order
     */
    protected function resolveDependencyOrder(): array
    {
        $resolved = [];
        $resolving = [];

        foreach (array_keys($this->plugins) as $pluginId) {
            $this->resolveDependencies($pluginId, $resolved, $resolving);
        }

        return $resolved;
    }

    /**
     * Recursively resolve dependencies for a plugin
     */
    protected function resolveDependencies(string $pluginId, array &$resolved, array &$resolving): void
    {
        if (in_array($pluginId, $resolved)) {
            return; // Already resolved
        }

        if (in_array($pluginId, $resolving)) {
            throw new RuntimeException("Circular dependency detected for plugin '{$pluginId}'");
        }

        $resolving[] = $pluginId;

        // Resolve dependencies first
        $dependencies = $this->dependencyGraph[$pluginId] ?? [];
        foreach ($dependencies as $dependency) {
            if (!isset($this->plugins[$dependency])) {
                throw new RuntimeException("Plugin '{$pluginId}' depends on '{$dependency}' which is not registered");
            }
            $this->resolveDependencies($dependency, $resolved, $resolving);
        }

        // Remove from resolving and add to resolved
        $resolving = array_filter($resolving, fn ($id) => $id !== $pluginId);
        $resolved[] = $pluginId;
    }
}
