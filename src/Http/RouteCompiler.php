<?php

namespace Refynd\Http;

/**
 * RouteCompiler - High-Performance Route Compilation
 *
 * Compiles routes into optimized patterns for faster matching
 * and reduces runtime overhead for route resolution.
 */
class RouteCompiler
{
    private const REGEX_DELIMITER = '#';
    private const VARIABLE_REGEX = '\{(\w+)(\:[^}]*)?\}';

    protected array $compiledRoutes = [];
    protected array $staticRoutes = [];
    protected array $dynamicRoutes = [];

    /**
     * Compile a route into an optimized pattern
     */
    public function compile(Route $route): array
    {
        $uri = $route->getUri();
        $methods = $route->getMethods();

        // Generate a cache key
        $cacheKey = $this->generateCacheKey($methods, $uri);

        if (isset($this->compiledRoutes[$cacheKey])) {
            return $this->compiledRoutes[$cacheKey];
        }

        $compiled = $this->doCompilation($uri);
        $compiled['route'] = $route;
        $compiled['methods'] = $methods;

        $this->compiledRoutes[$cacheKey] = $compiled;

        // Separate static and dynamic routes for faster lookup
        if ($compiled['static']) {
            foreach ($methods as $method) {
                $this->staticRoutes[$method][$uri] = $compiled;
            }
        } else {
            foreach ($methods as $method) {
                $this->dynamicRoutes[$method][] = $compiled;
            }
        }

        return $compiled;
    }

    /**
     * Find a matching route for the given method and URI
     */
    public function match(string $method, string $uri): ?array
    {
        // Check static routes first (fastest)
        if (isset($this->staticRoutes[$method][$uri])) {
            return ['route' => $this->staticRoutes[$method][$uri]['route'],
                'parameters' => [],];
        }

        // Check dynamic routes
        if (isset($this->dynamicRoutes[$method])) {
            foreach ($this->dynamicRoutes[$method] as $compiled) {
                if (preg_match($compiled['regex'], $uri, $matches)) {
                    $parameters = [];
                    foreach ($compiled['variables'] as $i => $variable) {
                        if (isset($matches[$i + 1])) {
                            $parameters[$variable] = $matches[$i + 1];
                        }
                    }

                    return ['route' => $compiled['route'],
                        'parameters' => $parameters,];
                }
            }
        }

        return null;
    }

    /**
     * Perform the actual route compilation
     */
    protected function doCompilation(string $uri): array
    {
        // Handle root route
        if ($uri === '/') {
            return ['static' => true,
                'regex' => null,
                'variables' => [],
                'tokens' => ['/'],];
        }

        // Check if route is static (no parameters)
        if (strpos($uri, '{') === false) {
            return ['static' => true,
                'regex' => null,
                'variables' => [],
                'tokens' => [$uri],];
        }

        // Compile dynamic route
        return $this->compileDynamicRoute($uri);
    }

    /**
     * Compile a dynamic route with parameters
     */
    protected function compileDynamicRoute(string $uri): array
    {
        $variables = [];
        $tokens = [];

        // Extract variables and build regex
        $regex = preg_replace_callback(
            '/' . self::VARIABLE_REGEX . '/',
            function ($matches) use (&$variables, &$tokens) {
                $variable = $matches[1];
                $constraint = isset($matches[2]) ? substr($matches[2], 1) : '[^/]+';

                $variables[] = $variable;
                $tokens[] = $variable;

                return '(' . $constraint . ')';
            },
            $uri
        );

        // Add delimiters and anchors
        $regex = self::REGEX_DELIMITER . '^' . $regex . '$' . self::REGEX_DELIMITER;

        return ['static' => false,
            'regex' => $regex,
            'variables' => $variables,
            'tokens' => $tokens,];
    }

    /**
     * Generate a cache key for the route
     */
    protected function generateCacheKey(array $methods, string $uri): string
    {
        return md5(implode('|', $methods) . ':' . $uri);
    }

    /**
     * Get compilation statistics
     */
    public function getStats(): array
    {
        $staticCount = 0;
        $dynamicCount = 0;

        foreach ($this->staticRoutes as $routes) {
            $staticCount += count($routes);
        }

        foreach ($this->dynamicRoutes as $routes) {
            $dynamicCount += count($routes);
        }

        return ['total_compiled' => count($this->compiledRoutes),
            'static_routes' => $staticCount,
            'dynamic_routes' => $dynamicCount,
            'memory_usage' => memory_get_usage(),];
    }

    /**
     * Clear compilation cache
     */
    public function clearCache(): void
    {
        $this->compiledRoutes = [];
        $this->staticRoutes = [];
        $this->dynamicRoutes = [];
    }
}
