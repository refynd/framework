<?php

namespace Refynd\Http;

use Closure;
use Refynd\Bootstrap\Engine;

class RouteFacade
{
    private static ?Router $router = null;

    private static function getRouter(): Router
    {
        if (self::$router === null) {
            self::$router = Engine::container()->make(Router::class);
        }

        return self::$router;
    }

    public static function get(string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->get($uri, $action);
    }

    public static function post(string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->post($uri, $action);
    }

    public static function put(string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->put($uri, $action);
    }

    public static function patch(string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->patch($uri, $action);
    }

    public static function delete(string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->delete($uri, $action);
    }

    public static function options(string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->options($uri, $action);
    }

    public static function any(string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->any($uri, $action);
    }

    public static function match(array $methods, string $uri, array|string|Closure $action): \Refynd\Http\Route
    {
        return self::getRouter()->match($methods, $uri, $action);
    }

    public static function resource(string $name, string $controller): void
    {
        self::getRouter()->resource($name, $controller);
    }

    public static function group(array $attributes, Closure $callback): void
    {
        self::getRouter()->group($attributes, $callback);
    }
}
