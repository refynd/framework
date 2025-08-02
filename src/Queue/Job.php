<?php

namespace Refynd\Queue;

abstract class Job implements JobInterface
{
    protected array $payload = [];
    protected int $maxTries = 3;
    protected int $timeout = 60;

    public function __construct(array $payload = [])
    {
        $this->payload = $payload;
    }

    public function getName(): string
    {
        return static::class;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getMaxTries(): int
    {
        return $this->maxTries;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function failed(\Exception $exception): void
    {
        // Default implementation - can be overridden
        error_log("Job failed: " . $this->getName() . " - " . $exception->getMessage());
    }

    abstract public function handle(): void;
}
