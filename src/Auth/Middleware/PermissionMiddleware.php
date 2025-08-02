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
 * PermissionMiddleware - Protects routes requiring specific permissions
 *
 * Ensures that only users with required permissions can access protected routes.
 * Supports complex permission logic and resource-based authorization.
 */
class PermissionMiddleware implements MiddlewareInterface
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
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $this->guard->user();

        if (!$user) {
            return $this->handleUnauthorized($request);
        }

        if (!$this->hasRequiredPermission($user, $permissions, $request)) {
            return $this->handleForbidden($request);
        }

        return $next($request);
    }

    /**
     * Check if user has any of the required permissions
     */
    protected function hasRequiredPermission(mixed $user, array $permissions, Request $request): bool
    {
        if (empty($permissions)) {
            return true;
        }

        $resource = $this->extractResource($request);

        foreach ($permissions as $permission) {
            if ($this->acl->can($user, $permission, $resource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract resource from request for policy checking
     */
    protected function extractResource(Request $request): mixed
    {
        // Try to get resource ID from route parameters
        $routeParams = $request->attributes->all();

        if (isset($routeParams['id'])) {
            return $routeParams['id'];
        }

        if (isset($routeParams['resource'])) {
            return $routeParams['resource'];
        }

        // Return request for policy to determine resource
        return $request;
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
     * Handle forbidden requests (authenticated but no permission)
     */
    protected function handleForbidden(Request $request): Response
    {
        if ($this->expectsJson($request)) {
            return new JsonResponse(['error' => 'Forbidden',
                'message' => 'Insufficient permissions'], 403);
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
