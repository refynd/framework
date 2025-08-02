<?php

namespace Refynd\Api;

use Refynd\Api\Contracts\ResourceInterface;
use Refynd\Api\Contracts\TransformerInterface;
use Refynd\Api\Contracts\PaginatorInterface;

/**
 * ApiResource - Base class for API resources
 *
 * Provides structured API responses with transformation,
 * metadata, and consistent formatting.
 */
abstract class ApiResource implements ResourceInterface
{
    protected mixed $resource;
    protected ?TransformerInterface $transformer = null;
    protected array $meta = [];
    protected array $links = [];
    protected int $statusCode = 200;

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Transform the resource into an array
     */
    public function toArray(): array
    {
        $data = $this->transformer
            ? $this->transformer->transform($this->resource)
            : $this->transformDefault();

        $response = ['data' => $data];

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        if (!empty($this->links)) {
            $response['links'] = $this->links;
        }

        return $response;
    }

    /**
     * Get resource metadata
     */
    public function getMetadata(): array
    {
        return $this->meta;
    }

    /**
     * Set transformer for the resource
     */
    public function setTransformer(TransformerInterface $transformer): self
    {
        $this->transformer = $transformer;
        return $this;
    }

    /**
     * Add metadata to the response
     */
    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Add links to the response
     */
    public function withLinks(array $links): self
    {
        $this->links = array_merge($this->links, $links);
        return $this;
    }

    /**
     * Set HTTP status code
     */
    public function withStatus(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Default transformation if no transformer is set
     */
    protected function transformDefault(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource) && method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }

        return ['value' => $this->resource];
    }

    /**
     * Create resource instance
     */
    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * Create collection resource
     */
    public static function collection(iterable $resources): ApiResourceCollection
    {
        return new ApiResourceCollection($resources, static::class);
    }
}

/**
 * ApiResourceCollection - Collection of API resources
 *
 * Handles arrays and collections of resources with
 * pagination support and bulk transformation.
 */
class ApiResourceCollection implements ResourceInterface
{
    protected iterable $collection;
    protected ?string $resourceClass = null;
    protected ?TransformerInterface $transformer = null;
    protected array $meta = [];
    protected array $links = [];
    protected ?PaginatorInterface $paginator = null;

    public function __construct(iterable $collection, ?string $resourceClass = null)
    {
        $this->collection = $collection;
        $this->resourceClass = $resourceClass;
    }

    /**
     * Transform the resource into an array
     */
    public function toArray(): array
    {
        if ($this->paginator) {
            return $this->toPaginatedArray();
        }

        $data = $this->transformCollection();

        $response = ['data' => $data];

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        if (!empty($this->links)) {
            $response['links'] = $this->links;
        }

        return $response;
    }

    /**
     * Get resource metadata
     */
    public function getMetadata(): array
    {
        return $this->meta;
    }

    /**
     * Set transformer for the resource
     */
    public function setTransformer(TransformerInterface $transformer): self
    {
        $this->transformer = $transformer;
        return $this;
    }

    /**
     * Add metadata to the response
     */
    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Add links to the response
     */
    public function withLinks(array $links): self
    {
        $this->links = array_merge($this->links, $links);
        return $this;
    }

    /**
     * Set paginator
     */
    public function paginate(PaginatorInterface $paginator): self
    {
        $this->paginator = $paginator;
        return $this;
    }

    /**
     * Transform collection to paginated array
     */
    protected function toPaginatedArray(): array
    {
        $data = $this->transformCollection();

        return ['data' => $data,
            'meta' => array_merge(['pagination' => ['current_page' => $this->paginator->getCurrentPage(),
                    'total_pages' => $this->paginator->getTotalPages(),
                    'total_items' => $this->paginator->getTotalItems(),
                    'per_page' => $this->paginator->getPerPage(),
                    'has_next' => $this->paginator->hasNextPage(),
                    'has_previous' => $this->paginator->hasPreviousPage(),],], $this->meta),
            'links' => array_merge($this->paginator->getLinks(), $this->links),];
    }

    /**
     * Transform collection items
     */
    protected function transformCollection(): array
    {
        $results = [];

        foreach ($this->collection as $item) {
            if ($this->transformer) {
                $results[] = $this->transformer->transform($item);
            } elseif ($this->resourceClass) {
                $resource = new $this->resourceClass($item);
                $results[] = $resource->toArray()['data'];
            } else {
                $results[] = $this->transformDefault($item);
            }
        }

        return $results;
    }

