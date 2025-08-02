<?php

namespace Refynd\Auth;

/**
 * StatefulGuardInterface - Contract for stateful authentication guards
 *
 * Extends the basic guard with login/logout capabilities
 */
interface StatefulGuardInterface extends GuardInterface
{
    /**
     * Attempt to authenticate a user using the given credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool;

    /**
     * Log a user into the application without sessions or cookies
     */
    public function once(array $credentials): bool;

    /**
     * Log a user into the application
     */
    public function login(AuthenticatableInterface $user, bool $remember = false): void;

    /**
     * Log the user out of the application
     */
    public function logout(): void;

    /**
     * Attempt to authenticate using the remember token
     */
    public function viaRemember(): ?AuthenticatableInterface;
}
