<?php

namespace Refynd\Queue;

class DatabaseQueue implements QueueInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->createTables();
    }

    public function push(JobInterface $job, string $queue = 'default'): bool
    {
        $id = uniqid('job_', true);
        $queuedJob = new QueuedJob($id, $queue, $job);
        
        $sql = "INSERT INTO jobs (id, queue, payload, attempts, created_at, available_at) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $queuedJob->id,
            $queuedJob->queue,
            json_encode($queuedJob->toArray()),
            $queuedJob->attempts,
            $queuedJob->createdAt,
            $queuedJob->availableAt
        ]);
    }

    public function pop(string $queue = 'default'): ?QueuedJob
    {
        $sql = "SELECT * FROM jobs 
                WHERE queue = ? AND available_at <= ? 
                ORDER BY created_at ASC 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$queue, time()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        // Delete the job from queue
        $deleteSql = "DELETE FROM jobs WHERE id = ?";
        $deleteStmt = $this->pdo->prepare($deleteSql);
        $deleteStmt->execute([$row['id']]);
        
        $payload = json_decode($row['payload'], true);
        return QueuedJob::fromArray($payload);
    }

    public function size(string $queue = 'default'): int
    {
        $sql = "SELECT COUNT(*) FROM jobs WHERE queue = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$queue]);
        return (int) $stmt->fetchColumn();
    }

    public function clear(string $queue = 'default'): bool
    {
        $sql = "DELETE FROM jobs WHERE queue = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$queue]);
    }

    public function failed(QueuedJob $job, \Exception $exception): void
    {
        $sql = "INSERT INTO failed_jobs (id, queue, payload, exception, failed_at) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $job->id,
            $job->queue,
            json_encode($job->toArray()),
            $exception->getMessage(),
            time()
        ]);
    }

    public function retry(string $id): bool
    {
        $sql = "SELECT * FROM failed_jobs WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            return false;
        }
        
        $payload = json_decode($row['payload'], true);
        $job = QueuedJob::fromArray($payload);
        
        // Re-queue the job
        $this->push($job->job, $job->queue);
        
        // Remove from failed jobs
        $deleteSql = "DELETE FROM failed_jobs WHERE id = ?";
        $deleteStmt = $this->pdo->prepare($deleteSql);
        return $deleteStmt->execute([$id]);
    }

    private function createTables(): void
    {
        $jobsTable = "
            CREATE TABLE IF NOT EXISTS jobs (
                id VARCHAR(255) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER DEFAULT 0,
                created_at INTEGER NOT NULL,
                available_at INTEGER NOT NULL
            )
        ";
        
        $failedJobsTable = "
            CREATE TABLE IF NOT EXISTS failed_jobs (
                id VARCHAR(255) PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                exception TEXT NOT NULL,
                failed_at INTEGER NOT NULL
            )
        ";
        
        $this->pdo->exec($jobsTable);
        $this->pdo->exec($failedJobsTable);
    }
}
