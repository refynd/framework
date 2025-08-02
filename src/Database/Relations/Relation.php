<?php

namespace Refynd\Database\Relations;

use Refynd\Database\Model;
use Refynd\Database\QueryBuilder;
use Refynd\Database\Collection;

/**
 * Relation - Base class for all relationship types
 * 
 * Provides the foundation for implementing different types
 * of relationships between models.
 */
abstract class Relation
{
    protected Model $parent;
    protected Model $related;
    protected QueryBuilder $query;

    public function __construct(Model $parent, Model $related)
    {
        $this->parent = $parent;
        $this->related = $related;
        $this->query = $related->newQuery();
        
        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query
     */
    abstract public function addConstraints(): void;

    /**
     * Add constraints for eager loading
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents
     * @param Collection<int, Model> $results
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    /**
     * Get the results of the relationship
     */
    abstract public function getResults(): mixed;

    /**
     * Get the relationship query
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Add a constraint to the relationship query
     */
    public function where(string $column, string $operator, mixed $value = null): self
    {
        $this->query->where($column, $operator, $value);
        return $this;
    }

    /**
     * Add an "or where" constraint to the relationship query
     */
    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        $this->query->orWhere($column, $operator, $value);
        return $this;
    }

    /**
     * Add an order by constraint to the relationship query
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    /**
     * Set the limit on the relationship query
     */
    public function limit(int $limit): self
    {
        $this->query->limit($limit);
        return $this;
    }

    /**
     * Execute the query and get all results
     * @return Collection<int, Model>
     */
    public function get(): Collection
    {
        return $this->query->get();
    }

    /**
     * Execute the query and get the first result
     */
    public function first(): ?Model
    {
        return $this->query->first();
    }

    /**
     * Find a related model by its primary key
     */
    public function find(mixed $id): ?Model
    {
        return $this->query->where($this->related->getKeyName(), $id)->first();
    }

    /**
     * Get the key name of the related model
     */
    protected function getRelatedKeyName(): string
    {
        return $this->related->getKeyName();
    }

    /**
     * Get the fully qualified key name of the related model
     */
    protected function getQualifiedRelatedKeyName(): string
    {
        return $this->related->getTable() . '.' . $this->related->getKeyName();
    }

    /**
     * Get the key name of the parent model
     */
    protected function getParentKeyName(): string
    {
        return $this->parent->getKeyName();
    }

    /**
     * Get the fully qualified key name of the parent model
     */
    protected function getQualifiedParentKeyName(): string
    {
        return $this->parent->getTable() . '.' . $this->parent->getKeyName();
    }

    /**
     * Dynamically handle calls to the query builder
     */
    public function __call(string $method, array $parameters): mixed
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }
}
