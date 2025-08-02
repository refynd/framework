<?php

namespace Refynd\Cache;

/**
 * HighPerformanceCache - Optimized Caching Layer
 *
 * Adds performance optimizations to the cache system including
 * in-memory caching, batch operations, and intelligent prefetching.
 */
class HighPerformanceCache implements CacheInterface
{
    protected CacheInterface $store;
    protected array $localCache = [];
    protected array $accessPattern = [];
    protected int $maxLocalItems = 1000;
    protected int $hitCount = 0;
    protected int $missCount = 0;
    protected bool $trackAccess = true;

    public function __construct(CacheInterface $store, array $options = [])
    {
        $this->store = $store;
        $this->maxLocalItems = $options['max_local_items'] ?? 1000;
        $this->trackAccess = $options['track_access'] ?? true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Check local cache first
        if (isset($this->localCache[$key])) {
            $this->recordHit();
            $this->recordAccess($key);
            return $this->localCache[$key]['value'];
        }

        // Get from underlying store
        $value = $this->store->get($key, $default);

        if ($value !== $default) {
            $this->recordHit();
            $this->cacheLocally($key, $value);
        } else {
            $this->recordMiss();
        }

        $this->recordAccess($key);
        return $value;
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        // Update local cache
        $this->cacheLocally($key, $value, $ttl);

        // Store in underlying cache
        return $this->store->put($key, $value, $ttl);
    }

    public function forget(string $key): bool
    {
        // Remove from local cache
        unset($this->localCache[$key]);

        // Remove from underlying store
        return $this->store->forget($key);
    }

    public function flush(): bool
    {
        $this->localCache = [];
        $this->accessPattern = [];
        return $this->store->flush();
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        // Remove from local cache as it will be stale
        unset($this->localCache[$key]);
        return $this->store->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        // Remove from local cache as it will be stale
        unset($this->localCache[$key]);
        return $this->store->decrement($key, $value);
    }

    public function forever(string $key, mixed $value): bool
    {
        $this->cacheLocally($key, $value, 0);
        return $this->store->forever($key, $value);
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
        return isset($this->localCache[$key]) || $this->store->has($key);
    }

    public function many(array $keys): array
    {
        $results = [];
        $missingKeys = [];

        // Check local cache first
        foreach ($keys as $key) {
            if (isset($this->localCache[$key])) {
                $results[$key] = $this->localCache[$key]['value'];
                $this->recordHit();
            } else {
                $missingKeys[] = $key;
            }
        }

        // Get missing keys from store
        if (!empty($missingKeys)) {
            $storeResults = $this->store->many($missingKeys);

            foreach ($storeResults as $key => $value) {
                $results[$key] = $value;
                if ($value !== null) {
                    $this->cacheLocally($key, $value);
                    $this->recordHit();
                } else {
                    $this->recordMiss();
                }
            }
        }

        return $results;
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        // Update local cache
        foreach ($values as $key => $value) {
            $this->cacheLocally($key, $value, $ttl);
        }

        return $this->store->putMany($values, $ttl);
    }

    public function forgetMany(array $keys): bool
    {
        // Remove from local cache
        foreach ($keys as $key) {
            unset($this->localCache[$key]);
        }

        return $this->store->forgetMany($keys);
    }

    /**
     * Cache a value locally
     */
    protected function cacheLocally(string $key, mixed $value, int $ttl = 3600): void
    {
        // Cleanup if we're at capacity
        if (count($this->localCache) >= $this->maxLocalItems) {
            $this->evictLeastRecentlyUsed();
        }

        $this->localCache[$key] = ['value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'access_count' => 1,
            'last_access' => time(),];
    }

    /**
     * Evict least recently used items
     */
    protected function evictLeastRecentlyUsed(): void
    {
        if (empty($this->localCache)) {
            return;
        }

        // Sort by last access time
        uasort($this->localCache, function ($a, $b) {
            return $a['last_access'] <=> $b['last_access'];
        });

        // Remove oldest 25% of items
        $removeCount = max(1, intval(count($this->localCache) * 0.25));
        $keys = array_keys($this->localCache);

        for ($i = 0; $i < $removeCount; $i++) {
            unset($this->localCache[$keys[$i]]);
        }
    }

    /**
     * Record cache access pattern
     */
    protected function recordAccess(string $key): void
    {
        if (!$this->trackAccess) {
            return;
        }

        if (isset($this->localCache[$key])) {
            $this->localCache[$key]['access_count']++;
            $this->localCache[$key]['last_access'] = time();
        }

        $this->accessPattern[$key] = ($this->accessPattern[$key] ?? 0) + 1;
    }

    /**
     * Record cache hit
     */
    protected function recordHit(): void
    {
        $this->hitCount++;
    }

    /**
     * Record cache miss
     */
    protected function recordMiss(): void
    {
        $this->missCount++;
    }

    /**
     * Get performance statistics
     */
    public function getStats(): array
    {
        $total = $this->hitCount + $this->missCount;
        $hitRatio = $total > 0 ? ($this->hitCount / $total) * 100 : 0;

        return ['hits' => $this->hitCount,
            'misses' => $this->missCount,
            'hit_ratio' => round($hitRatio, 2),
            'local_cache_size' => count($this->localCache),
            'max_local_items' => $this->maxLocalItems,
            'access_patterns' => count($this->accessPattern),
            'memory_usage' => memory_get_usage(),];
    }

    /**
     * Get most accessed keys
     */
    public function getMostAccessed(int $limit = 10): array
    {
        arsort($this->accessPattern);
        return array_slice($this->accessPattern, 0, $limit, true);
    }

    /**
     * Clear local cache only
     */
    public function clearLocal(): void
    {
        $this->localCache = [];
        $this->accessPattern = [];
        $this->hitCount = 0;
        $this->missCount = 0;
    }

    /**
     * Prefetch keys based on access patterns
     */
    public function prefetch(array $keys): void
    {
        $values = $this->store->many($keys);

        foreach ($values as $key => $value) {
            if ($value !== null) {
                $this->cacheLocally($key, $value);
            }
        }
    }
}
