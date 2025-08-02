<?php

namespace Refynd\Api\Contracts;

/**
 * ApiVersionInterface - Contract for API versioning
 *
 * Defines how API versions are managed and negotiated
 * in the framework's API system.
 */
interface ApiVersionInterface
{
    /**
     * Get the version number
     */
    public function getVersion(): string;

    /**
     * Check if this version supports a feature
     */
    public function supports(string $feature): bool;

    /**
     * Get deprecation information
     */
    public function getDeprecation(): ?array;

    /**
     * Check if version is deprecated
     */
    public function isDeprecated(): bool;
}
