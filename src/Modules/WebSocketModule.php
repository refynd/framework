<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\WebSocket\WebSocketServer;
use Refynd\WebSocket\WebSocketClient;

class WebSocketModule extends Module
{
    private Container $container; // @phpstan-ignore-line Container reserved for future module functionality

    public function register(Container $container): void
    {
        $this->container = $container;

        // Register WebSocket server
        $container->singleton(WebSocketServer::class, function () {
            $host = $_ENV['WEBSOCKET_HOST'] ?? '127.0.0.1';
            $port = (int) ($_ENV['WEBSOCKET_PORT'] ?? 8080);
            return new WebSocketServer($host, $port);
        });

        // Register WebSocket client factory
        $container->bind(WebSocketClient::class, function ($url = null) {
            $url = $url ?: ($_ENV['WEBSOCKET_URL'] ?? 'ws://127.0.0.1:8080');
            return new WebSocketClient($url);
        });
    }

    public function boot(): void
    {
        // Boot logic if needed
    }

    public function getVersion(): string
    {
        return '2.0.0';
    }
}
