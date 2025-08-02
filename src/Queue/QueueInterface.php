<?php

namespace Refynd\Queue;

interface QueueInterface
{
    public function push(JobInterface $job, string $queue = 'default'): bool;
    public function pop(string $queue = 'default'): ?QueuedJob;
    public function size(string $queue = 'default'): int;
    public function clear(string $queue = 'default'): bool;
    public function failed(QueuedJob $job, \Exception $exception): void;
    public function retry(string $id): bool;
}
