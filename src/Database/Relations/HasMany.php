<?php

namespace Refynd\Database\Relations;

use Refynd\Database\Model;
use Refynd\Database\Collection;

/**
 * HasMany - One-to-many relationship
 *
 * Represents a one-to-many relationship where the parent model
 * has multiple related models.
 */
class HasMany extends Relation
{
    protected string $foreignKey;
    protected string $localKey;

    public function __construct(Model $parent, Model $related, string $foreignKey, string $localKey)
    {
        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;

        parent::__construct($parent, $related);
    }

    /**
     * Set the base constraints on the relation query
     */
    public function addConstraints(): void
    {
        if ($this->parent->modelExists()) {
            $this->query->where($this->foreignKey, '=', $this->parent->getAttribute($this->localKey));
        }
    }

    /**
     * Add constraints for eager loading
     */
    public function addEagerConstraints(array $models): void
    {
        $keys = array_unique(array_map(function ($model) {
            return $model->getAttribute($this->localKey);
        }, $models));

        $this->query->whereIn($this->foreignKey, array_filter($keys));
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
     * @param Collection < int, Model> $results
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);

            if (isset($dictionary[$key])) {
                $model->setRelation($relation, new Collection($dictionary[$key]));
            }
        }

        return (array) $models;
    }

    /**
     * Get the results of the relationship
     * @return Collection < int, Model>
     */
    public function getResults(): Collection
    {
        return $this->query->get() ?: new Collection();
    }

    /**
     * Create a new related model
     */
    public function create(array $attributes = []): Model
    {
        $localKeyValue = $this->parent->getAttribute($this->localKey);
        if ($localKeyValue === null) {
            throw new \InvalidArgumentException('Parent model must have a value for the local key to create related models');
        }

        $attributes[$this->foreignKey] = $localKeyValue;

        return $this->related::create($attributes);
    }

    /**
     * Save a related model
     */
    public function save(Model $model): bool
    {
        $localKeyValue = $this->parent->getAttribute($this->localKey);
        if ($localKeyValue === null) {
            throw new \InvalidArgumentException('Parent model must have a value for the local key to save related models');
        }

        $model->setAttribute($this->foreignKey, $localKeyValue);

        return $model->save();
    }

    /**
     * Create multiple related models
     * @return Collection < int, Model>
     */
    public function createMany(array $records): Collection
    {
        $models = new Collection();

        foreach ($records as $attributes) {
            $models->push($this->create($attributes));
        }

        return $models;
    }

    /**
     * Save multiple related models
     */
    public function saveMany(array $models): bool
    {
        $result = true;

        foreach ($models as $model) {
            if (!$this->save($model)) {
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Build the model dictionary for matching
     * @param Collection < int, Model> $results
     */
    protected function buildDictionary(Collection $results): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $key = $result->getAttribute($this->foreignKey);

            if (!isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }
}
