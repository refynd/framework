<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Hash\HashManager;
use Refynd\Hash\BcryptHasher;
use Refynd\Hash\ArgonHasher;
use Refynd\Auth\AuthManager;
use Refynd\Auth\SessionGuard;
use Refynd\Auth\DatabaseUserProvider;
use Refynd\Auth\UserProviderInterface;
use Refynd\Auth\GuardInterface;
use Refynd\Auth\StatefulGuardInterface;
use Refynd\Auth\Middleware\AuthMiddleware;
use Refynd\Auth\Middleware\GuestMiddleware;

/**
 * AuthModule - Authentication and Hashing Module
 *
 * Provides authentication, authorization, and password hashing services.
 * Integrates guards, user providers, and middleware into the application.
 */
class AuthModule extends Module
{
    /**
     * Register the module's services
     */
    public function register(Container $container): void
    {
        $this->registerHashManager($container);
        $this->registerUserProvider($container);
        $this->registerAuthManager($container);
        $this->registerGuard($container);
        $this->registerMiddleware($container);
    }

    /**
     * Register the hash manager and hashers
     */
    protected function registerHashManager(Container $container): void
    {
        // Register individual hashers
        $container->bind('hash.bcrypt', function () {
            return new BcryptHasher(12);
        });

        $container->bind('hash.argon', function () {
            return new ArgonHasher(1024, 2, 2);
        });

        // Register hash manager
        $container->bind(HashManager::class, function (Container $container) {
            $manager = new HashManager();
            $manager->extend('bcrypt', $container->make('hash.bcrypt'));
            $manager->extend('argon', $container->make('hash.argon'));
            return $manager;
        });

        $container->bind('hash', HashManager::class);
    }

    /**
     * Register the user provider
     */
    protected function registerUserProvider(Container $container): void
    {
        $container->bind(UserProviderInterface::class, function (Container $container) {
            $hasher = $container->make(HashManager::class);
            // Use a default User model class - this can be configured later
            return new DatabaseUserProvider('App\\Models\\User', $hasher);
        });

        $container->bind('auth.provider', UserProviderInterface::class);
    }

    /**
     * Register the auth manager
     */
    protected function registerAuthManager(Container $container): void
    {
        $container->bind(AuthManager::class, function (Container $container) {
            $provider = $container->make(UserProviderInterface::class);
            return new AuthManager($provider);
        });

        $container->bind('auth.manager', AuthManager::class);
    }

    /**
     * Register the authentication guard
     */
    protected function registerGuard(Container $container): void
    {
        $container->bind(GuardInterface::class, function (Container $container) {
            $auth = $container->make(AuthManager::class);
            $session = $_SESSION ?? [];
            return new SessionGuard($auth, $session);
        });

        $container->bind(StatefulGuardInterface::class, function (Container $container) {
            return $container->make(GuardInterface::class);
        });

        $container->bind('auth.guard', GuardInterface::class);
        $container->bind('auth', GuardInterface::class);
    }

    /**
     * Register authentication middleware
     */
    protected function registerMiddleware(Container $container): void
    {
        $container->bind('auth.middleware', function (Container $container) {
            $guard = $container->make(GuardInterface::class);
            return new AuthMiddleware($guard);
        });

        $container->bind('guest.middleware', function (Container $container) {
            $guard = $container->make(GuardInterface::class);
            return new GuestMiddleware($guard);
        });
    }

    /**
     * Boot the module's services
     */
    public function boot(): void
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get the module name
     */
    public function getName(): string
    {
        return 'auth';
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return ['database', 'validation'];
    }
}
