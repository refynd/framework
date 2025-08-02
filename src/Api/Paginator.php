<?php

namespace Refynd\Api;

use Refynd\Api\Contracts\PaginatorInterface;

/**
 * Paginator - Advanced pagination implementation
 *
 * Provides flexible pagination with cursor support, metadata,
 * and navigation links for professional API responses.
 */
class Paginator implements PaginatorInterface
{
    protected iterable $items;
    protected int $currentPage;
    protected int $perPage;
    protected int $totalItems;
    protected int $totalPages;
    protected array $options;
    protected ?string $path = null;
    protected array $query = [];

    public function __construct(
        iterable $items,
        int $totalItems,
        int $perPage = 15,
        int $currentPage = 1,
        array $options = []
    ) {
        $this->items = $items;
        $this->totalItems = $totalItems;
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->totalPages = (int) ceil($totalItems / $this->perPage);
        $this->options = array_merge(['path' => '',
            'query' => [],
            'fragment' => null,], $options);
    }

    /**
     * Get items for current page
     */
    public function getItems(): iterable
    {
        return $this->items;
    }

    /**
     * Get current page number
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get total number of pages
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * Get total number of items
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * Get items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Check if there's a next page
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Get first item number on current page
     */
    public function getFirstItem(): int
    {
        if ($this->totalItems === 0) {
            return 0;
        }
        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    /**
     * Get last item number on current page
     */
    public function getLastItem(): int
    {
        return min($this->getFirstItem() + $this->perPage - 1, $this->totalItems);
    }

    /**
     * Get pagination links
     */
    public function getLinks(): array
    {
        $links = [];

        // Self link
        $links['self'] = $this->buildUrl($this->currentPage);

        // First page
        $links['first'] = $this->buildUrl(1);

        // Last page
        $links['last'] = $this->buildUrl($this->totalPages);

        // Previous page
        if ($this->hasPreviousPage()) {
            $links['prev'] = $this->buildUrl($this->currentPage - 1);
        }

        // Next page
        if ($this->hasNextPage()) {
            $links['next'] = $this->buildUrl($this->currentPage + 1);
        }

        return $links;
    }

    /**
     * Get navigation links with page numbers
     */
    public function getNavigationLinks(int $onEachSide = 3): array
    {
        $links = [];

        $start = max(1, $this->currentPage - $onEachSide);
        $end = min($this->totalPages, $this->currentPage + $onEachSide);

        // Add first page and ellipsis if needed
        if ($start > 1) {
            $links[] = ['page' => 1,
                'url' => $this->buildUrl(1),
                'active' => false,];

            if ($start > 2) {
                $links[] = ['page' => '...',
                    'url' => null,
                    'active' => false,];
            }
        }

        // Add page range
        for ($page = $start; $page <= $end; $page++) {
            $links[] = ['page' => $page,
                'url' => $this->buildUrl($page),
                'active' => $page === $this->currentPage,];
        }

        // Add ellipsis and last page if needed
        if ($end < $this->totalPages) {
            if ($end < $this->totalPages - 1) {
                $links[] = ['page' => '...',
                    'url' => null,
                    'active' => false,];
            }

            $links[] = ['page' => $this->totalPages,
                'url' => $this->buildUrl($this->totalPages),
                'active' => false,];
        }

        return $links;
    }

    /**
     * Transform to array representation
     */
    public function toArray(): array
    {
        return ['current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'total_items' => $this->totalItems,
            'per_page' => $this->perPage,
            'first_item' => $this->getFirstItem(),
            'last_item' => $this->getLastItem(),
            'has_next' => $this->hasNextPage(),
            'has_previous' => $this->hasPreviousPage(),
            'links' => $this->getLinks(),];
    }

    /**
     * Set base path for links
     */
    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set query parameters for links
     */
    public function withQuery(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Build URL for page
     */
    protected function buildUrl(int $page): string
    {
        $query = array_merge($this->query, ['page' => $page]);
        $queryString = http_build_query($query);

        $url = $this->path ?: '';

        if ($queryString) {
            $url .= '?' . $queryString;
        }

        return $url;
    }

    /**
     * Create paginator from array
     */
    public static function fromArray(array $data, int $perPage = 15, int $currentPage = 1): self
    {
        $totalItems = count($data);
        $offset = ($currentPage - 1) * $perPage;
        $items = array_slice($data, $offset, $perPage);

        return new self($items, $totalItems, $perPage, $currentPage);
    }
}

/**
 * CursorPaginator - Cursor-based pagination
 *
 * Provides cursor pagination for large datasets with
 * stable ordering and efficient navigation.
 */
class CursorPaginator implements PaginatorInterface
{
    protected iterable $items;
    protected int $perPage;
    protected ?string $nextCursor;
    protected ?string $prevCursor;
    protected ?string $path = null;
    protected array $query = [];

    public function __construct(
        iterable $items,
        int $perPage = 15,
        ?string $nextCursor = null,
        ?string $prevCursor = null
    ) {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->nextCursor = $nextCursor;
        $this->prevCursor = $prevCursor;
    }

    /**
     * Get items for current page
     */
    public function getItems(): iterable
    {
        return $this->items;
    }

    /**
     * Get current page number (not applicable for cursor pagination)
     */
    public function getCurrentPage(): int
    {
        return 1; // Cursor pagination doesn't have page numbers
    }

    /**
     * Get total number of pages (not applicable for cursor pagination)
     */
    public function getTotalPages(): int
    {
        return 1; // Unknown for cursor pagination
    }

    /**
     * Get total number of items (not applicable for cursor pagination)
     */
    public function getTotalItems(): int
    {
        return -1; // Unknown for cursor pagination
    }

    /**
     * Get items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Check if there's a next page
     */
    public function hasNextPage(): bool
    {
        return $this->nextCursor !== null;
    }

    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->prevCursor !== null;
    }

    /**
     * Get next cursor
     */
    public function getNextCursor(): ?string
    {
        return $this->nextCursor;
    }

    /**
     * Get previous cursor
     */
    public function getPrevCursor(): ?string
    {
        return $this->prevCursor;
    }

    /**
     * Get pagination links
     */
    public function getLinks(): array
    {
        $links = [];

        // Previous page
        if ($this->hasPreviousPage()) {
            $links['prev'] = $this->buildCursorUrl($this->prevCursor, 'before');
        }

        // Next page
        if ($this->hasNextPage()) {
            $links['next'] = $this->buildCursorUrl($this->nextCursor, 'after');
        }

        return $links;
    }

    /**
     * Transform to array representation
     */
    public function toArray(): array
    {
        return ['per_page' => $this->perPage,
            'has_next' => $this->hasNextPage(),
            'has_previous' => $this->hasPreviousPage(),
            'next_cursor' => $this->nextCursor,
            'prev_cursor' => $this->prevCursor,
            'links' => $this->getLinks(),];
    }

    /**
     * Set base path for links
     */
    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set query parameters for links
     */
    public function withQuery(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Build cursor URL
     */
    protected function buildCursorUrl(string $cursor, string $direction): string
    {
        $query = array_merge($this->query, ['cursor' => $cursor,
            'direction' => $direction,]);

        $queryString = http_build_query($query);
        $url = $this->path ?: '';

        if ($queryString) {
            $url .= '?' . $queryString;
        }

        return $url;
    }

    /**
     * Generate cursor from item
     */
    public static function generateCursor(mixed $item, array $fields = ['id']): string
    {
        $values = [];

        foreach ($fields as $field) {
            if (is_object($item)) {
                if (method_exists($item, 'get' . ucfirst($field))) {
                    $values[] = $item->{'get' . ucfirst($field)}();
                } elseif (property_exists($item, $field)) {
                    $values[] = $item->$field;
                }
            } elseif (is_array($item) && isset($item[$field])) {
                $values[] = $item[$field];
            }
        }

        return base64_encode(json_encode($values));
    }

    /**
     * Parse cursor to values
     */
    public static function parseCursor(string $cursor): array
    {
        $decoded = base64_decode($cursor);
        return json_decode($decoded, true) ?: [];
    }
}

/**
 * LengthAwarePaginator - Paginator with known total count
 *
 * Traditional pagination with full knowledge of total items
 * and page count for complete navigation.
 */
class LengthAwarePaginator extends Paginator
{
    /**
     * Create from query builder or collection
     */
    public static function create(
        iterable $items,
        int $totalItems,
        int $perPage = 15,
        int $currentPage = 1,
        array $options = []
    ): self {
        return new self($items, $totalItems, $perPage, $currentPage, $options);
    }

    /**
     * Get offset for current page
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }

    /**
     * Get range information
     */
    public function getRangeInfo(): string
    {
        if ($this->totalItems === 0) {
            return "No items found";
        }

        $first = $this->getFirstItem();
        $last = $this->getLastItem();

        return "Showing {$first} to {$last} of {$this->totalItems} results";
    }

    /**
     * Check if on first page
     */
    public function isFirstPage(): bool
    {
        return $this->currentPage === 1;
    }

    /**
     * Check if on last page
     */
    public function isLastPage(): bool
    {
        return $this->currentPage === $this->totalPages;
    }

    /**
     * Get pages around current page
     */
    public function getPagesAround(int $range = 2): array
    {
        $start = max(1, $this->currentPage - $range);
        $end = min($this->totalPages, $this->currentPage + $range);

        return range($start, $end);
    }
}

/**
 * SimplePaginator - Simple pagination without total count
 *
 * Lightweight pagination that only knows about next/previous
 * without total count for performance-sensitive applications.
 */
class SimplePaginator implements PaginatorInterface
{
    protected iterable $items;
    protected int $perPage;
    protected int $currentPage;
    protected bool $hasMore;
    protected ?string $path = null;
    protected array $query = [];

    public function __construct(
        iterable $items,
        int $perPage = 15,
        int $currentPage = 1,
        bool $hasMore = false
    ) {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->hasMore = $hasMore;
    }

    /**
     * Get items for current page
     */
    public function getItems(): iterable
    {
        return $this->items;
    }

    /**
     * Get current page number
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get total number of pages (unknown for simple pagination)
     */
    public function getTotalPages(): int
    {
        return -1; // Unknown
    }

    /**
     * Get total number of items (unknown for simple pagination)
     */
    public function getTotalItems(): int
    {
        return -1; // Unknown
    }

    /**
     * Get items per page
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Check if there's a next page
     */
    public function hasNextPage(): bool
    {
        return $this->hasMore;
    }

    /**
     * Check if there's a previous page
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Get pagination links
     */
    public function getLinks(): array
    {
        $links = [];

        // Previous page
        if ($this->hasPreviousPage()) {
            $links['prev'] = $this->buildUrl($this->currentPage - 1);
        }

        // Next page
        if ($this->hasNextPage()) {
            $links['next'] = $this->buildUrl($this->currentPage + 1);
        }

        return $links;
    }

    /**
     * Transform to array representation
     */
    public function toArray(): array
    {
        return ['current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'has_next' => $this->hasNextPage(),
            'has_previous' => $this->hasPreviousPage(),
            'links' => $this->getLinks(),];
    }

    /**
     * Set base path for links
     */
    public function withPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Set query parameters for links
     */
    public function withQuery(array $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Build URL for page
     */
    protected function buildUrl(int $page): string
    {
        $query = array_merge($this->query, ['page' => $page]);
        $queryString = http_build_query($query);

        $url = $this->path ?: '';

        if ($queryString) {
            $url .= '?' . $queryString;
        }

        return $url;
    }
}

/**
 * PaginatorFactory - Factory for creating paginators
 *
 * Provides convenient methods for creating different types
 * of paginators based on requirements.
 */
class PaginatorFactory
{
    /**
     * Create length-aware paginator
     */
    public static function lengthAware(
        iterable $items,
        int $totalItems,
        int $perPage = 15,
        int $currentPage = 1,
        array $options = []
    ): LengthAwarePaginator {
        return new LengthAwarePaginator($items, $totalItems, $perPage, $currentPage, $options);
    }

    /**
     * Create simple paginator
     */
    public static function simple(
        iterable $items,
        int $perPage = 15,
        int $currentPage = 1,
        bool $hasMore = false
    ): SimplePaginator {
        return new SimplePaginator($items, $perPage, $currentPage, $hasMore);
    }

    /**
     * Create cursor paginator
     */
    public static function cursor(
        iterable $items,
        int $perPage = 15,
        ?string $nextCursor = null,
        ?string $prevCursor = null
    ): CursorPaginator {
        return new CursorPaginator($items, $perPage, $nextCursor, $prevCursor);
    }

    /**
     * Create paginator from array with auto-detection
     */
    public static function fromArray(
        array $data,
        int $perPage = 15,
        int $currentPage = 1,
        ?string $type = null
    ): PaginatorInterface {
        $type = $type ?: 'length_aware';

        return match ($type) {
            'simple' => self::simple(
                array_slice($data, ($currentPage - 1) * $perPage, $perPage),
                $perPage,
                $currentPage,
                count($data) > $currentPage * $perPage
            ),
            'cursor' => self::cursor(
                array_slice($data, 0, $perPage),
                $perPage
            ),
            default => self::lengthAware(
                array_slice($data, ($currentPage - 1) * $perPage, $perPage),
                count($data),
                $perPage,
                $currentPage
            )
        };
    }
}
