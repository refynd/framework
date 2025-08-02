<?php

namespace Refynd\Cache;

/**
 * TaggedCache - Advanced cache implementation with tagging support
 *
 * Provides cache tagging functionality for intelligent cache invalidation
 * and grouping of related cache entries.
 */
class TaggedCache implements CacheInterface
{
    protected CacheInterface $store;
    protected array $tags;
    protected string $tagPrefix = 'tag:';

    public function __construct(CacheInterface $store, array $tags = [])
    {
        $this->store = $store;
        $this->tags = $tags;
    }

    /**
     * Get a value from cache with tag validation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check if any tags have been invalidated
        if ($this->tagsAreStale()) {
            return $default;
        }

        return $this->store->get($this->taggedKey($key), $default);
    }

    /**
     * Store a value in cache with tags
     */
    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $this->updateTagTimestamps();
        return $this->store->put($this->taggedKey($key), $value, $ttl);
    }

    /**
     * Remove a tagged cache entry
     */
    public function forget(string $key): bool
    {
        return $this->store->forget($this->taggedKey($key));
    }

    /**
     * Flush all tagged cache entries
     */
    public function flush(): bool
    {
        $this->invalidateTags();
        return true;
    }

    /**
     * Increment a tagged cache value
     */
    public function increment(string $key, int $value = 1): int|bool
    {
        if ($this->tagsAreStale()) {
            $this->put($key, $value);
            return $value;
        }

        return $this->store->increment($this->taggedKey($key), $value);
    }

    /**
     * Decrement a tagged cache value
     */
    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    /**
     * Store a value permanently with tags
     */
    public function forever(string $key, mixed $value): bool
    {
        $this->updateTagTimestamps();
        return $this->store->forever($this->taggedKey($key), $value);
    }

    /**
     * Get or set a cached value with tags
     */
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

    /**
     * Get or set a permanently cached value with tags
     */
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

    /**
     * Check if a tagged key exists and is valid
     */
    public function has(string $key): bool
    {
        if ($this->tagsAreStale()) {
            return false;
        }

        return $this->store->has($this->taggedKey($key));
    }

    /**
     * Get multiple values with tag validation
     */
    public function many(array $keys): array
    {
        if ($this->tagsAreStale()) {
            return array_fill_keys($keys, null);
        }

        $taggedKeys = array_map([$this, 'taggedKey'], $keys);
        $results = $this->store->many($taggedKeys);

        // Map back to original keys
        $mapped = [];
        foreach ($keys as $i => $originalKey) {
            $mapped[$originalKey] = $results[$taggedKeys[$i]] ?? null;
        }

        return $mapped;
    }

    /**
     * Store multiple values with tags
     */
    public function putMany(array $values, int $ttl = 3600): bool
    {
        $this->updateTagTimestamps();

        $taggedValues = [];
        foreach ($values as $key => $value) {
            $taggedValues[$this->taggedKey($key)] = $value;
        }

        return $this->store->putMany($taggedValues, $ttl);
    }

    /**
     * Remove multiple tagged entries
     */
    public function forgetMany(array $keys): bool
    {
        $taggedKeys = array_map([$this, 'taggedKey'], $keys);
        return $this->store->forgetMany($taggedKeys);
    }

    /**
     * Add tags to this cache instance
     */
    public function tags(array $tags): self
    {
        return new static($this->store, array_unique(array_merge($this->tags, $tags)));
    }

    /**
     * Get the tags for this cache instance
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Invalidate all cache entries with specific tags
     */
    public function invalidateTags(): void
    {
        foreach ($this->tags as $tag) {
            $this->store->put($this->tagKey($tag), time(), 0);
        }
    }

    /**
     * Generate a tagged cache key
     */
    protected function taggedKey(string $key): string
    {
        if (empty($this->tags)) {
            return $key;
        }

        $tagString = implode('|', $this->tags);
        $tagHash = hash('md5', $tagString);

        return "tagged:{$tagHash}:{$key}";
    }

    /**
     * Generate a tag timestamp key
     */
    protected function tagKey(string $tag): string
    {
        return $this->tagPrefix . $tag;
    }

    /**
     * Check if any tags have been invalidated
     */
    protected function tagsAreStale(): bool
    {
        if (empty($this->tags)) {
            return false;
        }

        $taggedKey = $this->taggedKey('__tag_check__');
        $storedTimestamp = $this->store->get($taggedKey);

        if ($storedTimestamp === null) {
            return true; // No timestamp stored, consider stale
        }

        foreach ($this->tags as $tag) {
            $tagTimestamp = $this->store->get($this->tagKey($tag));
            if ($tagTimestamp && $tagTimestamp > $storedTimestamp) {
                return true; // Tag was invalidated after this entry was created
            }
        }

        return false;
    }

    /**
     * Update tag timestamps for current tags
     */
    protected function updateTagTimestamps(): void
    {
        if (empty($this->tags)) {
            return;
        }

        $currentTime = time();

        // Store the creation timestamp for this tagged entry
        $taggedKey = $this->taggedKey('__tag_check__');
        $this->store->put($taggedKey, $currentTime, 0);

        // Initialize tag timestamps if they don't exist
        foreach ($this->tags as $tag) {
            $tagKey = $this->tagKey($tag);
            if (!$this->store->has($tagKey)) {
                $this->store->put($tagKey, $currentTime, 0);
            }
        }
    }
}
