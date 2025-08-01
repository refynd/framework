<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Http\Router;
use Refynd\Events\EventDispatcher;

class RoutingModule extends Module
{
    public function register(Container $container): void
    {
        $container->singleton(Router::class, function ($container) {
            return new Router($container);
        });
    }

    public function boot(): void
    {
        // Routing module is booted when routes are loaded
    }

    public function getDependencies(): array
    {
        return [];
    }
}
