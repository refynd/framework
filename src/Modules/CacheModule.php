<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Cache\CacheManager;

class CacheModule extends Module
{
    public function register(Container $container): void
    {
        $container->singleton(CacheManager::class, function ($container) {
            $config = ['default' => 'file',
                'stores' => ['file' => ['driver' => 'file',
                        'path' => sys_get_temp_dir() . '/refynd_cache',],
                    'array' => ['driver' => 'array',],
                    'redis' => ['driver' => 'redis',
                        'host' => '127.0.0.1',
                        'port' => 6379,
                        'database' => 0,
                        'prefix' => 'refynd_cache:',],
                    'memcached' => ['driver' => 'memcached',
                        'servers' => [['127.0.0.1', 11211]],
                        'prefix' => 'refynd_cache:',],],];

            return new CacheManager($config);
        });
    }

    public function boot(): void
    {
        // Cache module is ready for use
    }

    public function getDependencies(): array
    {
        return [];
    }
}
