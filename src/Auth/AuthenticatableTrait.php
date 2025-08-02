<?php

namespace Refynd\Auth;

/**
 * AuthenticatableTrait - Default implementation for authenticatable entities
 * 
 * Provides default implementations of the AuthenticatableInterface methods
 * that can be used by user models.
 */
trait AuthenticatableTrait
{
    /**
     * Get the unique identifier for the user
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * Get the name of the unique identifier for the user
     */
    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName() ?? 'id';
    }

    /**
     * Get the password for the user
     */
    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    /**
     * Get the "remember me" token value
     */
    public function getRememberToken(): ?string
    {
        return $this->{$this->getRememberTokenName()};
    }

    /**
     * Set the "remember me" token value
     */
    public function setRememberToken(string $value): void
    {
        $this->{$this->getRememberTokenName()} = $value;
    }

    /**
     * Get the column name for the "remember me" token
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
