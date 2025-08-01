<?php

namespace Refynd\Cache;

class CacheManager
{
    private array $stores = [];
    private array $config;
    private string $defaultStore;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->defaultStore = $config['default'] ?? 'file';
    }

    public function store(?string $name = null): CacheInterface
    {
        $name = $name ?: $this->defaultStore;

        if (!isset($this->stores[$name])) {
            $this->stores[$name] = $this->createStore($name);
        }

        return $this->stores[$name];
    }

    private function createStore(string $name): CacheInterface
    {
        $config = $this->config['stores'][$name] ?? [];
        $driver = $config['driver'] ?? 'file';

        return match ($driver) {
            'file' => new FileStore($config),
            'array' => new ArrayStore($config),
            'redis' => new RedisStore($config),
            'memcached' => new MemcachedStore($config),
            default => throw new \InvalidArgumentException("Cache driver [{$driver}] not supported"),
        };
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->store()->$method(...$arguments);
    }

    // Delegate methods to the default store for better IDE support
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->store()->put($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function flush(): bool
    {
        return $this->store()->flush();
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->store()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->store()->decrement($key, $value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->store()->forever($key, $value);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        return $this->store()->remember($key, $ttl, $callback);
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        return $this->store()->rememberForever($key, $callback);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function many(array $keys): array
    {
        return $this->store()->many($keys);
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        return $this->store()->putMany($values, $ttl);
    }

    public function forgetMany(array $keys): bool
    {
        return $this->store()->forgetMany($keys);
    }
}
