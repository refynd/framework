<?php

namespace Refynd\Hash;

use InvalidArgumentException;

/**
 * HashManager - Manages multiple hashing drivers
 *
 * Provides a unified interface for working with different hashing algorithms.
 * Supports bcrypt, argon2, and custom hashers with driver switching.
 */
class HashManager implements HashInterface
{
    protected array $drivers = [];
    protected string $defaultDriver;

    public function __construct(string $defaultDriver = 'bcrypt')
    {
        $this->defaultDriver = $defaultDriver;
        $this->createDefaultDrivers();
    }

    /**
     * Create the default built-in drivers
     */
    protected function createDefaultDrivers(): void
    {
        $this->drivers['bcrypt'] = new BcryptHasher();

        if (defined('PASSWORD_ARGON2ID')) {
            $this->drivers['argon'] = new ArgonHasher();
            $this->drivers['argon2id'] = new ArgonHasher();
        }
    }

    /**
     * Hash the given value using the default driver
     */
    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    /**
     * Verify a value against a hash using the default driver
     */
    public function check(string $value, string $hashedValue): bool
    {
        return $this->driver()->check($value, $hashedValue);
    }

    /**
     * Check if the given hash needs to be rehashed
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    /**
     * Get information about the given hash
     */
    public function info(string $hashedValue): array
    {
        return $this->driver()->info($hashedValue);
    }

    /**
     * Get a driver instance
     */
    public function driver(?string $driver = null): HashInterface
    {
        $driver = $driver ?: $this->defaultDriver;

        if (!isset($this->drivers[$driver])) {
            throw new InvalidArgumentException("Hash driver [{$driver}] not supported.");
        }

        return $this->drivers[$driver];
    }

    /**
     * Add a custom driver
     */
    public function extend(string $driver, HashInterface $hasher): void
    {
        $this->drivers[$driver] = $hasher;
    }

    /**
     * Set the default driver
     */
    public function setDefaultDriver(string $driver): void
    {
        $this->defaultDriver = $driver;
    }

    /**
     * Get the default driver name
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Get all available drivers
     */
    public function getDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Check if a driver is available
     */
    public function hasDriver(string $driver): bool
    {
        return isset($this->drivers[$driver]);
    }
}
