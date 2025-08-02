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
 * GateMiddleware - Flexible gate-based authorization
 *
 * Allows complex authorization logic using gates and policies
 * for fine-grained access control.
 */
class GateMiddleware implements MiddlewareInterface
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
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $user = $this->guard->user();

        if (!$user) {
            return $this->handleUnauthorized($request);
        }

        $gate = $params[0] ?? null;
        $resource = $params[1] ?? null;

        if (!$gate) {
            throw new \InvalidArgumentException('Gate parameter is required');
        }

        $resourceObject = $resource ? $this->resolveResource($resource, $request) : null;

        if (!$this->acl->can($user, $gate, $resourceObject)) {
            return $this->handleForbidden($request);
        }

        return $next($request);
    }

    /**
     * Resolve resource from string identifier
     */
    protected function resolveResource(string $resource, Request $request): mixed
    {
        // Try to resolve from route parameters
        $routeParams = $request->attributes->all();

        if (isset($routeParams[$resource])) {
            return $routeParams[$resource];
        }

        return $resource;
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
                'message' => 'Access denied by gate'], 403);
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
