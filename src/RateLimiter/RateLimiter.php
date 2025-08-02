<?php

namespace Refynd\RateLimiter;

use Refynd\Cache\CacheInterface;
use Refynd\Cache\ArrayStore;

class RateLimiter implements RateLimiterInterface
{
    private CacheInterface $cache;
    private string $keyPrefix;

    public function __construct(?CacheInterface $cache = null, string $keyPrefix = 'rate_limit')
    {
        $this->cache = $cache ?? new ArrayStore();
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Determine if the given key has exceeded the rate limit.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        $key = $this->resolveRequestSignature($key);

        $this->cache->put(
            $key . ':timer',
            $this->availableAt($decaySeconds),
            $decaySeconds
        );

        $hits = (int) $this->cache->get($key, 0) + 1;
        $this->cache->put($key, $hits, $decaySeconds);

        return $hits;
    }

    /**
     * Get the number of attempts for the given key.
     */
    public function attempts(string $key): int
    {
        $key = $this->resolveRequestSignature($key);
        return (int) $this->cache->get($key, 0);
    }

    /**
     * Reset the number of attempts for the given key.
     */
    public function resetAttempts(string $key): bool
    {
        $key = $this->resolveRequestSignature($key);
        return $this->cache->forget($key);
    }

    /**
     * Get the number of retries left for the given key.
     */
    public function retriesLeft(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Clear the hits and lockout timer for the given key.
     */
    public function clear(string $key): void
    {
        $key = $this->resolveRequestSignature($key);
        $this->resetAttempts($key);
        $this->cache->forget($key . ':timer');
    }

    /**
     * Get the number of seconds until the key is accessible again.
     */
    public function availableIn(string $key): int
    {
        $key = $this->resolveRequestSignature($key);
        $timer = $this->cache->get($key . ':timer');
        return $timer ? max(0, $timer - $this->currentTime()) : 0;
    }

    /**
     * Get rate limit information for a key.
     */
    public function getLimitInfo(string $key, int $maxAttempts, int $decaySeconds = 60): array
    {
        $attempts = $this->attempts($key);
        $remaining = max(0, $maxAttempts - $attempts);
        $availableIn = $this->availableIn($key);

        return ['key' => $key,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'remaining' => $remaining,
            'available_in' => $availableIn,
            'available_at' => $availableIn > 0 ? $this->currentTime() + $availableIn : null,
            'is_limited' => $attempts >= $maxAttempts,
            'decay_seconds' => $decaySeconds,
            'reset_time' => $this->currentTime() + $decaySeconds,];
    }

    /**
     * Attempt to execute a callback with rate limiting.
     */
    public function attempt(string $key, int $maxAttempts, callable $callback, int $decaySeconds = 60): mixed
    {
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            $limitInfo = $this->getLimitInfo($key, $maxAttempts, $decaySeconds);
            throw new RateLimitExceededException(
                'Rate limit exceeded for key: ' . $key,
                $this->availableIn($key),
                $limitInfo
            );
        }

        $this->hit($key, $decaySeconds);
        return $callback();
    }

    /**
     * Execute a callback with rate limiting, returning null if rate limited.
     */
    public function attemptOrNull(string $key, int $maxAttempts, callable $callback, int $decaySeconds = 60): mixed
    {
        try {
            return $this->attempt($key, $maxAttempts, $callback, $decaySeconds);
        } catch (RateLimitExceededException $e) {
            return null;
        }
    }

    /**
     * Check if a key is currently rate limited.
     */
    public function isLimited(string $key, int $maxAttempts): bool
    {
        return $this->tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Create a rate limiter for a specific feature/component.
     */
    public static function for(string $feature, ?CacheInterface $cache = null): self
    {
        return new self($cache, "rate_limit:{$feature}");
    }

    /**
     * Get comprehensive statistics about rate limiting.
     */
    public function getStatistics(): array
    {
        return ['cache_driver' => get_class($this->cache),
            'key_prefix' => $this->keyPrefix,
            'current_time' => $this->currentTime(),];
    }

    /**
     * Resolve the request signature for the given key.
     */
    protected function resolveRequestSignature(string $key): string
    {
        return $this->keyPrefix . ':' . sha1($key);
    }

    /**
     * Get the current timestamp.
     */
    protected function currentTime(): int
    {
        return time();
    }

    /**
     * Get the "available at" UNIX timestamp.
     */
    protected function availableAt(int $delay): int
    {
        return $this->currentTime() + $delay;
    }
}
