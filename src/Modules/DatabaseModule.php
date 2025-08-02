<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Database\Ledger;

/**
 * DatabaseModule - Provides Database Services
 *
 * Registers the Ledger ORM and database services
 * with the application container.
 */
class DatabaseModule extends Module
{
    public function register(Container $container): void
    {
        // Register Ledger as singleton
        $container->singleton(Ledger::class, function ($container) {
            return new Ledger();
        });

        // Bind database configuration
        $container->bind('database.config', function ($container) {
            $profile = $container->make(\Refynd\Config\AppProfile::class);

            return ['driver' => env('DB_CONNECTION', 'mysql'),
                'host' => env('DB_HOST', 'localhost'),
                'port' => env('DB_PORT', 3306),
                'database' => env('DB_DATABASE', ''),
                'username' => env('DB_USERNAME', ''),
                'password' => env('DB_PASSWORD', ''),];
        });
    }

    public function boot(): void
    {
        // Configure Ledger with database settings
        $config = app('database.config');
        Ledger::configure($config);
    }

    public function getName(): string
    {
        return 'Database';
    }

    public function getDescription(): string
    {
        return 'Provides Ledger ORM and database services';
    }
}

/**
 * Helper function to get the application container
 */
if (!function_exists('app')) {
    function app(?string $abstract = null): mixed
    {
        $container = \Refynd\Bootstrap\Engine::getContainerInstance();

        if ($container === null) {
            throw new \RuntimeException('Application container not available');
        }

        if ($abstract === null) {
            return $container;
        }

        return $container->make($abstract);
    }
}
