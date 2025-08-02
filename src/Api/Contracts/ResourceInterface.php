<?php

namespace Refynd\Api\Contracts;

/**
 * ResourceInterface - Contract for API resources
 *
 * Defines how API resources are structured and presented
 * with metadata, pagination, and transformation support.
 */
interface ResourceInterface
{
    /**
     * Transform the resource into an array
     */
    public function toArray(): array;

    /**
     * Get resource metadata
     */
    public function getMetadata(): array;

    /**
     * Set transformer for the resource
     */
    public function setTransformer(TransformerInterface $transformer): self;

    /**
     * Add metadata to the response
     */
    public function withMeta(array $meta): self;
}
