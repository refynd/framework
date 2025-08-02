<?php

namespace Refynd\Database;

use RuntimeException;

/**
 * Migration - Base class for database migrations
 *
 * Provides structure for creating and managing database schema changes.
 */
abstract class Migration
{
    protected Schema $schema;

    public function __construct()
    {
        $this->schema = new Schema();
    }

    /**
     * Run the migration
     */
    abstract public function up(): void;

    /**
     * Reverse the migration
     */
    abstract public function down(): void;

    /**
     * Get the schema builder
     */
    protected function schema(): Schema
    {
        return $this->schema;
    }
}
