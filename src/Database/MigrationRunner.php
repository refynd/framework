<?php

namespace Refynd\Database;

use PDO;
use Exception;

/**
 * MigrationRunner - Executes database migrations
 *
 * Handles running and rolling back migrations with proper
 * tracking and error handling.
 */
class MigrationRunner
{
    protected PDO $pdo;
    protected string $migrationsTable = 'migrations';
    protected string $migrationsPath;

    public function __construct(PDO $pdo, string $migrationsPath = 'database/migrations')
    {
        $this->pdo = $pdo;
        $this->migrationsPath = $migrationsPath;
        $this->ensureMigrationsTable();
    }

    /**
     * Ensure the migrations table exists
     */
    protected function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    /**
     * Run pending migrations
     */
    public function migrate(): array
    {
        $pendingMigrations = $this->getPendingMigrations();

        if (empty($pendingMigrations)) {
            return ['message' => 'No pending migrations'];
        }

        $batch = $this->getNextBatchNumber();
        $executed = [];

        foreach ($pendingMigrations as $migration) {
            try {
                $this->runMigration($migration, $batch);
                $executed[] = $migration;
                echo "Migrated: {$migration}\n";
            } catch (Exception $e) {
                echo "Failed to migrate {$migration}: " . $e->getMessage() . "\n";
                break;
            }
        }

        return ['executed' => $executed,
            'batch' => $batch];
    }

    /**
     * Rollback the last batch of migrations
     */
    public function rollback(): array
    {
        $lastBatch = $this->getLastBatch();

        if (!$lastBatch) {
            return ['message' => 'Nothing to rollback'];
        }

        $migrations = $this->getMigrationsByBatch($lastBatch);
        $rolledBack = [];

        // Rollback in reverse order
        foreach (array_reverse($migrations) as $migration) {
            try {
                $this->rollbackMigration($migration);
                $this->removeMigrationRecord($migration);
                $rolledBack[] = $migration;
                echo "Rolled back: {$migration}\n";
            } catch (Exception $e) {
                echo "Failed to rollback {$migration}: " . $e->getMessage() . "\n";
                break;
            }
        }

        return ['rolled_back' => $rolledBack,
            'batch' => $lastBatch];
    }

    /**
     * Get pending migrations
     */
    protected function getPendingMigrations(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();

        return array_diff($allMigrations, $executedMigrations);
    }

    /**
     * Get all migration files
     */
    protected function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = scandir($this->migrationsPath);
        $migrations = [];

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $migrations[] = pathinfo($file, PATHINFO_FILENAME);
            }
        }

        sort($migrations);
        return $migrations;
    }

    /**
     * Get executed migrations
     */
    protected function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable} ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get the next batch number
     */
    protected function getNextBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->migrationsTable}");
        $maxBatch = $stmt->fetchColumn();
        return $maxBatch ? $maxBatch + 1 : 1;
    }

    /**
     * Get the last batch number
     */
    protected function getLastBatch(): ?int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->migrationsTable}");
        $batch = $stmt->fetchColumn();
        return $batch ?: null;
    }

    /**
     * Get migrations by batch
     */
    protected function getMigrationsByBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare("SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY id");
        $stmt->execute([$batch]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Run a single migration
     */
    protected function runMigration(string $migration, int $batch): void
    {
        $migrationInstance = $this->loadMigration($migration);

        if (!$migrationInstance) {
            throw new Exception("Migration class not found for: {$migration}");
        }

        // Begin transaction
        $this->pdo->beginTransaction();

        try {
            $migrationInstance->up();
            $this->recordMigration($migration, $batch);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Rollback a single migration
     */
    protected function rollbackMigration(string $migration): void
    {
        $migrationInstance = $this->loadMigration($migration);

        if (!$migrationInstance) {
            throw new Exception("Migration class not found for: {$migration}");
        }

        // Begin transaction
        $this->pdo->beginTransaction();

        try {
            $migrationInstance->down();
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Load a migration class
     */
    protected function loadMigration(string $migration): ?Migration
    {
        $filePath = $this->migrationsPath . '/' . $migration . '.php';

        if (!file_exists($filePath)) {
            return null;
        }

        require_once $filePath;

        // Extract class name from filename (assuming pattern: YYYY_MM_DD_HHMMSS_migration_name)
        $parts = explode('_', $migration);
        $className = '';

        // Skip timestamp parts and build class name
        for ($i = 4; $i < count($parts); $i++) {
            $className .= ucfirst($parts[$i]);
        }

        if (class_exists($className)) {
            return new $className();
        }

        return null;
    }

    /**
     * Record a migration as executed
     */
    protected function recordMigration(string $migration, int $batch): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)");
        $stmt->execute([$migration, $batch]);
    }

    /**
     * Remove a migration record
     */
    protected function removeMigrationRecord(string $migration): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
        $stmt->execute([$migration]);
    }

    /**
     * Get migration status
     */
    public function status(): array
    {
        $allMigrations = $this->getAllMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();

        $status = [];
        foreach ($allMigrations as $migration) {
            $status[] = ['migration' => $migration,
                'status' => in_array($migration, $executedMigrations) ? 'executed' : 'pending'];
        }

        return $status;
    }
}
