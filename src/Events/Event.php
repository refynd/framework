<?php

namespace Refynd\Events;

use Refynd\Bootstrap\Engine;

class Event
{
    private static ?EventDispatcher $dispatcher = null;

    private static function getDispatcher(): EventDispatcher
    {
        if (self::$dispatcher === null) {
            self::$dispatcher = Engine::container()->make(EventDispatcher::class);
        }

        return self::$dispatcher;
    }

    public static function listen(string|array $events, string|array|\Closure $listener): void
    {
        self::getDispatcher()->listen($events, $listener);
    }

    public static function subscribe(string $subscriber): void
    {
        self::getDispatcher()->subscribe($subscriber);
    }

    public static function dispatch(string|object $event, array $payload = []): array
    {
        return self::getDispatcher()->dispatch($event, $payload);
    }

    public static function fire(string|object $event, array $payload = []): array
    {
        return self::getDispatcher()->fire($event, $payload);
    }

    public static function until(string|object $event, array $payload = []): mixed
    {
        return self::getDispatcher()->until($event, $payload);
    }

    public static function push(string $event, array $payload = []): void
    {
        self::getDispatcher()->push($event, $payload);
    }

    public static function flush(string $event): void
    {
        self::getDispatcher()->flush($event);
    }

    public static function forget(string $event): void
    {
        self::getDispatcher()->forget($event);
    }

    public static function forgetPushed(): void
    {
        self::getDispatcher()->forgetPushed();
    }

    public static function hasListeners(string $event): bool
    {
        return self::getDispatcher()->hasListeners($event);
    }

    public static function getListenerCount(string $event): int
    {
        return self::getDispatcher()->getListenerCount($event);
    }
}
