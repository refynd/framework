<?php

namespace Refynd\Auth;

use RuntimeException;
use InvalidArgumentException;

/**
 * AuthManager - Core authentication manager
 * 
 * Handles user authentication, login/logout sessions, and "remember me" functionality.
 * Supports multiple user providers and guards.
 */
class AuthManager
{
    protected UserProviderInterface $provider;
    protected ?AuthenticatableInterface $user = null;
    protected bool $loggedOut = false;
    protected array $session = [];

    public function __construct(UserProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Attempt to authenticate a user with the given credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user && $this->provider->validateCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    /**
     * Log a user into the application
     */
    public function login(AuthenticatableInterface $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $this->queueRecallerCookie($user);
        }

        $this->setUser($user);
    }

    /**
     * Log the user out of the application
     */
    public function logout(): void
    {
        $user = $this->user();

        $this->clearUserDataFromStorage();

        if ($user && $user->getRememberToken()) {
            $this->cycleRememberToken($user);
        }

        $this->user = null;
        $this->loggedOut = true;
    }

    /**
     * Determine if the current user is authenticated
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?AuthenticatableInterface
    {
        if ($this->loggedOut) {
            return null;
        }

        if (!is_null($this->user)) {
            return $this->user;
        }

        $id = $this->session['login_user_id'] ?? null;

        if (!is_null($id)) {
            $this->user = $this->provider->retrieveById($id);
        }

        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated user
     */
    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Set the current user
     */
    public function setUser(AuthenticatableInterface $user): self
    {
        $this->user = $user;
        $this->loggedOut = false;

        return $this;
    }

    /**
     * Validate a user's credentials
     */
    public function validate(array $credentials): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return $user && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Attempt to authenticate using the remember token
     */
    public function viaRemember(): ?AuthenticatableInterface
    {
        $recaller = $this->getRecaller();

        if (!$recaller) {
            return null;
        }

        [$id, $token] = explode('|', $recaller, 2);

        $user = $this->provider->retrieveByToken($id, $token);

        if ($user) {
            $this->updateSession($user->getAuthIdentifier());
            $this->setUser($user);
        }

        return $user;
    }

    /**
     * Update the session with the given ID
     */
    protected function updateSession(mixed $id): void
    {
        $this->session['login_user_id'] = $id;
        $this->session['login_time'] = time();
    }

    /**
     * Create a new "remember me" token for the user
     */
    protected function queueRecallerCookie(AuthenticatableInterface $user): void
    {
        $token = $this->createRecallerToken();
        
        $this->provider->updateRememberToken($user, $token);
        
        $recaller = $user->getAuthIdentifier() . '|' . $token;
        $this->session['remember_token'] = $recaller;
    }

    /**
     * Create a new remember token
     */
    protected function createRecallerToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get the remember token from session/cookie
     */
    protected function getRecaller(): ?string
    {
        return $this->session['remember_token'] ?? null;
    }

    /**
     * Cycle the remember token for the user
     */
    protected function cycleRememberToken(AuthenticatableInterface $user): void
    {
        $this->provider->updateRememberToken($user, $this->createRecallerToken());
    }

    /**
     * Clear the user data from storage
     */
    protected function clearUserDataFromStorage(): void
    {
        unset($this->session['login_user_id']);
        unset($this->session['login_time']);
        unset($this->session['remember_token']);
    }

    /**
     * Get the user provider
     */
    public function getProvider(): UserProviderInterface
    {
        return $this->provider;
    }

    /**
     * Set the user provider
     */
    public function setProvider(UserProviderInterface $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Get session data (for testing/debugging)
     */
    public function getSession(): array
    {
        return $this->session;
    }

    /**
     * Set session data (for integration with session systems)
     */
    public function setSession(array $session): void
    {
        $this->session = $session;
    }
}
