<?php

namespace Refynd\Database;

use DateTime;
use RuntimeException;
use Refynd\Database\Relations\HasMany;
use Refynd\Database\Relations\BelongsTo;
use Refynd\Database\Relations\BelongsToMany;
use Refynd\Database\Relations\HasOne;

/**
 * Model - Enhanced ORM Model with Relationships
 * 
 * Provides elegant database interactions with support for relationships,
 * scopes, events, and advanced querying capabilities.
 */
abstract class Model extends Record
{
    protected array $with = [];
    protected array $hidden = [];
    protected array $visible = [];
    protected array $appends = [];
    protected bool $timestamps = true;
    protected string $createdAt = 'created_at';
    protected string $updatedAt = 'updated_at';
    
    protected array $relations = [];
    protected array $relationshipData = [];

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        // Override in child classes for model events
    }

    /**
     * Get all models with eager loading
     */
    public static function with(array $relations): QueryBuilder
    {
        $instance = new static();
        return $instance->newQuery()->with($relations);
    }

    /**
     * Create a new query builder with eager loading support
     */
    public function newQuery(): QueryBuilder
    {
        $builder = parent::newQuery();
        $builder->setModel($this);
        return $builder;
    }

    /**
     * Start a new query builder for this model
     */
    public static function query(): QueryBuilder
    {
        $instance = new static();
        return $instance->newQuery();
    }

    /**
     * Get all records as a Collection (enhanced version)
     */
    public static function all(): array
    {
        $results = static::query()->get();
        return $results instanceof Collection ? $results->toArray() : $results;
    }

    /**
     * Get all records as a Collection object
     */
    public static function allAsCollection(): Collection
    {
        return static::query()->get();
    }

    /**
     * Define a one-to-many relationship
     */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->primaryKey;
        
        return new HasMany($this, new $related(), $foreignKey, $localKey);
    }

    /**
     * Define a one-to-one relationship
     */
    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();
        $localKey = $localKey ?: $this->primaryKey;
        
        return new HasOne($this, new $related(), $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-many relationship
     */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $foreignKey = $foreignKey ?: $this->getRelatedForeignKey($related);
        $ownerKey = $ownerKey ?: (new $related())->primaryKey;
        
        return new BelongsTo($this, new $related(), $foreignKey, $ownerKey);
    }

    /**
     * Define a many-to-many relationship
     */
    protected function belongsToMany(string $related, ?string $table = null, ?string $foreignPivotKey = null, ?string $relatedPivotKey = null): BelongsToMany
    {
        $table = $table ?: $this->joiningTable($related);
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();
        $relatedPivotKey = $relatedPivotKey ?: (new $related())->getForeignKey();
        
        return new BelongsToMany($this, new $related(), $table, $foreignPivotKey, $relatedPivotKey);
    }

    /**
     * Save the model with timestamp handling
     */
    public function save(): bool
    {
        if ($this->timestamps) {
            $this->updateTimestamps();
        }

        return parent::save();
    }

    /**
     * Get the primary key name
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the table name
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the foreign key for this model
     */
    public function getForeignKey(): string
    {
        return strtolower(class_basename($this)) . '_id';
    }

    /**
     * Get the foreign key for a related model
     */
    protected function getRelatedForeignKey(string $related): string
    {
        return strtolower(class_basename($related)) . '_id';
    }

    /**
     * Get the joining table name for many-to-many relationships
     */
    protected function joiningTable(string $related): string
    {
        $models = [
            strtolower(class_basename($this)),
            strtolower(class_basename($related))
        ];
        
        sort($models);
        
        return implode('_', $models);
    }

    /**
     * Update the model's timestamps
     */
    protected function updateTimestamps(): void
    {
        $now = date('Y-m-d H:i:s');
        
        if (!$this->exists) {
            $this->setAttribute($this->createdAt, $now);
        }
        
        $this->setAttribute($this->updatedAt, $now);
    }

    /**
     * Get a relationship value
     */
    public function getRelationValue(string $relation)
    {
        if (array_key_exists($relation, $this->relationshipData)) {
            return $this->relationshipData[$relation];
        }

        if (method_exists($this, $relation)) {
            $result = $this->$relation();
            
            if ($result instanceof Relations\Relation) {
                $this->relationshipData[$relation] = $result->getResults();
                return $this->relationshipData[$relation];
            }
        }

        return null;
    }

    /**
     * Set a relationship value
     */
    public function setRelation(string $relation, $value): self
    {
        $this->relationshipData[$relation] = $value;
        return $this;
    }

    /**
     * Convert model to array including relationships
     */
    public function toArray(): array
    {
        $attributes = parent::toArray();
        
        // Add loaded relationships
        foreach ($this->relationshipData as $key => $value) {
            if ($value instanceof Collection) {
                $attributes[$key] = $value->toArray();
            } elseif ($value instanceof Model) {
                $attributes[$key] = $value->toArray();
            } elseif (is_array($value)) {
                $attributes[$key] = array_map(function($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                }, $value);
            } else {
                $attributes[$key] = $value;
            }
        }

        // Apply hidden/visible rules
        if (!empty($this->visible)) {
            $attributes = array_intersect_key($attributes, array_flip($this->visible));
        }

        if (!empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Dynamically access relationships
     */
    public function __get(string $key)
    {
        // First check regular attributes
        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        // Then check relationships
        return $this->getRelationValue($key);
    }

    /**
     * Define query scopes dynamically
     */
    public function __call(string $method, array $parameters)
    {
        // Check for scope methods
        if (str_starts_with($method, 'scope')) {
            $scope = lcfirst(substr($method, 5));
            $query = $this->newQuery();
            $scopeMethod = $method;
            return $this->$scopeMethod($query, ...$parameters);
        }

        throw new RuntimeException("Method {$method} does not exist on " . static::class);
    }

    /**
     * Handle dynamic static calls for query builder methods
     */
    public static function __callStatic(string $method, array $parameters)
    {
        $instance = new static();
        return $instance->newQuery()->$method(...$parameters);
    }
}
