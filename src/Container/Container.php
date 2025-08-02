<?php

namespace Refynd\Container;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;
use RuntimeException;

/**
 * Container - Refynd's Dependency Injection Container
 *
 * A powerful and flexible container that manages object dependencies
 * and provides automatic resolution with reflection.
 */
class Container implements ContainerInterface
{
    protected array $bindings = [];
    protected array $instances = [];
    protected array $singletons = [];

    // Performance optimization caches
    protected array $reflectionCache = [];
    protected array $constructorCache = [];
    protected array $parameterCache = [];
    protected array $resolvedTypes = [];

    /**
     * Bind a type to the container
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = ['concrete' => $concrete,
            'shared' => $shared,];
    }

    /**
     * Bind a singleton to the container
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a type from the container
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * Determine if the given abstract type has been bound
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Get an entry from the container
     */
    public function get(string $id): mixed
    {
        if (!$this->has($id) && !class_exists($id)) {
            throw new RuntimeException("No binding found for [{$id}]");
        }

        return $this->resolve($id);
    }

    /**
     * Resolve the given type from the container
     */
    protected function resolve(string $abstract, array $parameters = []): mixed
    {
        // Return existing instance if singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Use cached resolved type if available and no custom parameters
        if (empty($parameters) && isset($this->resolvedTypes[$abstract])) {
            $object = $this->resolvedTypes[$abstract]();
        } else {
            $concrete = $this->getConcrete($abstract);

            // If concrete is a closure, execute it
            if ($concrete instanceof Closure) {
                $object = $concrete($this, $parameters);
            } else {
                $object = $this->build($concrete, $parameters);
            }

            // Cache the resolution for future use (only for parameter-less resolutions)
            if (empty($parameters) && !($concrete instanceof Closure)) {
                $this->cacheResolution($abstract, $concrete);
            }
        }

        // Store as singleton if needed
        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Get the concrete type for a given abstract
     */
    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Determine if the given type is shared (singleton)
     */
    protected function isShared(string $abstract): bool
    {
        return isset($this->bindings[$abstract]['shared']) &&
               $this->bindings[$abstract]['shared'] === true;
    }

    /**
     * Instantiate a concrete instance of the given type
     */
    protected function build(string $concrete, array $parameters = []): mixed
    {
        // Check reflection cache first
        if (!isset($this->reflectionCache[$concrete])) {
            $this->cacheReflectionData($concrete);
        }

        $reflectionData = $this->reflectionCache[$concrete];

        if (!$reflectionData['instantiable']) {
            throw new RuntimeException("Class [{$concrete}] is not instantiable");
        }

        if ($reflectionData['constructor'] === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies(
            $reflectionData['parameters'],
            $parameters
        );

        return $reflectionData['reflector']->newInstanceArgs($dependencies);
    }

    /**
     * Cache reflection data for a class
     */
    protected function cacheReflectionData(string $concrete): void
    {
        if (!class_exists($concrete)) {
            throw new RuntimeException("Class [{$concrete}] does not exist");
        }

        $reflector = new ReflectionClass($concrete);
        $constructor = $reflector->getConstructor();

        $this->reflectionCache[$concrete] = ['reflector' => $reflector,
            'instantiable' => $reflector->isInstantiable(),
            'constructor' => $constructor,
            'parameters' => $constructor ? $constructor->getParameters() : [],];
    }

    /**
     * Cache resolution strategy for repeated use
     */
    protected function cacheResolution(string $abstract, string $concrete): void
    {
        if (!isset($this->reflectionCache[$concrete])) {
            return; // Should not happen, but safety first
        }

        $reflectionData = $this->reflectionCache[$concrete];

        // Only cache simple resolutions (no constructor or simple dependencies)
        if ($reflectionData['constructor'] === null) {
            $this->resolvedTypes[$abstract] = fn () => new $concrete();
        } elseif (empty($reflectionData['parameters'])) {
            $this->resolvedTypes[$abstract] = fn () => $reflectionData['reflector']->newInstance();
        }
    }

    /**
     * Resolve constructor dependencies
     */
    protected function resolveDependencies(array $dependencies, array $parameters = []): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $result = $this->resolveDependency($dependency, $parameters);
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Resolve a single dependency
     */
    protected function resolveDependency(ReflectionParameter $parameter, array $parameters = []): mixed
    {
        $name = $parameter->getName();

        // Check if parameter was explicitly provided
        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        }

        $type = $parameter->getType();

        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new RuntimeException("Cannot resolve dependency [{$name}]");
        }

        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;

        // Cache type check for performance
        $cacheKey = "type_check_{$typeName}";
        if (!isset($this->parameterCache[$cacheKey])) {
            $this->parameterCache[$cacheKey] = class_exists($typeName) || interface_exists($typeName);
        }

        // If it's a class, resolve from container
        if ($this->parameterCache[$cacheKey]) {
            return $this->make($typeName);
        }

        // Use default value if available
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException("Cannot resolve dependency [{$name}] of type [{$typeName}]");
    }

    /**
     * Clear all performance caches
     */
    public function clearCaches(): void
    {
        $this->reflectionCache = [];
        $this->constructorCache = [];
        $this->parameterCache = [];
        $this->resolvedTypes = [];
    }

    /**
     * Get cache statistics for debugging
     */
    public function getCacheStats(): array
    {
        return ['reflection_cache_size' => count($this->reflectionCache),
            'constructor_cache_size' => count($this->constructorCache),
            'parameter_cache_size' => count($this->parameterCache),
            'resolved_types_size' => count($this->resolvedTypes),
            'instances_count' => count($this->instances),
            'bindings_count' => count($this->bindings),];
    }

    /**
     * Call a method with automatic dependency injection
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            [$class, $method] = $callback;
            $reflector = new ReflectionClass($class);
            $methodReflector = $reflector->getMethod($method);

            $instance = is_object($class) ? $class : $this->make($class);

            $dependencies = $this->resolveDependencies(
                $methodReflector->getParameters(),
                $parameters
            );

            return $methodReflector->invokeArgs($instance, $dependencies);
        }

        if ($callback instanceof Closure) {
            $reflector = new \ReflectionFunction($callback);
            $dependencies = $this->resolveDependencies(
                $reflector->getParameters(),
                $parameters
            );

            return $reflector->invokeArgs($dependencies);
        }

        throw new RuntimeException('Invalid callback provided');
    }
}
