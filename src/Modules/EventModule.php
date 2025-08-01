<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Events\EventDispatcher;

class EventModule extends Module
{
    public function register(Container $container): void
    {
        $container->singleton(EventDispatcher::class, function ($container) {
            return new EventDispatcher($container);
        });
    }

    public function boot(): void
    {
        // Event module is ready for use
    }

    public function getDependencies(): array
    {
        return [];
    }
}
