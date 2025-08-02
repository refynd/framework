<?php

namespace Refynd\Queue;

class QueueWorker
{
    private QueueInterface $queue;
    private bool $shouldStop = false;

    public function __construct(QueueInterface $queue)
    {
        $this->queue = $queue;
    }

    public function work(string $queueName = 'default', int $sleep = 3): void
    {
        while (!$this->shouldStop) {
            $job = $this->queue->pop($queueName);

            if ($job === null) {
                sleep($sleep);
                continue;
            }

            $this->process($job);
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    private function process(QueuedJob $queuedJob): void
    {
        try {
            $job = $queuedJob->job;
            $queuedJob->increment();

            // Set up timeout handling
            if ($job instanceof Job) {
                set_time_limit($job->getTimeout());
            }

            // Execute the job
            $job->handle();

            echo "Job completed: " . $job->getName() . "\n";

        } catch (\Exception $exception) {
            $this->handleFailedJob($queuedJob, $exception);
        }
    }

    private function handleFailedJob(QueuedJob $queuedJob, \Exception $exception): void
    {
        $job = $queuedJob->job;

        // Check if we should retry
        $maxTries = $job instanceof Job ? $job->getMaxTries() : 3;

        if ($queuedJob->shouldRetry($maxTries)) {
            // Re-queue for retry after delay
            $queuedJob->availableAt = time() + (60 * $queuedJob->attempts); // Exponential backoff
            $this->queue->push($job, $queuedJob->queue);
            echo "Job retry scheduled: " . $job->getName() . " (attempt {$queuedJob->attempts})\n";
        } else {
            // Mark as failed
            $this->queue->failed($queuedJob, $exception);
            $job->failed($exception);
            echo "Job failed permanently: " . $job->getName() . " - " . $exception->getMessage() . "\n";
        }
    }
}
