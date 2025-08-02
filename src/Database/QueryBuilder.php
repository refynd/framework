<?php

namespace Refynd\Database;

/**
 * QueryBuilder - Fluent Query Builder for Ledger ORM
 * 
 * Provides an expressive, fluent interface for building database queries.
 */
class QueryBuilder
{
    protected string $table;
    protected array $select = ['*'];
    protected array $where = [];
    protected array $joins = [];
    protected array $orderBy = [];
    protected array $groupBy = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $bindings = [];
    protected ?Model $model = null;
    protected array $eagerLoad = [];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * Set the SELECT clause
     */
    public function select(array $columns = ['*']): self
    {
        $this->select = $columns;
        return $this;
    }

    /**
     * Add a WHERE clause
     */
    public function where(string $column, string $operator, mixed $value = null): self
    {
        // Handle where($column, $value) syntax
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'where',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Add an OR WHERE clause
     */
    public function orWhere(string $column, string $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'where',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'or'
        ];

        return $this;
    }

    /**
     * Add a WHERE IN clause
     */
    public function whereIn(string $column, array $values): self
    {
        $this->where[] = [
            'type' => 'wherein',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Add a WHERE NULL clause
     */
    public function whereNull(string $column): self
    {
        $this->where[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT NULL clause
     */
    public function whereNotNull(string $column): self
    {
        $this->where[] = [
            'type' => 'notnull',
            'column' => $column,
            'boolean' => 'and'
        ];

        return $this;
    }

    /**
     * Add a JOIN clause
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'inner',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add a LEFT JOIN clause
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $this->joins[] = [
            'type' => 'left',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    /**
     * Add an ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtolower($direction)
        ];

        return $this;
    }

    /**
     * Add a GROUP BY clause
     */
    public function groupBy(string $column): self
    {
        $this->groupBy[] = $column;
        return $this;
    }

    /**
     * Set the LIMIT clause
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the OFFSET clause
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Execute the query and return all results
     * @return array|Collection<int, Model>
     */
    public function get(): array|Collection
    {
        $sql = $this->toSql();
        $results = Ledger::select($sql, $this->bindings);
        
        if ($this->model) {
            $models = [];
            foreach ($results as $result) {
                $models[] = $this->model->newFromDatabase($result);
            }
            
            $collection = new Collection($models);
            
            // Handle eager loading
            if (!empty($this->eagerLoad)) {
                $collection = $this->loadRelations($collection);
            }
            
            return $collection;
        }
        
        return $results;
    }

    /**
     * Execute the query and return the first result
     */
    public function first(): mixed
    {
        $this->limit(1);
        $results = $this->get();
        
        if ($results instanceof Collection) {
            return $results->first();
        }
        
        return $results[0] ?? null;
    }

    /**
     * Set the model for this query
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set eager loading relationships
     */
    public function with(array $relations): self
    {
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }

    /**
     * Load relationships for the collection
     * @param Collection<int, Model> $collection
     * @return Collection<int, Model>
     */
    protected function loadRelations(Collection $collection): Collection
    {
        foreach ($this->eagerLoad as $relation) {
            if (method_exists($this->model, $relation)) {
                $relationInstance = $this->model->$relation();
                
                if ($relationInstance instanceof Relations\Relation) {
                    $models = $collection->all();
                    $relationInstance->addEagerConstraints($models);
                    $results = $relationInstance->get();
                    $collection = new Collection(
                        $relationInstance->match($models, $results, $relation)
                    );
                }
            }
        }
        
        return $collection;
    }

    /**
     * Get the count of records
     */
    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];
        
        $result = $this->first();
        $this->select = $originalSelect;
        
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Convert the query to SQL
     */
    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select) . ' FROM ' . $this->table;

        // Add JOINs
        foreach ($this->joins as $join) {
            $type = strtoupper($join['type']);
            $sql .= " {$type} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // Add WHERE clauses
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . $this->buildWhereClause();
        }

        // Add GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // Add ORDER BY
        if (!empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as $order) {
                $orderClauses[] = $order['column'] . ' ' . strtoupper($order['direction']);
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
        }

        // Add LIMIT and OFFSET
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Build WHERE clause string
     */
    protected function buildWhereClause(): string
    {
        $clauses = [];
        $this->bindings = [];

        foreach ($this->where as $index => $condition) {
            $boolean = $index === 0 ? '' : strtoupper($condition['boolean']) . ' ';

            switch ($condition['type']) {
                case 'where':
                    $placeholder = ':where_' . count($this->bindings);
                    $this->bindings[$placeholder] = $condition['value'];
                    $clauses[] = $boolean . $condition['column'] . ' ' . $condition['operator'] . ' ' . $placeholder;
                    break;

                case 'wherein':
                    $placeholders = [];
                    foreach ($condition['values'] as $value) {
                        $placeholder = ':wherein_' . count($this->bindings);
                        $this->bindings[$placeholder] = $value;
                        $placeholders[] = $placeholder;
                    }
                    $clauses[] = $boolean . $condition['column'] . ' IN (' . implode(', ', $placeholders) . ')';
                    break;

                case 'null':
                    $clauses[] = $boolean . $condition['column'] . ' IS NULL';
                    break;

                case 'notnull':
                    $clauses[] = $boolean . $condition['column'] . ' IS NOT NULL';
                    break;
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * Get the bindings array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}
