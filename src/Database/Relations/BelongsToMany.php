<?php

namespace Refynd\Database\Relations;

use Refynd\Database\Model;
use Refynd\Database\Collection;
use Refynd\Database\Ledger;

/**
 * BelongsToMany - Many-to-many relationship
 * 
 * Represents a many-to-many relationship where models are related
 * through a pivot table.
 */
class BelongsToMany extends Relation
{
    protected string $table;
    protected string $foreignPivotKey;
    protected string $relatedPivotKey;
    protected string $parentKey;
    protected string $relatedKey;

    public function __construct(Model $parent, Model $related, string $table, string $foreignPivotKey, string $relatedPivotKey)
    {
        $this->table = $table;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;
        $this->parentKey = $parent->getKeyName();
        $this->relatedKey = $related->getKeyName();
        
        parent::__construct($parent, $related);
    }

    /**
     * Set the base constraints on the relation query
     */
    public function addConstraints(): void
    {
        $this->query->join(
            $this->table, 
            $this->related->getTable() . '.' . $this->relatedKey, 
            '=', 
            $this->table . '.' . $this->relatedPivotKey
        );

        if ($this->parent->exists) {
            $this->query->where($this->table . '.' . $this->foreignPivotKey, '=', $this->parent->getKey());
        }
    }

    /**
     * Add constraints for eager loading
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = array_unique(array_map(function ($model) {
            return $model->getKey();
        }, $models));

        $this->query->whereIn($this->table . '.' . $this->foreignPivotKey, array_filter($keys));
    }

    /**
     * Initialize the relation on a set of models
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, new Collection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getKey();
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship
     */
    public function getResults(): Collection
    {
        return $this->query->get() ?: new Collection();
    }

    /**
     * Attach models to the relationship
     */
    public function attach($ids, array $attributes = []): void
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $id) {
            $pivotData = array_merge([
                $this->foreignPivotKey => $this->parent->getKey(),
                $this->relatedPivotKey => $id,
            ], $attributes);

            $this->insertPivot($pivotData);
        }
    }

    /**
     * Detach models from the relationship
     */
    public function detach($ids = null): int
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->foreignPivotKey} = :parent_key";
        $bindings = [':parent_key' => $this->parent->getKey()];

        if ($ids !== null) {
            if (!is_array($ids)) {
                $ids = [$ids];
            }

            $placeholders = [];
            foreach ($ids as $index => $id) {
                $placeholder = ":id_{$index}";
                $placeholders[] = $placeholder;
                $bindings[$placeholder] = $id;
            }

            $sql .= " AND {$this->relatedPivotKey} IN (" . implode(', ', $placeholders) . ")";
        }

        return Ledger::statement($sql, $bindings);
    }

    /**
     * Sync the relationship with the given IDs
     */
    public function sync(array $ids): array
    {
        // Get current IDs
        $current = $this->getCurrentIds();
        
        // Determine what to attach and detach
        $toAttach = array_diff($ids, $current);
        $toDetach = array_diff($current, $ids);
        
        // Perform operations
        if (!empty($toDetach)) {
            $this->detach($toDetach);
        }
        
        if (!empty($toAttach)) {
            $this->attach($toAttach);
        }

        return [
            'attached' => $toAttach,
            'detached' => $toDetach,
            'updated' => [],
        ];
    }

    /**
     * Get current related IDs
     */
    protected function getCurrentIds(): array
    {
        $sql = "SELECT {$this->relatedPivotKey} FROM {$this->table} WHERE {$this->foreignPivotKey} = :parent_key";
        $results = Ledger::select($sql, [':parent_key' => $this->parent->getKey()]);
        
        return array_column($results, $this->relatedPivotKey);
    }

    /**
     * Insert a pivot record
     */
    protected function insertPivot(array $attributes): void
    {
        $columns = array_keys($attributes);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $bindings = [];
        foreach ($attributes as $key => $value) {
            $bindings[':' . $key] = $value;
        }

        Ledger::statement($sql, $bindings);
    }

    /**
     * Build the model dictionary for matching
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            // For many-to-many, we need to get the foreign key from the pivot
            // This is complex and would need pivot data in the result
            // For now, we'll use a simplified approach
            $key = $result->getAttribute($this->foreignPivotKey);
            
            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }
            
            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }
}
