<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Prism\PrismEngine;
use Refynd\Prism\PrismView;

/**
 * PrismModule - Enhanced Template Engine Module
 * 
 * Integrates the enhanced Prism template engine into the Refynd framework.
 * Provides view rendering, component management, template compilation services,
 * custom directives, filters, and framework integration.
 */
class PrismModule extends Module
{
    /**
     * Register the module's services
     */
    public function register(Container $container): void
    {
        $this->registerEngine($container);
        $this->registerViewHelpers($container);
        $this->registerDirectives($container);
        $this->registerFilters($container);
    }

    /**
     * Register the enhanced Prism engine
     */
    protected function registerEngine(Container $container): void
    {
        $container->singleton(PrismEngine::class, function($container) {
            $profile = $container->make(\Refynd\Config\AppProfile::class);
            
            $viewPath = $profile->path('views');
            $cachePath = $profile->storagePath('cache/views');
            $debugMode = env('APP_DEBUG', false) === 'true';
            
            $engine = new PrismEngine($viewPath, $cachePath, $debugMode);
            
            // Add enhanced global variables
            $engine->addGlobals([
                'app_name' => env('APP_NAME', 'Refynd Application'),
                'app_version' => env('APP_VERSION', '1.2.0'),
                'app_url' => env('APP_URL', 'http://localhost'),
                'current_year' => date('Y'),
                'current_date' => date('Y-m-d'),
                'current_time' => time(),
                'debug' => $debugMode,
            ]);
            
            return $engine;
        });

        // Register convenient aliases
        $container->bind('view.engine', PrismEngine::class);
        $container->bind('prism', PrismEngine::class);
    }

    /**
     * Register enhanced view helper functions
     */
    protected function registerViewHelpers(Container $container): void
    {
        // Enhanced view factory function
        $container->bind('view', function($container) {
            return function(string $template, array $data = []) use ($container) {
                $engine = $container->make(PrismEngine::class);
                return new PrismView($engine, $template, $data);
            };
        });

        // Component factory function
        $container->bind('component', function (Container $container) {
            return function (string $name, array $data = []) use ($container) {
                $engine = $container->make(PrismEngine::class);
                return new PrismView($engine, "components.{$name}", $data);
            };
        });

        // Template renderer function
        $container->bind('render', function (Container $container) {
            return function (string $template, array $data = []) use ($container) {
                $engine = $container->make(PrismEngine::class);
                return $engine->render($template, $data);
            };
        });
    }

    /**
     * Register framework-specific directives
     */
    protected function registerDirectives(Container $container): void
    {
        $engine = $container->make(PrismEngine::class);

        // Route directive - placeholder for future router integration
        $engine->directive('route', function ($expression) {
            return "<?php echo '/'.{$expression}; ?>";
        });

        // URL directive
        $engine->directive('url', function ($expression) {
            return "<?php echo rtrim('" . env('APP_URL', 'http://localhost') . "', '/') . '/' . ltrim({$expression}, '/'); ?>";
        });

        // Config directive
        $engine->directive('config', function ($expression) {
            return "<?php echo env({$expression}); ?>";
        });

        // Session directive - placeholder for future session integration
        $engine->directive('session', function ($expression) {
            return "<?php echo \$_SESSION[{$expression}] ?? ''; ?>";
        });

        // Flash message directives - placeholder for future session integration
        $engine->directive('flash', function ($type = null) {
            if ($type) {
                return "<?php if (isset(\$_SESSION['flash'][{$type}])): ?>" .
                       "<?php echo \$_SESSION['flash'][{$type}]; ?>" .
                       "<?php endif; ?>";
            } else {
                return "<?php echo \$_SESSION['flash']['message'] ?? ''; ?>";
            }
        });

        // Error directive for validation - placeholder for future validation integration
        $engine->directive('error', function ($field) {
            return "<?php echo \$_SESSION['errors'][{$field}] ?? ''; ?>";
        });

        // Old input directive - placeholder for future form handling integration
        $engine->directive('old', function ($expression) {
            return "<?php echo \$_SESSION['old'][{$expression}] ?? ''; ?>";
        });

        // Authorization directives - placeholder for future auth integration
        $engine->directive('can', function ($ability) {
            return "<?php if (true): // Placeholder for authorization check ?>";
        });

        $engine->directive('cannot', function ($ability) {
            return "<?php if (false): // Placeholder for authorization check ?>";
        });

        $engine->directive('endcan', function () {
            return "<?php endif; ?>";
        });

        $engine->directive('endcannot', function () {
            return "<?php endif; ?>";
        });

        // Asset stack management
        $engine->directive('stack', function ($name) {
            return "<?php echo \$__stacks[{$name}] ?? ''; ?>";
        });

        $engine->directive('push', function ($name) {
            return "<?php ob_start(); \$__currentStack = {$name}; ?>";
        });

        $engine->directive('endpush', function () {
            return "<?php \$__stacks[\$__currentStack] = (\$__stacks[\$__currentStack] ?? '') . ob_get_clean(); ?>";
        });
    }

