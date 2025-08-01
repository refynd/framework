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

    /**
     * Bind a type to the container
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];
    }

    /**
     * Bind a singleton to the container
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as a singleton
     */
    public function instance(string $abstract, $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Resolve a type from the container
     */
    public function make(string $abstract, array $parameters = [])
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
    public function get(string $id)
    {
        if (!$this->has($id) && !class_exists($id)) {
            throw new RuntimeException("No binding found for [{$id}]");
        }

        return $this->resolve($id);
    }

    /**
     * Resolve the given type from the container
     */
    protected function resolve(string $abstract, array $parameters = [])
    {
        // Return existing instance if singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        // If concrete is a closure, execute it
        if ($concrete instanceof Closure) {
            $object = $concrete($this, $parameters);
        } else {
            $object = $this->build($concrete, $parameters);
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
    protected function getConcrete(string $abstract)
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
    protected function build(string $concrete, array $parameters = [])
    {
        if (!class_exists($concrete)) {
            throw new RuntimeException("Class [{$concrete}] does not exist");
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Class [{$concrete}] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete;
        }

        $dependencies = $this->resolveDependencies(
            $constructor->getParameters(),
            $parameters
        );

        return $reflector->newInstanceArgs($dependencies);
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
    protected function resolveDependency(ReflectionParameter $parameter, array $parameters = [])
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

        // If it's a class, resolve from container
        if (class_exists($typeName) || interface_exists($typeName)) {
            return $this->make($typeName);
        }

        // Use default value if available
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException("Cannot resolve dependency [{$name}] of type [{$typeName}]");
    }

    /**
     * Call a method with automatic dependency injection
     */
    public function call($callback, array $parameters = [])
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
