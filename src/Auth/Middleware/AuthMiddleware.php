<?php

namespace Refynd\Auth\Middleware;

use Refynd\Auth\GuardInterface;
use Refynd\Http\Middleware\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Closure;

/**
 * AuthMiddleware - Protects routes requiring authentication
 * 
 * Ensures that only authenticated users can access protected routes.
 * Redirects guests to login or returns 401 for API requests.
 */
class AuthMiddleware implements MiddlewareInterface
{
    protected GuardInterface $guard;
    protected string $redirectTo = '/login';

    public function __construct(GuardInterface $guard, string $redirectTo = '/login')
    {
        $this->guard = $guard;
        $this->redirectTo = $redirectTo;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->guard->guest()) {
            return $this->handleUnauthenticated($request);
        }

        return $next($request);
    }

    /**
     * Handle unauthenticated requests
     */
    protected function handleUnauthenticated(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse(['error' => 'Unauthenticated'], 401);
        }

        return $this->redirectTo($request);
    }

    /**
     * Determine if the request expects a JSON response
     */
    protected function expectsJson(Request $request): bool
    {
        $accept = $request->headers->get('Accept', '');
        $contentType = $request->headers->get('Content-Type', '');
        
        return str_contains($accept, 'application/json') || 
               str_contains($contentType, 'application/json') ||
               $request->isXmlHttpRequest();
    }

    /**
     * Redirect to login page
     */
    protected function redirectTo(Request $request): RedirectResponse
    {
        $location = $this->redirectTo . '?redirect=' . urlencode($request->getPathInfo());
        
        return new RedirectResponse($location);
    }

    /**
     * Set the redirect path for guests
     */
    public function setRedirectTo(string $path): self
    {
        $this->redirectTo = $path;
        return $this;
    }
}
