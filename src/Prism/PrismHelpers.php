<?php

namespace Refynd\Prism;

/**
 * PrismHelpers - Global Helper Functions for Prism Templates
 *
 * Provides convenient functions for common template operations.
 */

if (!function_exists('prism')) {
    /**
     * Get the Prism engine instance
     */
    function prism(): PrismEngine
    {
        static $engine = null;

        if ($engine === null) {
            $viewPath = getcwd() . '/views';
            $cachePath = getcwd() . '/storage/cache/views';
            $debugMode = defined('PRISM_DEBUG') ? constant('PRISM_DEBUG') : false;

            $engine = new PrismEngine($viewPath, $cachePath, $debugMode);

            // Register common helpers
            $engine->addGlobals(['app_name' => $_ENV['APP_NAME'] ?? 'Refynd App',
                'app_version' => $_ENV['APP_VERSION'] ?? '1.0.0',
                'current_year' => date('Y'),
                'current_date' => date('Y-m-d'),
                'current_time' => time(),]);
        }

        return $engine;
    }
}

if (!function_exists('view')) {
    /**
     * Create a new Prism view instance
     */
    function view(string $template, array $data = []): PrismView
    {
        return new PrismView(prism(), $template, $data);
    }
}

if (!function_exists('component')) {
    /**
     * Create a component view
     */
    function component(string $name, array $data = []): PrismView
    {
        return new PrismView(prism(), "components.{$name}", $data);
    }
}

if (!function_exists('asset')) {
    /**
     * Generate asset URL
     */
    function asset(string $path): string
    {
        $baseUrl = $_ENV['ASSET_URL'] ?? '/assets';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get CSRF token
     */
    function csrf_token(): string
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }
}

if (!function_exists('method_field')) {
    /**
     * Generate hidden method field
     */
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('old')) {
    /**
     * Get old input value
     */
    function old(string $key, string $default = ''): string
    {
        return $_SESSION['_old'][$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    /**
     * Get validation errors
     */
    function errors(): array
    {
        return $_SESSION['_errors'] ?? [];
    }
}

if (!function_exists('auth')) {
    /**
     * Get authentication guard (mock for now)
     */
    function auth(): object
    {
        return new class () {
            public function check(): bool
            {
                return isset($_SESSION['user_id']);
            }

            public function guest(): bool
            {
                return !$this->check();
            }

            public function user(): ?object
            {
                if (!$this->check()) {
                    return null;
                }

                return (object) ['id' => $_SESSION['user_id'] ?? null,
                    'name' => $_SESSION['user_name'] ?? 'Anonymous',
                    'email' => $_SESSION['user_email'] ?? '',];
            }
        };
    }
}

if (!function_exists('session')) {
    /**
     * Get session value
     */
    function session(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $config = null;

        if ($config === null) {
            $config = ['app.name' => $_ENV['APP_NAME'] ?? 'Refynd',
                'app.version' => $_ENV['APP_VERSION'] ?? '1.0.0',
                'app.debug' => $_ENV['APP_DEBUG'] ?? false,
                'cache.default' => $_ENV['CACHE_DRIVER'] ?? 'file',];
        }

        return $config[$key] ?? $default;
    }
}

if (!function_exists('url')) {
    /**
     * Generate URL
     */
    function url(string $path = ''): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    /**
     * Generate route URL (simplified)
     */
    function route(string $name, array $parameters = []): string
    {
        // This would integrate with Refynd's router in a real implementation
        return url($name . '?' . http_build_query($parameters));
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die
     */
    function dd(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        die();
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variable
     */
    function dump(mixed ...$vars): void
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
    }
}
