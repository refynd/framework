<?php

namespace Refynd\Auth;

/**
 * AuthenticatableInterface - Contract for authenticatable entities
 * 
 * Defines the interface that user models must implement to work
 * with the authentication system.
 */
interface AuthenticatableInterface
{
    /**
     * Get the unique identifier for the user
     */
    public function getAuthIdentifier(): mixed;

    /**
     * Get the name of the unique identifier for the user
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password for the user
     */
    public function getAuthPassword(): string;

    /**
     * Get the "remember me" token value
     */
    public function getRememberToken(): ?string;

    /**
     * Set the "remember me" token value
     */
    public function setRememberToken(string $value): void;

    /**
     * Get the column name for the "remember me" token
     */
    public function getRememberTokenName(): string;
}
