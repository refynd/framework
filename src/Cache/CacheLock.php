<?php

namespace Refynd\Cache;

/**
 * CacheLock - Distributed locking mechanism using cache
 *
 * Provides cache-based distributed locking to prevent cache stampede
 * and ensure atomic operations across multiple processes.
 */
class CacheLock
{
    protected CacheInterface $cache;
    protected string $key;
    protected int $timeout;
    protected ?string $token = null;
    protected bool $acquired = false;

    public function __construct(CacheInterface $cache, string $key, int $timeout = 10)
    {
        $this->cache = $cache;
        $this->key = "lock:{$key}";
        $this->timeout = $timeout;
    }

    /**
     * Attempt to acquire the lock
     */
    public function acquire(): bool
    {
        if ($this->acquired) {
            return true;
        }

        $this->token = $this->generateToken();
        $acquired = $this->cache->put($this->key, $this->token, $this->timeout);

        if ($acquired) {
            $this->acquired = true;
        }

        return $acquired;
    }

    /**
     * Release the lock
     */
    public function release(): bool
    {
        if (!$this->acquired || !$this->token) {
            return false;
        }

        // Only release if we still own the lock
        $currentToken = $this->cache->get($this->key);
        if ($currentToken === $this->token) {
            $this->cache->forget($this->key);
            $this->acquired = false;
            $this->token = null;
            return true;
        }

        return false;
    }

    /**
     * Execute a callback with the lock
     */
    public function get(callable $callback, mixed $default = null): mixed
    {
        if ($this->acquire()) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }

        return $default;
    }

    /**
     * Block until lock is acquired, then execute callback
     */
    public function block(callable $callback, int $maxWaitTime = 30): mixed
    {
        $startTime = time();

        while (!$this->acquire()) {
            if (time() - $startTime >= $maxWaitTime) {
                throw new \RuntimeException("Failed to acquire lock '{$this->key}' within {$maxWaitTime} seconds");
            }

            usleep(100000); // Wait 100ms before retrying
        }

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }

    /**
     * Check if the lock is currently acquired by this instance
     */
    public function isAcquired(): bool
    {
        return $this->acquired;
    }

    /**
     * Check if the lock exists (regardless of who owns it)
     */
    public function exists(): bool
    {
        return $this->cache->has($this->key);
    }

    /**
     * Get the remaining time on the lock
     */
    public function getRemainingTime(): int
    {
        // This would require cache stores to support TTL inspection
        // For now, return the original timeout
        return $this->timeout;
    }

    /**
     * Generate a unique token for this lock instance
     */
    protected function generateToken(): string
    {
        return uniqid(gethostname() . '_' . getmypid() . '_', true);
    }

    /**
     * Auto-release lock when object is destroyed
     */
    public function __destruct()
    {
        $this->release();
    }
}
