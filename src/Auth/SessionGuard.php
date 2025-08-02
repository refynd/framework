<?php

namespace Refynd\Auth;

/**
 * SessionGuard - Session-based authentication guard
 * 
 * Handles authentication using sessions and cookies.
 * Supports "remember me" functionality and user persistence.
 */
class SessionGuard implements StatefulGuardInterface
{
    protected AuthManager $auth;
    protected array $session;
    protected ?AuthenticatableInterface $lastAttempted = null;

    public function __construct(AuthManager $auth, array &$session = [])
    {
        $this->auth = $auth;
        $this->session = &$session;
        $this->auth->setSession($this->session);
    }

    /**
     * Determine if the current user is authenticated
     */
    public function check(): bool
    {
        return $this->auth->check();
    }

    /**
     * Determine if the current user is a guest
     */
    public function guest(): bool
    {
        return $this->auth->guest();
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?AuthenticatableInterface
    {
        if ($user = $this->auth->user()) {
            return $user;
        }

        // Try to authenticate via remember token
        return $this->auth->viaRemember();
    }

    /**
     * Get the ID for the currently authenticated user
     */
    public function id(): mixed
    {
        return $this->auth->id();
    }

    /**
     * Validate a user's credentials
     */
    public function validate(array $credentials): bool
    {
        $this->lastAttempted = $this->auth->getProvider()->retrieveByCredentials($credentials);

        return $this->auth->validate($credentials);
    }

    /**
     * Attempt to authenticate a user using the given credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $this->lastAttempted = $this->auth->getProvider()->retrieveByCredentials($credentials);

        if ($this->auth->attempt($credentials, $remember)) {
            $this->updateSession();
            return true;
        }

        return false;
    }

    /**
     * Log a user into the application without sessions or cookies
     */
    public function once(array $credentials): bool
    {
        if ($this->validate($credentials)) {
            $this->auth->setUser($this->lastAttempted);
            return true;
        }

        return false;
    }

    /**
     * Log a user into the application
     */
    public function login(AuthenticatableInterface $user, bool $remember = false): void
    {
        $this->auth->login($user, $remember);
        $this->updateSession();
    }

    /**
     * Log the user out of the application
     */
    public function logout(): void
    {
        $this->auth->logout();
        $this->clearSession();
    }

    /**
     * Set the current user
     */
    public function setUser(AuthenticatableInterface $user): void
    {
        $this->auth->setUser($user);
    }

    /**
     * Attempt to authenticate using the remember token
     */
    public function viaRemember(): ?AuthenticatableInterface
    {
        return $this->auth->viaRemember();
    }

    /**
     * Get the last user we attempted to authenticate
     */
    public function getLastAttempted(): ?AuthenticatableInterface
    {
        return $this->lastAttempted;
    }

    /**
     * Update the session with authentication data
     */
    protected function updateSession(): void
    {
        $this->session = $this->auth->getSession();
    }

    /**
     * Clear authentication data from session
     */
    protected function clearSession(): void
    {
        $this->session = [];
        $this->auth->setSession($this->session);
    }

    /**
     * Get the auth manager
     */
    public function getAuth(): AuthManager
    {
        return $this->auth;
    }

    /**
     * Get the user provider
     */
    public function getProvider(): UserProviderInterface
    {
        return $this->auth->getProvider();
    }
}
