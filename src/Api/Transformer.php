<?php

namespace Refynd\Api;

use Refynd\Api\Contracts\TransformerInterface;
use Refynd\Api\Contracts\PaginatorInterface;

/**
 * Transformer - Base class for data transformation
 *
 * Provides a flexible system for transforming data models
 * into API-friendly formats with includes and excludes.
 */
abstract class Transformer implements TransformerInterface
{
    protected array $availableIncludes = [];
    protected array $defaultIncludes = [];
    protected array $currentIncludes = [];
    protected array $excludedFields = [];

    /**
     * Transform a single item
     */
    abstract public function transform(mixed $item): array;

    /**
     * Transform a collection of items
     */
    public function transformCollection(iterable $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $results[] = $this->transform($item);
        }

        return $results;
    }

    /**
     * Set available includes
     */
    public function setAvailableIncludes(array $includes): self
    {
        $this->availableIncludes = $includes;
        return $this;
    }

    /**
     * Set default includes
     */
    public function setDefaultIncludes(array $includes): self
    {
        $this->defaultIncludes = $includes;
        return $this;
    }

    /**
     * Include related data
     */
    public function include(string $relation): self
    {
        if (in_array($relation, $this->availableIncludes) && !in_array($relation, $this->currentIncludes)) {
            $this->currentIncludes[] = $relation;
        }
        return $this;
    }

    /**
     * Exclude fields from transformation
     */
    public function exclude(array $fields): self
    {
        $this->excludedFields = array_merge($this->excludedFields, $fields);
        return $this;
    }

    /**
     * Parse includes from string
     */
    public function parseIncludes(string $includes): self
    {
        $requestedIncludes = array_filter(explode(',', $includes));

        foreach ($requestedIncludes as $include) {
            $this->include(trim($include));
        }

        return $this;
    }

    /**
     * Check if should include relation
     */
    protected function shouldInclude(string $relation): bool
    {
        return in_array($relation, $this->currentIncludes) ||
               in_array($relation, $this->defaultIncludes);
    }

    /**
     * Filter excluded fields from array
     */
    protected function filterExcluded(array $data): array
    {
        foreach ($this->excludedFields as $field) {
            unset($data[$field]);
        }
        return $data;
    }

    /**
     * Include related data using callback
     */
    protected function includeRelation(string $relation, mixed $item, callable $callback): ?array
    {
        if (!$this->shouldInclude($relation)) {
            return null;
        }

        return $callback($item);
    }

    /**
     * Transform nested data
     */
    protected function transformNested(mixed $data, TransformerInterface $transformer): array
    {
        if (is_iterable($data)) {
            return $transformer->transformCollection($data);
        }

        return $transformer->transform($data);
    }

    /**
     * Get current includes
     */
    public function getCurrentIncludes(): array
    {
        return array_merge($this->defaultIncludes, $this->currentIncludes);
    }

    /**
     * Get available includes
     */
    public function getAvailableIncludes(): array
    {
        return $this->availableIncludes;
    }
}

/**
 * CollectionTransformer - Transforms collections with metadata
 *
 * Handles pagination, sorting, filtering metadata alongside
 * the actual data transformation.
 */
class CollectionTransformer
{
    protected TransformerInterface $transformer;
    protected array $meta = [];

    public function __construct(TransformerInterface $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * Transform collection with metadata
     */
    public function transform(iterable $items, array $meta = []): array
    {
        return ['data' => $this->transformer->transformCollection($items),
            'meta' => array_merge($this->meta, $meta),];
    }

    /**
     * Add metadata
     */
    public function addMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Set transformer
     */
    public function setTransformer(TransformerInterface $transformer): self
    {
        $this->transformer = $transformer;
        return $this;
    }
}

/**
 * PaginatedTransformer - Transforms paginated collections
 *
 * Adds pagination metadata and links to transformed data
 * for consistent paginated API responses.
 */
class PaginatedTransformer extends CollectionTransformer
{
    /**
     * Transform paginated collection
     */
    public function transformPaginated(PaginatorInterface $paginator, array $meta = []): array
    {
        $data = $this->transformer->transformCollection($paginator->getItems());

        return ['data' => $data,
            'meta' => array_merge(['pagination' => ['current_page' => $paginator->getCurrentPage(),
                    'total_pages' => $paginator->getTotalPages(),
                    'total_items' => $paginator->getTotalItems(),
                    'per_page' => $paginator->getPerPage(),
                    'has_next' => $paginator->hasNextPage(),
                    'has_previous' => $paginator->hasPreviousPage(),],], $this->meta, $meta),
            'links' => $paginator->getLinks(),];
    }
}

/**
 * ConditionalTransformer - Applies conditional transformation
 *
 * Allows different transformation logic based on context,
 * user permissions, or other conditional factors.
 */
class ConditionalTransformer implements TransformerInterface
{
    protected array $transformers = [];
    protected ?TransformerInterface $defaultTransformer = null;

