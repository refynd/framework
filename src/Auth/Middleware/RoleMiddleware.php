<?php

namespace Refynd\Auth\Middleware;

use Refynd\Auth\AccessControlManager;
use Refynd\Auth\GuardInterface;
use Refynd\Http\Middleware\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Closure;

/**
 * RoleMiddleware - Protects routes requiring specific roles
 *
 * Ensures that only users with required roles can access protected routes.
 * Works in conjunction with AuthMiddleware for comprehensive access control.
 */
class RoleMiddleware implements MiddlewareInterface
{
    protected AccessControlManager $acl;
    protected GuardInterface $guard;

    public function __construct(AccessControlManager $acl, GuardInterface $guard)
    {
        $this->acl = $acl;
        $this->guard = $guard;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $this->guard->user();

        if (!$user) {
            return $this->handleUnauthorized($request);
        }

        if (!$this->hasRequiredRole($user, $roles)) {
            return $this->handleForbidden($request);
        }

        return $next($request);
    }

    /**
     * Check if user has any of the required roles
     */
    protected function hasRequiredRole(mixed $user, array $roles): bool
    {
        if (empty($roles)) {
            return true;
        }

        foreach ($roles as $role) {
            if ($this->acl->hasRole($user, $role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle unauthorized requests (not authenticated)
     */
    protected function handleUnauthorized(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse(['error' => 'Unauthenticated',
                'message' => 'Authentication required'], 401);
        }

        return new Response('Unauthorized', 401);
    }

    /**
     * Handle forbidden requests (authenticated but no role)
     */
    protected function handleForbidden(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse(['error' => 'Forbidden',
                'message' => 'Insufficient privileges'], 403);
        }

        return new Response('Forbidden', 403);
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
}
