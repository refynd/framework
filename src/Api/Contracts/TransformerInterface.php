<?php

namespace Refynd\Api\Contracts;

/**
 * TransformerInterface - Contract for data transformation
 *
 * Defines how resources are transformed for API responses
 * with support for includes, excludes, and custom formatting.
 */
interface TransformerInterface
{
    /**
     * Transform a single item
     */
    public function transform(mixed $item): array;

    /**
     * Transform a collection of items
     */
    public function transformCollection(iterable $items): array;

    /**
     * Set available includes
     */
    public function setAvailableIncludes(array $includes): self;

    /**
     * Set default includes
     */
    public function setDefaultIncludes(array $includes): self;

    /**
     * Include related data
     */
    public function include(string $relation): self;

    /**
     * Exclude fields from transformation
     */
    public function exclude(array $fields): self;
}
