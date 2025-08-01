<?php

namespace Refynd\Cache;

use Refynd\Bootstrap\Engine;

class Cache
{
    private static ?CacheManager $manager = null;

    private static function getManager(): CacheManager
    {
        if (self::$manager === null) {
            self::$manager = Engine::container()->make(CacheManager::class);
        }

        return self::$manager;
    }

    public static function store(?string $name = null): CacheInterface
    {
        return self::getManager()->store($name);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getManager()->get($key, $default);
    }

    public static function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return self::getManager()->put($key, $value, $ttl);
    }

    public static function forget(string $key): bool
    {
        return self::getManager()->forget($key);
    }

    public static function flush(): bool
    {
        return self::getManager()->flush();
    }

    public static function increment(string $key, int $value = 1): int|bool
    {
        return self::getManager()->increment($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int|bool
    {
        return self::getManager()->decrement($key, $value);
    }

    public static function forever(string $key, mixed $value): bool
    {
        return self::getManager()->forever($key, $value);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        return self::getManager()->remember($key, $ttl, $callback);
    }

    public static function rememberForever(string $key, callable $callback): mixed
    {
        return self::getManager()->rememberForever($key, $callback);
    }

    public static function has(string $key): bool
    {
        return self::getManager()->has($key);
    }

    public static function many(array $keys): array
    {
        return self::getManager()->many($keys);
    }

    public static function putMany(array $values, int $ttl = 3600): bool
    {
        return self::getManager()->putMany($values, $ttl);
    }

    public static function forgetMany(array $keys): bool
    {
        return self::getManager()->forgetMany($keys);
    }
}