    /**
     * Add conditional transformer
     */
    public function when(callable $condition, TransformerInterface $transformer): self
    {
        $this->transformers[] = ['condition' => $condition,
            'transformer' => $transformer,];
        return $this;
    }

    /**
     * Set default transformer
     */
    public function default(TransformerInterface $transformer): self
    {
        $this->defaultTransformer = $transformer;
        return $this;
    }

    /**
     * Transform a single item
     */
    public function transform(mixed $item): array
    {
        $transformer = $this->getTransformerForItem($item);
        return $transformer ? $transformer->transform($item) : [];
    }

    /**
     * Transform a collection of items
     */
    public function transformCollection(iterable $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $results[] = $this->transform($item);
        }

        return $results;
    }

    /**
     * Set available includes
     */
    public function setAvailableIncludes(array $includes): self
    {
        foreach ($this->transformers as $config) {
            $config['transformer']->setAvailableIncludes($includes);
        }

        if ($this->defaultTransformer) {
            $this->defaultTransformer->setAvailableIncludes($includes);
        }

        return $this;
    }

    /**
     * Set default includes
     */
    public function setDefaultIncludes(array $includes): self
    {
        foreach ($this->transformers as $config) {
            $config['transformer']->setDefaultIncludes($includes);
        }

        if ($this->defaultTransformer) {
            $this->defaultTransformer->setDefaultIncludes($includes);
        }

        return $this;
    }

    /**
     * Include related data
     */
    public function include(string $relation): self
    {
        foreach ($this->transformers as $config) {
            $config['transformer']->include($relation);
        }

        if ($this->defaultTransformer) {
            $this->defaultTransformer->include($relation);
        }

        return $this;
    }

    /**
     * Exclude fields from transformation
     */
    public function exclude(array $fields): self
    {
        foreach ($this->transformers as $config) {
            $config['transformer']->exclude($fields);
        }

        if ($this->defaultTransformer) {
            $this->defaultTransformer->exclude($fields);
        }

        return $this;
    }

    /**
     * Get transformer for item based on conditions
     */
    protected function getTransformerForItem(mixed $item): ?TransformerInterface
    {
        foreach ($this->transformers as $config) {
            if ($config['condition']($item)) {
                return $config['transformer'];
            }
        }

        return $this->defaultTransformer;
    }
}

/**
 * CachedTransformer - Caches transformation results
 *
 * Wraps transformers with caching to improve performance
 * for expensive transformation operations.
 */
class CachedTransformer implements TransformerInterface
{
    protected TransformerInterface $transformer;
    protected array $cache = [];
    protected int $ttl = 3600; // 1 hour default

    public function __construct(TransformerInterface $transformer, int $ttl = 3600)
    {
        $this->transformer = $transformer;
        $this->ttl = $ttl;
    }

    /**
     * Transform a single item
     */
    public function transform(mixed $item): array
    {
        $key = $this->getCacheKey($item);

        if (isset($this->cache[$key]) && !$this->isExpired($key)) {
            return $this->cache[$key]['data'];
        }

        $result = $this->transformer->transform($item);

        $this->cache[$key] = ['data' => $result,
            'timestamp' => time(),];

        return $result;
    }

    /**
     * Transform a collection of items
     */
    public function transformCollection(iterable $items): array
    {
        $results = [];

        foreach ($items as $item) {
            $results[] = $this->transform($item);
        }

        return $results;
    }

    /**
     * Set available includes
     */
    public function setAvailableIncludes(array $includes): self
    {
        $this->transformer->setAvailableIncludes($includes);
        return $this;
    }

    /**
     * Set default includes
     */
    public function setDefaultIncludes(array $includes): self
    {
        $this->transformer->setDefaultIncludes($includes);
        return $this;
    }

    /**
     * Include related data
     */
    public function include(string $relation): self
    {
        $this->transformer->include($relation);
        return $this;
    }

    /**
     * Exclude fields from transformation
     */
    public function exclude(array $fields): self
    {
        $this->transformer->exclude($fields);
        return $this;
    }

    /**
     * Clear cache
     */
    public function clearCache(): self
    {
        $this->cache = [];
        return $this;
    }

    /**
     * Get cache key for item
     */
    protected function getCacheKey(mixed $item): string
    {
        if (is_object($item)) {
            if (method_exists($item, 'getId')) {
                return get_class($item) . ':' . $item->getId();
            }
            if (property_exists($item, 'id')) {
                return get_class($item) . ':' . $item->id;
            }
        }

        return md5(serialize($item));
    }

    /**
     * Check if cache entry is expired
     */
    protected function isExpired(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return true;
        }

        return (time() - $this->cache[$key]['timestamp']) > $this->ttl;
    }
}
