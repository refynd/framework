<?php

namespace Refynd\Auth;

/**
 * UserProviderInterface - Contract for user providers
 * 
 * Defines the interface for retrieving users from various storage systems
 * (database, API, LDAP, etc.) for authentication purposes.
 */
interface UserProviderInterface
{
    /**
     * Retrieve a user by their unique identifier
     */
    public function retrieveById(mixed $identifier): ?AuthenticatableInterface;

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     */
    public function retrieveByToken(mixed $identifier, string $token): ?AuthenticatableInterface;

    /**
     * Update the "remember me" token for the given user
     */
    public function updateRememberToken(AuthenticatableInterface $user, string $token): void;

    /**
     * Retrieve a user by the given credentials
     */
    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface;

    /**
     * Validate a user against the given credentials
     */
    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool;

    /**
     * Rehash the user's password if required
     */
    public function rehashPasswordIfRequired(AuthenticatableInterface $user, array $credentials, bool $force = false): void;
}