    /**
     * Default transformation for item
     */
    protected function transformDefault(mixed $item): array
    {
        if (is_array($item)) {
            return $item;
        }

        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return ['value' => $item];
    }
}

/**
 * JsonApiResource - JSON:API compliant resource
 *
 * Implements JSON:API specification for resource formatting
 * with type, id, attributes, and relationships.
 */
class JsonApiResource extends ApiResource
{
    protected string $type;
    protected ?string $id = null;

    public function __construct(mixed $resource, string $type, ?string $id = null)
    {
        parent::__construct($resource);
        $this->type = $type;
        $this->id = $id ?? $this->extractId();
    }

    /**
     * Transform to JSON:API format
     */
    public function toArray(): array
    {
        $data = ['type' => $this->type,
            'id' => $this->id,
            'attributes' => $this->getAttributes(),];

        $relationships = $this->getRelationships();
        if (!empty($relationships)) {
            $data['relationships'] = $relationships;
        }

        $response = ['data' => $data];

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        if (!empty($this->links)) {
            $response['links'] = $this->links;
        }

        return $response;
    }

    /**
     * Get resource attributes
     */
    protected function getAttributes(): array
    {
        if ($this->transformer) {
            return $this->transformer->transform($this->resource);
        }

        return $this->transformDefault();
    }

    /**
     * Get resource relationships
     */
    protected function getRelationships(): array
    {
        // Override in subclasses to define relationships
        return [];
    }

    /**
     * Extract ID from resource
     */
    protected function extractId(): ?string
    {
        if (is_object($this->resource)) {
            if (method_exists($this->resource, 'getId')) {
                return (string) $this->resource->getId();
            }
            if (property_exists($this->resource, 'id')) {
                return (string) $this->resource->id;
            }
        }

        if (is_array($this->resource) && isset($this->resource['id'])) {
            return (string) $this->resource['id'];
        }

        return null;
    }
}

/**
 * ErrorResource - Standardized error responses
 *
 * Provides consistent error formatting with codes,
 * messages, and debugging information.
 */
class ErrorResource implements ResourceInterface
{
    protected array $errors;
    protected int $statusCode;
    protected array $meta = [];

    public function __construct(array $errors, int $statusCode = 400)
    {
        $this->errors = $errors;
        $this->statusCode = $statusCode;
    }

    /**
     * Transform the resource into an array
     */
    public function toArray(): array
    {
        $response = ['errors' => $this->formatErrors()];

        if (!empty($this->meta)) {
            $response['meta'] = $this->meta;
        }

        return $response;
    }

    /**
     * Get resource metadata
     */
    public function getMetadata(): array
    {
        return $this->meta;
    }

    /**
     * Set transformer for the resource
     */
    public function setTransformer(TransformerInterface $transformer): self
    {
        // Errors don't use transformers
        return $this;
    }

    /**
     * Add metadata to the response
     */
    public function withMeta(array $meta): self
    {
        $this->meta = array_merge($this->meta, $meta);
        return $this;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Format errors consistently
     */
    protected function formatErrors(): array
    {
        $formatted = [];

        foreach ($this->errors as $error) {
            if (is_string($error)) {
                $formatted[] = ['code' => 'generic_error',
                    'message' => $error,];
            } elseif (is_array($error)) {
                $formatted[] = array_merge(['code' => 'unknown',
                    'message' => 'An error occurred',], $error);
            }
        }

        return $formatted;
    }

    /**
     * Create single error
     */
    public static function single(string $message, string $code = 'error', int $statusCode = 400): self
    {
        return new self([['code' => $code, 'message' => $message]], $statusCode);
    }

    /**
     * Create validation errors
     */
    public static function validation(array $errors): self
    {
        $formatted = [];

        foreach ($errors as $field => $messages) {
            if (is_array($messages)) {
                foreach ($messages as $message) {
                    $formatted[] = ['code' => 'validation_error',
                        'message' => $message,
                        'field' => $field,];
                }
            } else {
                $formatted[] = ['code' => 'validation_error',
                    'message' => $messages,
                    'field' => $field,];
            }
        }

        return new self($formatted, 422);
    }
}
