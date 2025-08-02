<?php

namespace Refynd\Http;

use Closure;

class Route
{
    private array $methods;
    private string $uri;
    private array|string|Closure $action;
    private array $middleware = [];
    private array $parameters = [];
    private ?string $name = null;
    private array $where = [];

    public function __construct(array $methods, string $uri, array|string|Closure $action)
    {
        $this->methods = $methods;
        $this->uri = $uri;
        $this->action = $action;
    }

    public function middleware(array|string $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function where(array|string $name, ?string $expression = null): self
    {
        if (is_array($name)) {
            $this->where = array_merge($this->where, $name);
        } else {
            $this->where[$name] = $expression;
        }
        return $this;
    }

    public function matches(string $uri): bool
    {
        $pattern = $this->getCompiledPattern();
        return (bool) preg_match($pattern, $uri);
    }

    public function extractParameters(string $uri): array
    {
        $pattern = $this->getCompiledPattern();
        
        if (!preg_match($pattern, $uri, $matches)) {
            return [];
        }

        $parameters = [];
        $parameterNames = $this->getParameterNames();
        
        for ($i = 1; $i < count($matches); $i++) {
            if (isset($parameterNames[$i - 1])) {
                $parameters[$parameterNames[$i - 1]] = $matches[$i];
            }
        }

        return $parameters;
    }

    private function getCompiledPattern(): string
    {
        $pattern = $this->uri;
        
        // Replace route parameters {param} with regex patterns
        $pattern = preg_replace_callback('/\{([^}]+)\}/', function ($matches) {
            $paramName = $matches[1];
            
            // Check if parameter has custom regex
            if (isset($this->where[$paramName])) {
                return '(' . $this->where[$paramName] . ')';
            }
            
            // Default parameter pattern (everything except forward slash)
            return '([^/]+)';
        }, $pattern);
        
        // Escape other regex characters
        $pattern = str_replace('/', '\/', $pattern);
        
        return '/^' . $pattern . '$/';
    }

    private function getParameterNames(): array
    {
        preg_match_all('/\{([^}]+)\}/', $this->uri, $matches);
        return $matches[1];
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAction(): array|string|Closure
    {
        return $this->action;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getWhere(): array
    {
        return $this->where;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function url(array $parameters = []): string
    {
        $url = $this->uri;
        
        foreach ($parameters as $key => $value) {
            $url = str_replace('{' . $key . '}', $value, $url);
        }
        
        return $url;
    }

    public function hasParameter(string $name): bool
    {
        return strpos($this->uri, '{' . $name . '}') !== false;
    }

    public function getRequiredParameters(): array
    {
        return $this->getParameterNames();
    }
}