    /**
     * Register framework-specific filters
     */
    protected function registerFilters(Container $container): void
    {
        $engine = $container->make(PrismEngine::class);

        // Route filter - placeholder for future router integration
        $engine->filter('route', function ($name, ...$parameters) {
            // Placeholder for route generation when router is implemented
            $params = !empty($parameters) ? '?' . http_build_query($parameters) : '';
            return "/{$name}{$params}";
        });

        // URL filter
        $engine->filter('url', function ($path) {
            $baseUrl = env('APP_URL', 'http://localhost');
            return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        });

        // Asset filter
        $engine->filter('asset', function ($path) {
            $baseUrl = env('APP_URL', 'http://localhost');
            return rtrim($baseUrl, '/') . '/assets/' . ltrim($path, '/');
        });

        // Config filter
        $engine->filter('config', function ($key, $default = null) {
            return env($key, $default);
        });

        // Pluralize filter
        $engine->filter('pluralize', function ($count, $singular, $plural = null) {
            if ($plural === null) {
                $plural = $singular . 's';
            }
            return $count == 1 ? $singular : $plural;
        });

        // Human readable file size
        $engine->filter('filesize', function ($bytes) {
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }
            return round($bytes, 2) . ' ' . $units[$i];
        });

        // Time ago filter
        $engine->filter('timeAgo', function ($timestamp) {
            $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
            $diff = time() - $time;
            
            if ($diff < 60) return 'just now';
            if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
            if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
            if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
            
            return date('M j, Y', $time);
        });

        // Basic markdown filter
        $engine->filter('markdown', function ($text) {
            $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
            $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
            $text = preg_replace('/`(.*?)`/', '<code>$1</code>', $text);
            $text = nl2br($text);
            return $text;
        });
    }

    /**
     * Boot the module's services
     */
    public function boot(): void
    {
        $profile = app(\Refynd\Config\AppProfile::class);
        
        // Create enhanced directory structure
        $viewPaths = [
            $profile->path('views'),
            $profile->path('views/layouts'),
            $profile->path('views/components'),
            $profile->path('views/pages'),
            $profile->path('views/partials'),
            $profile->storagePath('cache/views'),
        ];

        foreach ($viewPaths as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        // Create .gitkeep files for organization
        $gitkeepPaths = [
            $profile->path('views/layouts/.gitkeep'),
            $profile->path('views/components/.gitkeep'),
            $profile->path('views/pages/.gitkeep'),
            $profile->path('views/partials/.gitkeep'),
        ];

        foreach ($gitkeepPaths as $path) {
            if (!file_exists($path)) {
                file_put_contents($path, '');
            }
        }
    }

    /**
     * Get the module name
     */
    public function getName(): string
    {
        return 'Prism Enhanced';
    }

    /**
     * Get module version
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Get module description
     */
    public function getDescription(): string
    {
        return 'Enhanced Prism template engine with advanced features, custom directives, filters, and complete Refynd framework integration';
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return ['container'];
    }
}

/**
 * Enhanced global view helper function
 */
if (!function_exists('view')) {
    function view(string $template, array $data = []): PrismView
    {
        static $viewFactory = null;
        
        if ($viewFactory === null) {
            $viewFactory = app('view');
        }
        
        return $viewFactory($template, $data);
    }
}

/**
 * Component helper function
 */
if (!function_exists('component')) {
    function component(string $name, array $data = []): PrismView
    {
        static $componentFactory = null;
        
        if ($componentFactory === null) {
            $componentFactory = app('component');
        }
        
        return $componentFactory($name, $data);
    }
}
