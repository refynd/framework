<?php

namespace Refynd\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Ledger - Central ORM Manager
 * 
 * Manages database connections and provides the foundation
 * for Record models and query operations.
 */
class Ledger
{
    protected static ?PDO $connection = null;
    protected static array $config = [];

    /**
     * Set database configuration
     */
    public static function configure(array $config): void
    {
        static::$config = $config;
    }

    /**
     * Get database connection
     */
    public static function connection(): PDO
    {
        if (static::$connection === null) {
            static::connect();
        }

        return static::$connection;
    }

    /**
     * Establish database connection
     */
    protected static function connect(): void
    {
        $config = static::$config;

        if (empty($config)) {
            throw new RuntimeException('Database configuration not set. Call Ledger::configure() first.');
        }

        $dsn = static::buildDsn($config);

        try {
            static::$connection = new PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Build DSN string from configuration
     */
    protected static function buildDsn(array $config): string
    {
        $driver = $config['driver'] ?? 'mysql';
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? 3306;
        $database = $config['database'] ?? '';

        switch ($driver) {
            case 'mysql':
                return "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            case 'pgsql':
                return "pgsql:host={$host};port={$port};dbname={$database}";
            case 'sqlite':
                return "sqlite:" . ($config['database'] ?? ':memory:');
            default:
                throw new RuntimeException("Unsupported database driver: {$driver}");
        }
    }

    /**
     * Execute a raw query
     */
    public static function query(string $sql, array $bindings = []): \PDOStatement
    {
        $connection = static::connection();
        $statement = $connection->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    /**
     * Execute a SELECT query and return results
     */
    public static function select(string $sql, array $bindings = []): array
    {
        return static::query($sql, $bindings)->fetchAll();
    }

    /**
     * Execute an INSERT query and return last insert ID
     */
    public static function insert(string $sql, array $bindings = []): int
    {
        static::query($sql, $bindings);
        return (int) static::connection()->lastInsertId();
    }

    /**
     * Execute an UPDATE or DELETE query and return affected rows
     */
    public static function statement(string $sql, array $bindings = []): int
    {
        return static::query($sql, $bindings)->rowCount();
    }

    /**
     * Begin a database transaction
     */
    public static function beginTransaction(): bool
    {
        return static::connection()->beginTransaction();
    }

    /**
     * Commit a database transaction
     */
    public static function commit(): bool
    {
        return static::connection()->commit();
    }

    /**
     * Rollback a database transaction
     */
    public static function rollback(): bool
    {
        return static::connection()->rollBack();
    }

    /**
     * Execute a callback within a transaction
     */
    public static function transaction(callable $callback): mixed
    {
        static::beginTransaction();

        try {
            $result = $callback();
            static::commit();
            return $result;
        } catch (\Throwable $e) {
            static::rollback();
            throw $e;
        }
    }
}
