<?php

namespace Refynd\Hash;

use RuntimeException;

/**
 * ArgonHasher - Argon2 implementation of the HashInterface
 *
 * Uses PHP's password_hash() with PASSWORD_ARGON2ID algorithm.
 * Provides modern, secure password hashing with memory and time cost factors.
 */
class ArgonHasher implements HashInterface
{
    protected int $memory;
    protected int $time;
    protected int $threads;

    public function __construct(int $memory = 65536, int $time = 4, int $threads = 3)
    {
        $this->memory = $memory;
        $this->time = $time;
        $this->threads = $threads;
    }

    /**
     * Hash the given value using Argon2ID
     */
    public function make(string $value, array $options = []): string
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException('Argon2ID hashing not supported.');
        }

        $hash = password_hash($value, PASSWORD_ARGON2ID, ['memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,]);

        if (!is_string($hash) || empty($hash)) {
            throw new RuntimeException('Argon2ID hashing failed.');
        }

        return $hash;
    }

    /**
     * Verify a value against an Argon2ID hash
     */
    public function check(string $value, string $hashedValue): bool
    {
        if (empty($hashedValue)) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    /**
     * Check if the given hash needs to be rehashed
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            return false;
        }

        return password_needs_rehash($hashedValue, PASSWORD_ARGON2ID, ['memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,]);
    }

    /**
     * Get information about the given hash
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * Set the default memory cost
     */
    public function setMemory(int $memory): void
    {
        $this->memory = $memory;
    }

    /**
     * Set the default time cost
     */
    public function setTime(int $time): void
    {
        $this->time = $time;
    }

    /**
     * Set the default thread count
     */
    public function setThreads(int $threads): void
    {
        $this->threads = $threads;
    }
}
