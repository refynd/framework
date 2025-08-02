<?php

namespace Refynd\Database;

/**
 * ForeignKeyDefinition - Represents a foreign key constraint definition
 * 
 * Provides fluent methods for defining foreign key relationships
 * and actions.
 */
class ForeignKeyDefinition
{
    protected string $column;
    protected ?string $references = null;
    protected ?string $on = null;
    protected string $onDelete = 'RESTRICT';
    protected string $onUpdate = 'RESTRICT';
    protected ?string $name = null;

    public function __construct(string $column)
    {
        $this->column = $column;
    }

    /**
     * Set the referenced column
     */
    public function references(string $column): self
    {
        $this->references = $column;
        return $this;
    }

    /**
     * Set the referenced table
     */
    public function on(string $table): self
    {
        $this->on = $table;
        return $this;
    }

    /**
     * Set the action on delete
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);
        return $this;
    }

    /**
     * Set the action on update
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);
        return $this;
    }

    /**
     * Set a custom constraint name
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Shortcut for cascade on delete
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    /**
     * Shortcut for set null on delete
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    /**
     * Convert the foreign key definition to SQL
     */
    public function toSql(): string
    {
        if (!$this->references || !$this->on) {
            throw new \InvalidArgumentException('Foreign key must specify references() and on()');
        }

        $name = $this->name ?: "fk_{$this->column}";
        
        $sql = "CONSTRAINT `{$name}` FOREIGN KEY (`{$this->column}`) ";
        $sql .= "REFERENCES `{$this->on}` (`{$this->references}`)";
        
        if ($this->onDelete !== 'RESTRICT') {
            $sql .= " ON DELETE {$this->onDelete}";
        }
        
        if ($this->onUpdate !== 'RESTRICT') {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }

        return $sql;
    }
}
