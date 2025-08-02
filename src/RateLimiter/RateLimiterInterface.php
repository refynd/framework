<?php

namespace Refynd\RateLimiter;

use Refynd\Cache\CacheInterface;

interface RateLimiterInterface
{
    /**
     * Determine if the given key has exceeded the rate limit.
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool;

    /**
     * Increment the counter for a given key for a given decay time.
     */
    public function hit(string $key, int $decaySeconds = 60): int;

    /**
     * Get the number of attempts for the given key.
     */
    public function attempts(string $key): int;

    /**
     * Reset the number of attempts for the given key.
     */
    public function resetAttempts(string $key): bool;

    /**
     * Get the number of retries left for the given key.
     */
    public function retriesLeft(string $key, int $maxAttempts): int;

    /**
     * Clear the hits and lockout timer for the given key.
     */
    public function clear(string $key): void;

    /**
     * Get the number of seconds until the key is accessible again.
     */
    public function availableIn(string $key): int;

    /**
     * Get rate limit information for a key.
     */
    public function getLimitInfo(string $key, int $maxAttempts, int $decaySeconds = 60): array;
}
