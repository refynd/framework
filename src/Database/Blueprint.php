<?php

namespace Refynd\Database;

/**
 * Blueprint - Schema blueprint for table creation and modification
 *
 * Provides fluent methods for defining table structure,
 * columns, indexes, and constraints.
 */
class Blueprint
{
    protected string $table;
    protected array $columns = [];
    protected array $commands = [];
    protected bool $modifying = false;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Set whether this is modifying an existing table
     */
    public function setModifying(bool $modifying): void
    {
        $this->modifying = $modifying;
    }

    /**
     * Create an auto-incrementing primary key column
     */
    public function id(string $column = 'id'): Column
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create a big incrementing integer column
     */
    public function bigIncrements(string $column): Column
    {
        $col = new Column($column, 'BIGINT', ['unsigned' => true, 'auto_increment' => true]);
        $this->columns[] = $col;
        $this->primary($column);
        return $col;
    }

    /**
     * Create a string column
     */
    public function string(string $column, int $length = 255): Column
    {
        $col = new Column($column, 'VARCHAR', ['length' => $length]);
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a text column
     */
    public function text(string $column): Column
    {
        $col = new Column($column, 'TEXT');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create an integer column
     */
    public function integer(string $column): Column
    {
        $col = new Column($column, 'INT');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a big integer column
     */
    public function bigInteger(string $column): Column
    {
        $col = new Column($column, 'BIGINT');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a boolean column
     */
    public function boolean(string $column): Column
    {
        $col = new Column($column, 'BOOLEAN');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a decimal column
     */
    public function decimal(string $column, int $precision = 8, int $scale = 2): Column
    {
        $col = new Column($column, 'DECIMAL', ['precision' => $precision, 'scale' => $scale]);
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a float column
     */
    public function float(string $column): Column
    {
        $col = new Column($column, 'FLOAT');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a date column
     */
    public function date(string $column): Column
    {
        $col = new Column($column, 'DATE');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a datetime column
     */
    public function dateTime(string $column): Column
    {
        $col = new Column($column, 'DATETIME');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create a timestamp column
     */
    public function timestamp(string $column): Column
    {
        $col = new Column($column, 'TIMESTAMP');
        $this->columns[] = $col;
        return $col;
    }

    /**
     * Create timestamp columns (created_at, updated_at)
     */
    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable()->default('CURRENT_TIMESTAMP');
        $this->timestamp('updated_at')->nullable()->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    /**
     * Create a foreign key column
     */
    public function foreignId(string $column): Column
    {
        return $this->bigInteger($column)->unsigned();
    }

    /**
     * Add a primary key
     */
    public function primary(string|array $columns): void
    {
        $this->commands[] = ['type' => 'primary',
            'columns' => is_array($columns) ? $columns : [$columns]];
    }

    /**
     * Add an index
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?: 'idx_' . $this->table . '_' . implode('_', $columns);

        $this->commands[] = ['type' => 'index',
            'columns' => $columns,
            'name' => $name];
    }

    /**
     * Add a unique index
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?: 'unique_' . $this->table . '_' . implode('_', $columns);

        $this->commands[] = ['type' => 'unique',
            'columns' => $columns,
            'name' => $name];
    }

    /**
     * Add a foreign key constraint
     */
    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($column);
        $this->commands[] = ['type' => 'foreign',
            'definition' => $foreign];
        return $foreign;
    }

    /**
     * Drop a column
     */
    public function dropColumn(string|array $columns): void
    {
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $column) {
            $this->commands[] = ['type' => 'drop_column',
                'column' => $column];
        }
    }

    /**
     * Convert the blueprint to SQL for table creation
     */
    public function toSql(): string
    {
        $sql = "CREATE TABLE {$this->table} (\n";

        $definitions = [];

        // Add column definitions
        foreach ($this->columns as $column) {
            $definitions[] = '  ' . $column->toSql();
        }

        // Add constraints
        foreach ($this->commands as $command) {
            switch ($command['type']) {
                case 'primary':
                    $columns = implode(', ', $command['columns']);
                    $definitions[] = "  PRIMARY KEY ({$columns})";
                    break;
                case 'unique':
                    $columns = implode(', ', $command['columns']);
                    $definitions[] = "  UNIQUE KEY {$command['name']} ({$columns})";
                    break;
                case 'index':
                    $columns = implode(', ', $command['columns']);
                    $definitions[] = "  KEY {$command['name']} ({$columns})";
                    break;
                case 'foreign':
                    $definitions[] = '  ' . $command['definition']->toSql();
                    break;
            }
        }

        $sql .= implode(", \n", $definitions);
        $sql .= "\n) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci";

        return $sql;
    }

    /**
     * Convert the blueprint to SQL statements for table modification
     */
    public function toSqlStatements(): array
    {
        $statements = [];

        // Add new columns
        foreach ($this->columns as $column) {
            $statements[] = "ALTER TABLE {$this->table} ADD COLUMN " . $column->toSql();
        }

        // Process commands
        foreach ($this->commands as $command) {
            switch ($command['type']) {
                case 'drop_column':
                    $statements[] = "ALTER TABLE {$this->table} DROP COLUMN {$command['column']}";
                    break;
                case 'index':
                    $columns = implode(', ', $command['columns']);
                    $statements[] = "ALTER TABLE {$this->table} ADD INDEX {$command['name']} ({$columns})";
                    break;
                case 'unique':
                    $columns = implode(', ', $command['columns']);
                    $statements[] = "ALTER TABLE {$this->table} ADD UNIQUE {$command['name']} ({$columns})";
                    break;
                case 'foreign':
                    $statements[] = "ALTER TABLE {$this->table} ADD " . $command['definition']->toSql();
                    break;
            }
        }

        return $statements;
    }
}
