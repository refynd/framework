<?php

namespace Refynd\Queue;

class QueuedJob
{
    public string $id;
    public string $queue;
    public JobInterface $job;
    public int $attempts;
    public int $createdAt;
    public int $availableAt;

    public function __construct(
        string $id,
        string $queue,
        JobInterface $job,
        int $attempts = 0,
        ?int $createdAt = null,
        ?int $availableAt = null
    ) {
        $this->id = $id;
        $this->queue = $queue;
        $this->job = $job;
        $this->attempts = $attempts;
        $this->createdAt = $createdAt ?: time();
        $this->availableAt = $availableAt ?: time();
    }

    public function increment(): void
    {
        $this->attempts++;
    }

    public function isExpired(int $timeout): bool
    {
        return (time() - $this->createdAt) > $timeout;
    }

    public function shouldRetry(int $maxTries): bool
    {
        return $this->attempts < $maxTries;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'queue' => $this->queue,
            'job' => serialize($this->job),
            'attempts' => $this->attempts,
            'created_at' => $this->createdAt,
            'available_at' => $this->availableAt,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['id'],
            $data['queue'],
            unserialize($data['job']),
            $data['attempts'],
            $data['created_at'],
            $data['available_at']
        );
    }
}
