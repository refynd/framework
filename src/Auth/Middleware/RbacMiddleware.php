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
 * RbacMiddleware - Main RBAC Middleware
 *
 * Primary middleware for role-based access control. This middleware
 * serves as the main entry point for RBAC functionality.
 *
 * For specific role or permission checking, use:
 * - RoleMiddleware for role-based protection
 * - PermissionMiddleware for permission-based protection
 * - GateMiddleware for complex gate-based authorization
 */
class RbacMiddleware implements MiddlewareInterface
{
    protected AccessControlManager $acl;
    protected GuardInterface $guard;

    public function __construct(AccessControlManager $acl, GuardInterface $guard)
    {
        $this->acl = $acl;
        $this->guard = $guard;
    }

    /**
     * Handle an incoming request with RBAC protection
     */
    public function handle(Request $request, Closure $next, string $type = 'authenticated', string ...$params): Response
    {
        $user = $this->guard->user();

        if (!$user) {
            return $this->handleUnauthorized($request);
        }

        // Check access based on type
        switch ($type) {
            case 'role':
                return $this->checkRoles($request, $next, $user, $params);
            case 'permission':
                return $this->checkPermissions($request, $next, $user, $params);
            case 'gate':
                return $this->checkGate($request, $next, $user, $params);
            case 'authenticated':
            default:
                return $next($request);
        }
    }

    /**
     * Check role-based access
     */
    protected function checkRoles(Request $request, Closure $next, mixed $user, array $roles): Response
    {
        if (empty($roles)) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($this->acl->hasRole($user, $role)) {
                return $next($request);
            }
        }

        return $this->handleForbidden($request);
    }

    /**
     * Check permission-based access
     */
    protected function checkPermissions(Request $request, Closure $next, mixed $user, array $permissions): Response
    {
        if (empty($permissions)) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($this->acl->can($user, $permission)) {
                return $next($request);
            }
        }

        return $this->handleForbidden($request);
    }

    /**
     * Check gate-based access
     */
    protected function checkGate(Request $request, Closure $next, mixed $user, array $params): Response
    {
        $gate = $params[0] ?? null;

        if (!$gate) {
            throw new \InvalidArgumentException('Gate parameter is required');
        }

        if ($this->acl->can($user, $gate)) {
            return $next($request);
        }

        return $this->handleForbidden($request);
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
     * Handle forbidden requests (authenticated but no access)
     */
    protected function handleForbidden(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse(['error' => 'Forbidden',
                'message' => 'Access denied'], 403);
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
