<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Plugin\PluginManager;

/**
 * PluginModule - Plugin System Module
 *
 * Integrates the plugin system into the Refynd framework,
 * enabling dynamic extension of functionality through plugins.
 */
class PluginModule extends Module
{
    protected Container $container;

    /**
     * Register plugin services
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        // Register plugin manager
        $container->singleton(PluginManager::class, function (Container $container) {
            return new PluginManager($container);
        });

        // Register convenient alias
        $container->bind('plugins', PluginManager::class);
    }

    /**
     * Boot the plugin system
     */
    public function boot(): void
    {
        $pluginManager = $this->container->make(PluginManager::class);

        // Discover and register plugins
        $discovered = $pluginManager->discover();

        foreach ($discovered as $pluginInfo) {
            if ($pluginInfo['class'] && class_exists($pluginInfo['class'])) {
                try {
                    $pluginManager->registerClass($pluginInfo['class']);
                } catch (\Exception $e) {
                    // Log plugin registration failure but don't break the app
                    error_log("Failed to register plugin {$pluginInfo['name']}: " . $e->getMessage());
                }
            }
        }

        // Boot all registered plugins
        $pluginManager->bootAll();
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return ['container'];
    }

    /**
     * Get module name
     */
    public function getName(): string
    {
        return 'Plugin System';
    }

    /**
     * Get module description
     */
    public function getDescription(): string
    {
        return 'Dynamic plugin system for extending framework functionality';
    }
}
