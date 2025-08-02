<?php

namespace Refynd\Database\Relations;

use Refynd\Database\Model;
use Refynd\Database\Collection;

/**
 * BelongsTo - Inverse one-to-many relationship
 * 
 * Represents an inverse one-to-many relationship where the model
 * belongs to another model.
 */
class BelongsTo extends Relation
{
    protected string $foreignKey;
    protected string $ownerKey;

    public function __construct(Model $parent, Model $related, string $foreignKey, string $ownerKey)
    {
        $this->foreignKey = $foreignKey;
        $this->ownerKey = $ownerKey;
        
        parent::__construct($parent, $related);
    }

    /**
     * Set the base constraints on the relation query
     */
    public function addConstraints(): void
    {
        if ($this->parent->modelExists()) {
            $this->query->where($this->ownerKey, '=', $this->parent->getAttribute($this->foreignKey));
        }
    }

    /**
     * Add constraints for eager loading
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = array_unique(array_map(function ($model) {
            return $model->getAttribute($this->foreignKey);
        }, $models));

        $this->query->whereIn($this->ownerKey, array_filter($keys));
    }

    /**
     * Initialize the relation on a set of models
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, null);
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents
     * @param Collection<int, Model> $results
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            
            if (isset($dictionary[$key])) {
                $model->setRelation($relation, $dictionary[$key]);
            }
        }

        return $models;
    }

    /**
     * Get the results of the relationship
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /**
     * Associate this model with another
     */
    public function associate(Model $model): Model
    {
        $this->parent->setAttribute($this->foreignKey, $model->getAttribute($this->ownerKey));
        
        return $this->parent->setRelation($this->getRelationName(), $model);
    }

    /**
     * Dissociate this model from its parent
     */
    public function dissociate(): Model
    {
        $this->parent->setAttribute($this->foreignKey, null);
        
        return $this->parent->setRelation($this->getRelationName(), null);
    }

    /**
     * Get the name of the relationship
     */
    protected function getRelationName(): string
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]['function'];
    }

    /**
     * Build the model dictionary for matching
     * @param Collection<int, Model> $results
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $result->getAttribute($this->ownerKey);
            $dictionary[$key] = $result;
        }

        return $dictionary;
    }
}
