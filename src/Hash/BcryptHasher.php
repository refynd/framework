<?php

namespace Refynd\Hash;

use RuntimeException;

/**
 * BcryptHasher - Bcrypt implementation of the HashInterface
 * 
 * Uses PHP's password_hash() with PASSWORD_BCRYPT algorithm.
 * Provides secure password hashing with configurable cost factors.
 */
class BcryptHasher implements HashInterface
{
    protected int $rounds;

    public function __construct(int $rounds = 12)
    {
        $this->rounds = max(4, $rounds);
    }

    /**
     * Hash the given value using bcrypt
     */
    public function make(string $value, array $options = []): string
    {
        $cost = $options['rounds'] ?? $this->rounds;

        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);

        if (!is_string($hash) || empty($hash)) {
            throw new RuntimeException('Bcrypt hashing not supported.');
        }

        return $hash;
    }

    /**
     * Verify a value against a bcrypt hash
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
        $cost = $options['rounds'] ?? $this->rounds;

        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $cost,
        ]);
    }

    /**
     * Get information about the given hash
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * Set the default cost factor
     */
    public function setRounds(int $rounds): void
    {
        $this->rounds = max(4, $rounds);
    }
}
