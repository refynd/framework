<?php

namespace Refynd\Http;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Refynd\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{
    private Container $container;
    private array $routes = [];
    private array $middleware = []; // @phpstan-ignore-line Reserved for future middleware registration
    private array $routeGroups = []; // @phpstan-ignore-line Reserved for future route grouping
    private string $currentGroupPrefix = '';
    private array $currentGroupMiddleware = [];
    private ?RouteCompiler $compiler = null;
    private bool $compilationEnabled = true;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->compiler = new RouteCompiler();
    }

    public function get(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function options(string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    public function any(string $uri, array|string|Closure $action): Route
    {
        $methods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        return $this->addRoute($methods, $uri, $action);
    }

    public function match(array $methods, string $uri, array|string|Closure $action): Route
    {
        return $this->addRoute($methods, $uri, $action);
    }

    public function resource(string $name, string $controller): void
    {
        $this->get($name, [$controller, 'index']);
        $this->get($name . '/create', [$controller, 'create']);
        $this->post($name, [$controller, 'store']);
        $this->get($name . '/{id}', [$controller, 'show']);
        $this->get($name . '/{id}/edit', [$controller, 'edit']);
        $this->put($name . '/{id}', [$controller, 'update']);
        $this->patch($name . '/{id}', [$controller, 'update']);
        $this->delete($name . '/{id}', [$controller, 'destroy']);
    }

    public function group(array $attributes, Closure $callback): void
    {
        $previousPrefix = $this->currentGroupPrefix;
        $previousMiddleware = $this->currentGroupMiddleware;

        $this->currentGroupPrefix = $previousPrefix . ($attributes['prefix'] ?? '');
        $this->currentGroupMiddleware = array_merge(
            $previousMiddleware,
            $attributes['middleware'] ?? []
        );

        $callback($this);

        $this->currentGroupPrefix = $previousPrefix;
        $this->currentGroupMiddleware = $previousMiddleware;
    }

    private function addRoute(array|string $methods, string $uri, array|string|Closure $action): Route
    {
        $methods = (array) $methods;
        $uri = $this->currentGroupPrefix . '/' . ltrim($uri, '/');
        $uri = rtrim($uri, '/') ?: '/';

        $route = new Route($methods, $uri, $action);
        $route->middleware($this->currentGroupMiddleware);

        foreach ($methods as $method) {
            $this->routes[$method][] = $route;
        }

        // Compile the route for performance
        if ($this->compilationEnabled && $this->compiler) {
            $this->compiler->compile($route);
        }

        return $route;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $uri = $request->getPathInfo();

        // Handle HEAD requests as GET
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        // Use compiled routes for faster matching
        if ($this->compilationEnabled && $this->compiler) {
            $match = $this->compiler->match($method, $uri);
            if ($match) {
                return $this->runRouteFromMatch($request, $match['route'], $match['parameters']);
            }
        } else {
            // Fallback to legacy route matching
            if (!isset($this->routes[$method])) {
                return $this->createNotFoundResponse();
            }

            foreach ($this->routes[$method] as $route) {
                if ($route->matches($uri)) {
                    return $this->runRoute($request, $route);
                }
            }
        }

        return $this->createNotFoundResponse();
    }

    private function runRoute(Request $request, Route $route): Response
    {
        // Extract route parameters
        $parameters = $route->extractParameters($request->getPathInfo());

        return $this->runRouteFromMatch($request, $route, $parameters);
    }

    private function runRouteFromMatch(Request $request, Route $route, array $parameters): Response
    {
        // Run middleware
        $response = $this->runMiddleware($request, $route, function ($request) use ($route, $parameters) {
            return $this->callAction($request, $route, $parameters);
        });

        return $response;
    }

    private function runMiddleware(Request $request, Route $route, Closure $then): Response
    {
        $middleware = $route->getMiddleware();

        if (empty($middleware)) {
            return $then($request);
        }

        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function ($request) use ($middleware, $next) {
                    $middlewareInstance = $this->container->make($middleware);
                    return $middlewareInstance->handle($request, $next);
                };
            },
            $then
        );

        return $pipeline($request);
    }

    private function callAction(Request $request, Route $route, array $parameters): Response
    {
        $action = $route->getAction();

        if ($action instanceof Closure) {
            return $this->callClosure($action, $parameters);
        }

        if (is_array($action)) {
            [$controller, $method] = $action;
            return $this->callController($controller, $method, $parameters);
        }

        if (is_string($action)) {
            if (strpos($action, '@') !== false) {
                [$controller, $method] = explode('@', $action, 2);
                return $this->callController($controller, $method, $parameters);
            }
        }

        throw new \InvalidArgumentException('Invalid route action');
    }

    private function callClosure(Closure $closure, array $parameters): Response
    {
        $result = $this->container->call($closure, $parameters);
        return $this->prepareResponse($result);
    }

    private function callController(string $controller, string $method, array $parameters): Response
    {
        $controllerInstance = $this->container->make($controller);

        $reflectionMethod = new ReflectionMethod($controllerInstance, $method);
        $methodParameters = $this->resolveMethodParameters($reflectionMethod, $parameters);

        $result = $reflectionMethod->invokeArgs($controllerInstance, $methodParameters);
        return $this->prepareResponse($result);
    }

    private function resolveMethodParameters(ReflectionMethod $method, array $routeParameters): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // Check if it's a route parameter
            if (isset($routeParameters[$name])) {
                $parameters[] = $routeParameters[$name];
                continue;
            }

            // Try to resolve from container
            if ($type && $type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $parameters[] = $this->container->make($type->getName());
                continue;
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $parameters[] = $parameter->getDefaultValue();
                continue;
            }

            throw new \InvalidArgumentException("Cannot resolve parameter: {$name}");
        }

        return $parameters;
    }

    private function prepareResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return new Response(
                json_encode($result),
                200,
                ['Content-Type' => 'application/json']
            );
        }

        return new Response((string) $result);
    }

    private function createNotFoundResponse(): Response
    {
        return new Response('Not Found', 404);
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Enable or disable route compilation
     */
    public function setCompilationEnabled(bool $enabled): void
    {
        $this->compilationEnabled = $enabled;
    }

    /**
     * Get route compiler instance
     */
    public function getCompiler(): ?RouteCompiler
    {
        return $this->compiler;
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = ['compilation_enabled' => $this->compilationEnabled,
            'total_routes' => 0,];

        foreach ($this->routes as $method => $routes) {
            $stats['total_routes'] += count($routes);
            $stats["routes_{$method}"] = count($routes);
        }

        if ($this->compiler) {
            $stats['compiler'] = $this->compiler->getStats();
        }

        return $stats;
    }

    /**
     * Clear route compilation cache
     */
    public function clearCompilationCache(): void
    {
        if ($this->compiler) {
            $this->compiler->clearCache();
        }
    }
}
