<?php

namespace Refynd\Events;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use Refynd\Container\Container;

class EventDispatcher
{
    private Container $container;
    private array $listeners = [];
    private array $wildcards = [];
    private array $queuedEvents = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function listen(string|array $events, string|array|Closure $listener): void
    {
        foreach ((array) $events as $event) {
            if (str_contains($event, '*')) {
                $this->setupWildcardListener($event, $listener);
            } else {
                $this->listeners[$event][] = $this->makeListener($listener);
            }
        }
    }

    public function subscribe(string $subscriber): void
    {
        $subscriberInstance = $this->container->make($subscriber);
        
        $reflection = new ReflectionClass($subscriberInstance);
        
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Listener::class);
            
            foreach ($attributes as $attribute) {
                $listenerConfig = $attribute->newInstance();
                $eventClass = $listenerConfig->event;
                
                $this->listen($eventClass, [$subscriberInstance, $method->getName()]);
            }
        }
    }

    public function dispatch(string|object $event, array $payload = []): array
    {
        $eventName = is_object($event) ? get_class($event) : $event;
        $eventData = is_object($event) ? $event : $payload;

        $responses = [];

        // Get direct listeners
        foreach ($this->getListeners($eventName) as $listener) {
            $response = $this->callListener($listener, $eventData);
            
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        // Get wildcard listeners
        foreach ($this->wildcards as $pattern => $listeners) {
            if ($this->eventMatches($pattern, $eventName)) {
                foreach ($listeners as $listener) {
                    $response = $this->callListener($listener, $eventData);
                    
                    if ($response !== null) {
                        $responses[] = $response;
                    }
                }
            }
        }

        return $responses;
    }

    public function fire(string|object $event, array $payload = []): array
    {
        return $this->dispatch($event, $payload);
    }

    public function until(string|object $event, array $payload = []): mixed
    {
        $eventName = is_object($event) ? get_class($event) : $event;
        $eventData = is_object($event) ? $event : $payload;

        foreach ($this->getListeners($eventName) as $listener) {
            $response = $this->callListener($listener, $eventData);
            
            if ($response !== null) {
                return $response;
            }
        }

        return null;
    }

    public function push(string $event, array $payload = []): void
    {
        $this->queuedEvents[] = [$event, $payload];
    }

    public function flush(string $event): void
    {
        foreach ($this->queuedEvents as $index => $queuedEvent) {
            [$queuedEventName, $payload] = $queuedEvent;
            
            if ($queuedEventName === $event) {
                $this->dispatch($event, $payload);
                unset($this->queuedEvents[$index]);
            }
        }
    }

    public function forget(string $event): void
    {
        unset($this->listeners[$event]);
        
        foreach ($this->wildcards as $pattern => $listeners) {
            if ($this->eventMatches($pattern, $event)) {
                unset($this->wildcards[$pattern]);
            }
        }
    }

    public function forgetPushed(): void
    {
        $this->queuedEvents = [];
    }

    private function setupWildcardListener(string $event, string|array|Closure $listener): void
    {
        $this->wildcards[$event][] = $this->makeListener($listener);
    }

    private function makeListener(string|array|Closure $listener): Closure
    {
        if ($listener instanceof Closure) {
            return $listener;
        }

        return function ($event) use ($listener) {
            if (is_string($listener)) {
                return $this->container->call($listener, ['event' => $event]);
            }

            if (is_array($listener)) {
                [$class, $method] = $listener;
                
                if (is_string($class)) {
                    $class = $this->container->make($class);
                }
                
                return $this->container->call([$class, $method], ['event' => $event]);
            }

            throw new \InvalidArgumentException('Invalid event listener');
        };
    }

    private function callListener(Closure $listener, mixed $event): mixed
    {
        try {
            return $listener($event);
        } catch (\Throwable $e) {
            // Log the error or handle it as needed
            throw $e;
        }
    }

    private function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }

    private function eventMatches(string $pattern, string $eventName): bool
    {
        // Convert wildcard pattern to regex
        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\*', '.*', $regex);
        
        return (bool) preg_match("/^{$regex}$/", $eventName);
    }

    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) || $this->hasWildcardListeners($event);
    }

    private function hasWildcardListeners(string $event): bool
    {
        foreach ($this->wildcards as $pattern => $listeners) {
            if ($this->eventMatches($pattern, $event)) {
                return true;
            }
        }

        return false;
    }

    public function getListenerCount(string $event): int
    {
        $count = count($this->getListeners($event));
        
        foreach ($this->wildcards as $pattern => $listeners) {
            if ($this->eventMatches($pattern, $event)) {
                $count += count($listeners);
            }
        }
        
        return $count;
    }
}
