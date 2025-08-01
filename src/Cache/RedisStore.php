<?php

namespace Refynd\Cache;

class RedisStore implements CacheInterface
{
    private mixed $redis;
    private string $prefix;

    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not loaded');
        }

        if (!class_exists('Redis')) {
            throw new \RuntimeException('Redis class is not available');
        }

        $redisClass = 'Redis';
        $this->redis = new $redisClass();
        $this->redis->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379
        );

        if (isset($config['password'])) {
            $this->redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $this->redis->select($config['database']);
        }

        $this->prefix = $config['prefix'] ?? 'refynd_cache:';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefix . $key);
        
        if ($value === false) {
            return $default;
        }

        return unserialize($value);
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $serialized = serialize($value);
        
        if ($ttl > 0) {
            return $this->redis->setex($this->prefix . $key, $ttl, $serialized);
        }

        return $this->redis->set($this->prefix . $key, $serialized);
    }

    public function forget(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) > 0;
    }

    public function flush(): bool
    {
        $keys = $this->redis->keys($this->prefix . '*');
        
        if (empty($keys)) {
            return true;
        }

        return $this->redis->del($keys) > 0;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->redis->incrBy($this->prefix . $key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->redis->decrBy($this->prefix . $key, $value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
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
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    public function many(array $keys): array
    {
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        $values = $this->redis->mGet($prefixedKeys);
        
        $result = [];
        foreach ($keys as $index => $key) {
            $result[$key] = $values[$index] !== false ? unserialize($values[$index]) : null;
        }
        
        return $result;
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        $pipe = $this->redis->multi();
        
        foreach ($values as $key => $value) {
            $serialized = serialize($value);
            
            if ($ttl > 0) {
                $pipe->setex($this->prefix . $key, $ttl, $serialized);
            } else {
                $pipe->set($this->prefix . $key, $serialized);
            }
        }
        
        $results = $pipe->exec();
        
        return !in_array(false, $results, true);
    }

    public function forgetMany(array $keys): bool
    {
        $prefixedKeys = array_map(fn($key) => $this->prefix . $key, $keys);
        return $this->redis->del($prefixedKeys) > 0;
    }

    public function __destruct()
    {
        if (isset($this->redis)) {
            $this->redis->close();
        }
    }
}
