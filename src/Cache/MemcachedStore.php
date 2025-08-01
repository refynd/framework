<?php

namespace Refynd\Cache;

class MemcachedStore implements CacheInterface
{
    private mixed $memcached;
    private string $prefix;

    public function __construct(array $config)
    {
        if (!extension_loaded('memcached')) {
            throw new \RuntimeException('Memcached extension is not loaded');
        }

        if (!class_exists('Memcached')) {
            throw new \RuntimeException('Memcached class is not available');
        }

        $memcachedClass = 'Memcached';
        $this->memcached = new $memcachedClass();
        
        $servers = $config['servers'] ?? [['127.0.0.1', 11211]];
        $this->memcached->addServers($servers);
        
        $this->prefix = $config['prefix'] ?? 'refynd_cache:';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->memcached->get($this->prefix . $key);
        
        if ($this->memcached->getResultCode() === constant('Memcached::RES_NOTFOUND')) {
            return $default;
        }

        return $value;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->memcached->set($this->prefix . $key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        return $this->memcached->delete($this->prefix . $key);
    }

    public function flush(): bool
    {
        return $this->memcached->flush();
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->memcached->increment($this->prefix . $key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->memcached->decrement($this->prefix . $key, $value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->memcached->set($this->prefix . $key, $value, 0);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->put($key, $value, $ttl);
        
        return $value;
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->forever($key, $value);
        
        return $value;
    }

    public function has(string $key): bool
    {
        $this->memcached->get($this->prefix . $key);
        return $this->memcached->getResultCode() !== constant('Memcached::RES_NOTFOUND');
    }

    public function many(array $keys): array
    {
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        $values = $this->memcached->getMulti($prefixedKeys);
        
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $values[$this->prefix . $key] ?? null;
        }
        
        return $result;
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        $prefixedValues = [];
        foreach ($values as $key => $value) {
            $prefixedValues[$this->prefix . $key] = $value;
        }
        
        return $this->memcached->setMulti($prefixedValues, $ttl);
    }

    public function forgetMany(array $keys): bool
    {
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        $this->memcached->deleteMulti($prefixedKeys);
        
        return true; // Memcached deleteMulti doesn't return meaningful result
    }
}
