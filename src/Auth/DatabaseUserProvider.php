<?php

namespace Refynd\Auth;

use Refynd\Hash\HashInterface;
use Refynd\Database\Model;

/**
 * DatabaseUserProvider - Database implementation of UserProviderInterface
 *
 * Retrieves users from a database using the Refynd ORM.
 * Handles credential validation and password rehashing.
 */
class DatabaseUserProvider implements UserProviderInterface
{
    protected string $model;
    protected HashInterface $hasher;

    public function __construct(string $model, HashInterface $hasher)
    {
        $this->model = $model;
        $this->hasher = $hasher;
    }

    /**
     * Retrieve a user by their unique identifier
     */
    public function retrieveById(mixed $identifier): ?AuthenticatableInterface
    {
        $modelClass = $this->model;

        return $modelClass::where('id', $identifier)->first();
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     */
    public function retrieveByToken(mixed $identifier, string $token): ?AuthenticatableInterface
    {
        $modelClass = $this->model;

        $user = $modelClass::where('id', $identifier)->first();

        if (!$user) {
            return null;
        }

        $rememberToken = $user->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token) ? $user : null;
    }

    /**
     * Update the "remember me" token for the given user
     */
    public function updateRememberToken(AuthenticatableInterface $user, string $token): void
    {
        $user->setRememberToken($token);

        if ($user instanceof Model) {
            $user->save();
        }
    }

    /**
     * Retrieve a user by the given credentials
     */
    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => !str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return null;
        }

        $modelClass = $this->model;
        $query = $modelClass::query();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || str_contains($key, '*')) {
                $query->where($key, 'like', $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Validate a user against the given credentials
     */
    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? '';

        return $this->hasher->check($plain, $user->getAuthPassword());
    }

    /**
     * Rehash the user's password if required
     */
    public function rehashPasswordIfRequired(AuthenticatableInterface $user, array $credentials, bool $force = false): void
    {
        if (!$force && !$this->hasher->needsRehash($user->getAuthPassword())) {
            return;
        }

        if ($user instanceof Model) {
            $user->setAttribute('password', $this->hasher->make($credentials['password']));
            $user->save();
        }
    }

    /**
     * Create a new instance of the model
     */
    public function createModel(): AuthenticatableInterface
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class();
    }

    /**
     * Gets the hasher implementation
     */
    public function getHasher(): HashInterface
    {
        return $this->hasher;
    }
}
