<?php

namespace Refynd\Database;

/**
 * Column - Represents a database column definition
 *
 * Provides fluent methods for defining column properties
 * and constraints.
 */
class Column
{
    protected string $name;
    protected string $type;
    protected array $attributes;

    public function __construct(string $name, string $type, array $attributes = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->attributes = $attributes;
    }

    /**
     * Mark the column as having a unique constraint
     */
    public function unique(): self
    {
        $this->attributes['unique'] = true;
        return $this;
    }

    /**
     * Mark the column as nullable
     */
    public function nullable(bool $nullable = true): self
    {
        $this->attributes['nullable'] = $nullable;
        return $this;
    }

    /**
     * Set a default value for the column
     */
    public function default(mixed $value): self
    {
        $this->attributes['default'] = $value;
        return $this;
    }

    /**
     * Mark the column as unsigned (for numeric types)
     */
    public function unsigned(): self
    {
        $this->attributes['unsigned'] = true;
        return $this;
    }

    /**
     * Set the column length
     */
    public function length(int $length): self
    {
        $this->attributes['length'] = $length;
        return $this;
    }

    /**
     * Add a comment to the column
     */
    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    /**
     * Convert the column to SQL
     */
    public function toSql(): string
    {
        $sql = "`{$this->name}` {$this->type}";

        // Add length/precision
        if (isset($this->attributes['length'])) {
            $sql .= "({$this->attributes['length']})";
        } elseif (isset($this->attributes['precision']) && isset($this->attributes['scale'])) {
            $sql .= "({$this->attributes['precision']}, {$this->attributes['scale']})";
        }

        // Add unsigned
        if (isset($this->attributes['unsigned']) && $this->attributes['unsigned']) {
            $sql .= ' UNSIGNED';
        }

        // Add nullable
        if (isset($this->attributes['nullable']) && $this->attributes['nullable']) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        // Add auto increment
        if (isset($this->attributes['auto_increment']) && $this->attributes['auto_increment']) {
            $sql .= ' AUTO_INCREMENT';
        }

        // Add default
        if (isset($this->attributes['default'])) {
            $default = $this->attributes['default'];
            if (is_string($default) && !in_array(strtoupper($default), ['CURRENT_TIMESTAMP', 'NULL'])) {
                $sql .= " DEFAULT '{$default}'";
            } else {
                $sql .= " DEFAULT {$default}";
            }
        }

        // Add comment
        if (isset($this->attributes['comment'])) {
            $sql .= " COMMENT '{$this->attributes['comment']}'";
        }

        return $sql;
    }
}
