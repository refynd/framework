<?php

namespace Refynd\Auth\Middleware;

use Refynd\Auth\GuardInterface;
use Refynd\Http\Middleware\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Closure;

/**
 * GuestMiddleware - Protects routes requiring no authentication
 * 
 * Ensures that only guests (unauthenticated users) can access certain routes.
 * Redirects authenticated users to a specified location (typically home/dashboard).
 */
class GuestMiddleware implements MiddlewareInterface
{
    protected GuardInterface $guard;
    protected string $redirectTo = '/dashboard';

    public function __construct(GuardInterface $guard, string $redirectTo = '/dashboard')
    {
        $this->guard = $guard;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->guard->check()) {
            return new RedirectResponse($this->redirectTo);
        }

        return $next($request);
    }

    /**
     * Set the redirect path for authenticated users
     */
    public function setRedirectTo(string $path): self
    {
        $this->redirectTo = $path;
        return $this;
    }
}
