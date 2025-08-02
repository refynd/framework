<?php

namespace Refynd\Api\Contracts;

/**
 * PaginatorInterface - Contract for API pagination
 *
 * Defines how paginated responses are structured and navigated
 * with links, metadata, and cursor support.
 */
interface PaginatorInterface
{
    /**
     * Get items for current page
     */
    public function getItems(): iterable;

    /**
     * Get current page number
     */
    public function getCurrentPage(): int;

    /**
     * Get total number of pages
     */
    public function getTotalPages(): int;

    /**
     * Get total number of items
     */
    public function getTotalItems(): int;

    /**
     * Get items per page
     */
    public function getPerPage(): int;

    /**
     * Check if there's a next page
     */
    public function hasNextPage(): bool;

    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage(): bool;

    /**
     * Get pagination links
     */
    public function getLinks(): array;

    /**
     * Transform to array representation
     */
    public function toArray(): array;
}
