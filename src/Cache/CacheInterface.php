<?php

namespace Refynd\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function put(string $key, mixed $value, int $ttl = 3600): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function increment(string $key, int $value = 1): int|bool;
    public function decrement(string $key, int $value = 1): int|bool;
    public function forever(string $key, mixed $value): bool;
    public function remember(string $key, int $ttl, callable $callback): mixed;
    public function rememberForever(string $key, callable $callback): mixed;
    public function has(string $key): bool;
    public function many(array $keys): array;
    public function putMany(array $values, int $ttl = 3600): bool;
    public function forgetMany(array $keys): bool;
}
