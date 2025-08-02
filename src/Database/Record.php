<?php

namespace Refynd\Database;

use DateTime;
use RuntimeException;

/**
 * Record - Base Model Class for Ledger ORM
 *
 * Active Record-style base class for all database models.
 * Provides elegant methods for database interactions.
 */
abstract class Record
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = ['id'];
    protected array $casts = [];
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);

        if (empty($this->table)) {
            $this->table = $this->getDefaultTableName();
        }
    }

    /**
     * Create a new record and save it to the database
     */
    public static function create(array $attributes): static
    {
        /** @var static $record */
        /** @var static $record */
        $record = new static($attributes); // @phpstan-ignore-line - Safe static instantiation for Record classes
        $record->save();
        return $record;
    }

    /**
     * Find a record by its primary key
     */
    public static function find(mixed $id): ?static
    {
        /** @var static $instance */
        $instance = new static(); // @phpstan-ignore-line - Safe static instantiation for Record classes
        $result = $instance->newQuery()
            ->where($instance->primaryKey, $id)
            ->first();

        if ($result === null) {
            return null;
        }

        return $instance->newFromDatabase($result);
    }

    /**
     * Find a record by its primary key or throw an exception
     */
    public static function findOrFail(mixed $id): static
    {
        $record = static::find($id);

        if ($record === null) {
            throw new RuntimeException("Record not found with ID: {$id}");
        }

        return $record;
    }

    /**
     * Get all records
     */
    public static function all(): array
    {
        /** @var static $instance */
        $instance = new static(); // @phpstan-ignore-line - Safe static instantiation for Record classes
        $results = $instance->newQuery()->get();

        return array_map(function ($result) use ($instance) {
            return $instance->newFromDatabase($result);
        }, $results);
    }

    /**
     * Create a new query builder for this model
     */
    public static function where(string $column, string $operator, mixed $value = null): QueryBuilder
    {
        /** @var static $instance */
        $instance = new static(); // @phpstan-ignore-line - Safe static instantiation for Record classes
        return $instance->newQuery()->where($column, $operator, $value);
    }

    /**
     * Create a new query builder instance
     */
    public function newQuery(): QueryBuilder
    {
        return new QueryBuilder($this->table);
    }

    /**
     * Create a new model instance from database result
     */
    public function newFromDatabase(array $attributes): static
    {
        /** @var static $instance */
        $instance = new static(); // @phpstan-ignore-line - Safe static instantiation for Record classes
        $instance->attributes = $instance->castAttributes($attributes);
        $instance->original = $instance->attributes;
        $instance->exists = true;

        return $instance;
    }

    /**
     * Fill the model with attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * Save the model to the database
     */
    public function save(): bool
    {
        if ($this->exists) {
            return $this->performUpdate();
        } else {
            return $this->performInsert();
        }
    }

    /**
     * Delete the model from the database
     */
    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $affected = Ledger::statement($sql, [':id' => $this->getKey()]);

        if ($affected > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    /**
     * Get the primary key value
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    /**
     * Get an attribute value
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set an attribute value
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the model to an array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the model to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Magic getter for attributes
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter for attributes
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Magic isset for attributes
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Perform an INSERT query
     */
    protected function performInsert(): bool
    {
        $attributes = $this->getAttributesForInsert();

        if (empty($attributes)) {
            return false;
        }

        $columns = array_keys($attributes);
        $placeholders = array_map(fn ($col) => ':' . $col, $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $bindings = [];
        foreach ($attributes as $key => $value) {
            $bindings[':' . $key] = $value;
        }

        $insertId = Ledger::insert($sql, $bindings);

        if ($insertId) {
            $this->setAttribute($this->primaryKey, $insertId);
            $this->exists = true;
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    /**
     * Perform an UPDATE query
     */
    protected function performUpdate(): bool
    {
        $dirty = $this->getDirtyAttributes();

        if (empty($dirty)) {
            return true; // No changes to save
        }

        $sets = [];
        $bindings = [];

        foreach ($dirty as $key => $value) {
            $sets[] = "{$key} = :{$key}";
            $bindings[":{$key}"] = $value;
        }

        $bindings[':id'] = $this->getKey();

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE {$this->primaryKey} = :id";

        $affected = Ledger::statement($sql, $bindings);

        if ($affected > 0) {
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    /**
     * Get attributes that are dirty (changed)
     */
    protected function getDirtyAttributes(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                if ($key !== $this->primaryKey) { // Don't update primary key
                    $dirty[$key] = $value;
                }
            }
        }

        return $dirty;
    }

    /**
     * Get attributes for INSERT query
     */
    protected function getAttributesForInsert(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->isFillable($key) && $key !== $this->primaryKey) {
                $attributes[$key] = $value;
            }
        }

        return $attributes;
    }

    /**
     * Check if an attribute is fillable
     */
    protected function isFillable(string $key): bool
    {
        // If fillable is defined, only allow those
        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        // Otherwise, allow all except guarded
        return !in_array($key, $this->guarded);
    }

    /**
     * Cast attributes according to the casts array
     */
    protected function castAttributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (isset($this->casts[$key])) {
                $attributes[$key] = $this->castAttribute($key, $value);
            }
        }

        return $attributes;
    }

    /**
     * Cast a single attribute
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        $castType = $this->casts[$key];

        if ($value === null) {
            return null;
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return is_array($value) ? $value : json_decode($value, true);
            case 'json':
                return is_array($value) ? $value : json_decode($value, true);
            case 'date':
            case 'datetime':
                return $value instanceof DateTime ? $value : new DateTime($value);
            default:
                return $value;
        }
    }

    /**
     * Get the default table name for this model
     */
    protected function getDefaultTableName(): string
    {
        $className = class_basename(static::class);

        // Remove "Record" suffix if present
        if (str_ends_with($className, 'Record')) {
            $className = substr($className, 0, -6);
        }

        // Convert to snake_case and pluralize
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className)) . 's';
    }
}
