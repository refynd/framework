<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Queue\DatabaseQueue;
use Refynd\Queue\QueueInterface;
use Refynd\Queue\QueueWorker;

class QueueModule extends Module
{
    private Container $container;

    public function register(Container $container): void
    {
        $this->container = $container;
        
        // Register queue driver
        $container->singleton('queue', function() {
            $pdo = $this->container->make(\PDO::class);
            return new DatabaseQueue($pdo);
        });
        
        // Register queue worker
        $container->singleton(QueueWorker::class, function() {
            $queue = $this->container->make('queue');
            return new QueueWorker($queue);
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
