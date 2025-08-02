<?php

namespace Refynd\Hash;

/**
 * HashInterface - Defines the contract for hashing implementations
 *
 * Provides a consistent interface for password hashing and verification
 * across different hashing algorithms and drivers.
 */
interface HashInterface
{
    /**
     * Hash the given value
     */
    public function make(string $value, array $options = []): string;

    /**
     * Verify a value against a hash
     */
    public function check(string $value, string $hashedValue): bool;

    /**
     * Check if the given hash needs to be rehashed
     */
    public function needsRehash(string $hashedValue, array $options = []): bool;

    /**
     * Get information about the given hash
     */
    public function info(string $hashedValue): array;
}
