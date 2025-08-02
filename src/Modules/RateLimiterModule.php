<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\RateLimiter\RateLimiter;
use Refynd\RateLimiter\WebSocketRateLimiter;
use Refynd\RateLimiter\HttpRateLimiter;
use Refynd\RateLimiter\ApiRateLimiter;
use Refynd\Cache\CacheInterface;

class RateLimiterModule extends Module
{
    public function register(Container $container): void
    {
        // Register the base rate limiter
        $container->singleton(RateLimiter::class, function(Container $container) {
            $cache = $container->has(CacheInterface::class) 
                ? $container->get(CacheInterface::class) 
                : null;
            
            return new RateLimiter($cache);
        });

        // Register specific rate limiters
        $container->singleton(WebSocketRateLimiter::class, function(Container $container) {
            $cache = $container->has(CacheInterface::class) 
                ? $container->get(CacheInterface::class) 
                : null;
            
            return new WebSocketRateLimiter($cache);
        });

        $container->singleton(HttpRateLimiter::class, function(Container $container) {
            $cache = $container->has(CacheInterface::class) 
                ? $container->get(CacheInterface::class) 
                : null;
            
            return new HttpRateLimiter($cache);
        });

        $container->singleton(ApiRateLimiter::class, function(Container $container) {
            $cache = $container->has(CacheInterface::class) 
                ? $container->get(CacheInterface::class) 
                : null;
            
            return new ApiRateLimiter($cache);
        });
    }

    public function boot(): void
    {
        // No boot logic needed for rate limiters
    }

    public function getDescription(): string
    {
        return 'Provides comprehensive rate limiting capabilities across the framework';
    }

    public function getDependencies(): array
    {
        return [CacheModule::class];
    }
}
