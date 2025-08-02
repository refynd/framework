<?php

namespace Refynd\Database;

use RuntimeException;

/**
 * Schema - Fluent schema builder for database operations
 * 
 * Provides an expressive interface for creating, modifying,
 * and dropping database tables and columns.
 */
class Schema
{
    /**
     * Create a new table
     */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        $sql = $blueprint->toSql();
        static::getPdo()->exec($sql);
    }

    /**
     * Modify an existing table
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $blueprint->setModifying(true);
        $callback($blueprint);
        
        $statements = $blueprint->toSqlStatements();
        foreach ($statements as $statement) {
            static::getPdo()->exec($statement);
        }
    }

    /**
     * Drop a table
     */
    public static function drop(string $table): void
    {
        $sql = "DROP TABLE IF EXISTS {$table}";
        static::getPdo()->exec($sql);
    }

    /**
     * Get the PDO instance (override in your application)
     */
    protected static function getPdo(): \PDO
    {
        // This should be implemented in your application
        // For now, throw an exception to indicate configuration needed
        throw new \RuntimeException('PDO instance not configured. Override Schema::getPdo() in your application.');
    }

    /**
     * Drop a table if it exists
     */
    public function dropIfExists(string $table): void
    {
        $this->drop($table);
    }

    /**
     * Check if a table exists
     */
    public function hasTable(string $table): bool
    {
        $sql = "SHOW TABLES LIKE :table";
        $result = Ledger::select($sql, [':table' => $table]);
        
        return !empty($result);
    }

    /**
     * Check if a column exists on a table
     */
    public function hasColumn(string $table, string $column): bool
    {
        $sql = "SHOW COLUMNS FROM {$table} LIKE :column";
        $result = Ledger::select($sql, [':column' => $column]);
        
        return !empty($result);
    }

    /**
     * Rename a table
     */
    public function rename(string $from, string $to): void
    {
        $sql = "RENAME TABLE {$from} TO {$to}";
        Ledger::statement($sql);
    }
}
