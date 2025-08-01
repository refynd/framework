<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Prism\PrismEngine;
use Refynd\Prism\PrismView;

/**
 * PrismModule - Provides Template Engine Services
 * 
 * Registers the Prism template engine and view services
 * with the application container.
 */
class PrismModule extends Module
{
    public function register(Container $container): void
    {
        // Register Prism Engine as singleton
        $container->singleton(PrismEngine::class, function($container) {
            $profile = $container->make(\Refynd\Config\AppProfile::class);
            
            $viewPath = $profile->path('views');
            $cachePath = $profile->storagePath('cache/views');
            
            $engine = new PrismEngine($viewPath, $cachePath);
            
            // Add global variables
            $engine->addGlobals([
                'appName' => env('APP_NAME', 'Refynd Application'),
                'version' => '1.0.0-alpha',
                'debug' => env('APP_DEBUG', false),
            ]);
            
            return $engine;
        });

        // Register view helper
        $container->bind('view', function($container) {
            return function(string $template, array $data = []) use ($container) {
                $engine = $container->make(PrismEngine::class);
                return new PrismView($engine, $template, $data);
            };
        });
    }

    public function boot(): void
    {
        // Ensure cache directory exists
        $profile = app(\Refynd\Config\AppProfile::class);
        $cacheDir = $profile->storagePath('cache/views');
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
    }

    public function getName(): string
    {
        return 'Prism';
    }

    public function getDescription(): string
    {
        return 'Provides Prism template engine and view services';
    }
}

/**
 * Global view helper function
 */
if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        static $viewFactory = null;
        
        if ($viewFactory === null) {
            $viewFactory = app('view');
        }
        
        return $viewFactory($template, $data);
    }
}
