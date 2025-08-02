<?php

namespace Refynd\Database;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/**
 * Collection - Enhanced array-like container for ORM results
 *
 * Provides powerful methods for working with collections of models
 * and arrays in an elegant, fluent interface.
 *
 * @template TKey of array-key
 * @template TValue
 * @implements ArrayAccess < TKey, TValue>
 * @implements IteratorAggregate < TKey, TValue>
 */
class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Create a new collection instance
     * @return static < TKey, TValue>
     */
    public static function make(array $items = []): static
    {
        return new self($items);
    }

    /**
     * Get all items in the collection
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Get the first item from the collection
     */
    public function first(?callable $callback = null): mixed
    {
        if ($callback === null) {
            return $this->items[0] ?? null;
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Get the last item from the collection
     */
    public function last(?callable $callback = null): mixed
    {
        if ($callback === null) {
            $keys = array_keys($this->items);
            return empty($keys) ? null : $this->items[end($keys)];
        }

        $items = array_reverse($this->items, true);
        foreach ($items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Filter the collection using a callback
     * @return static < TKey, TValue>
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback === null) {
            return new self(array_filter($this->items));
        }

        return new self(array_filter($this->items, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Map each item through a callback
     * @return static < TKey, TValue>
     */
    public function map(callable $callback): static
    {
        return new self(array_map($callback, $this->items));
    }

    /**
     * Transform each item using a callback
     */
    public function transform(callable $callback): static
    {
        $this->items = array_map($callback, $this->items);
        return $this;
    }

    /**
     * Reduce the collection to a single value
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    /**
     * Get a subset of the collection
     * @return static < TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new self(array_slice($this->items, $offset, $length, true));
    }

    /**
     * Take the first n items
     */
    public function take(int $limit): static
    {
        return $this->slice(0, $limit);
    }

    /**
     * Skip the first n items
     */
    public function skip(int $offset): static
    {
        return $this->slice($offset);
    }

    /**
     * Group the collection by a key
     * @return static < TKey, TValue>
     */
    public function groupBy(string|callable $groupBy): static
    {
        $groups = [];

        foreach ($this->items as $item) {
            $key = is_callable($groupBy) ? $groupBy($item) : $this->getItemValue($item, $groupBy);

            if (!isset($groups[$key])) {
                $groups[$key] = new self();
            }

            $groups[$key]->push($item);
        }

        return new self($groups);
    }

    /**
     * Sort the collection using a callback
     * @return static < TKey, TValue>
     */
    public function sort(?callable $callback = null): static
    {
        $items = $this->items;

        if ($callback === null) {
            sort($items);
        } else {
            uasort($items, $callback);
        }

        return new self($items);
    }

    /**
     * Sort the collection by a key
     * @return static < TKey, TValue>
     */
    public function sortBy(string|callable $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        $results = [];

        foreach ($this->items as $key => $item) {
            $results[$key] = is_callable($callback) ? $callback($item) : $this->getItemValue($item, $callback);
        }

        $descending ? arsort($results, $options) : asort($results, $options);

        $sorted = [];
        foreach (array_keys($results) as $key) {
            $sorted[] = $this->items[$key];
        }

        return new self($sorted);
    }

    /**
     * Reverse the order of items
     * @return static < TKey, TValue>
     */
    public function reverse(): static
    {
        return new self(array_reverse($this->items, true));
    }

    /**
     * Check if the collection contains an item
     */
    public function contains(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if (is_callable($key)) {
                foreach ($this->items as $item) {
                    if ($key($item)) {
                        return true;
                    }
                }
                return false;
            }

            return in_array($key, $this->items, true);
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        foreach ($this->items as $item) {
            $itemValue = $this->getItemValue($item, $key);

            if ($this->operatorForWhere($itemValue, $operator, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get unique items from the collection
     * @return static < TKey, TValue>
     */
    public function unique(?string $key = null): static
    {
        if ($key === null) {
            return new self(array_unique($this->items, SORT_REGULAR));
        }

        $exists = [];
        $unique = [];

        foreach ($this->items as $item) {
            $value = $this->getItemValue($item, $key);

            if (!in_array($value, $exists, true)) {
                $exists[] = $value;
                $unique[] = $item;
            }
        }

        return new self($unique);
    }

    /**
     * Get only the specified keys from each item
     */
    public function only(array $keys): static
    {
        return $this->map(function ($item) use ($keys) {
            if (is_array($item)) {
                return array_intersect_key($item, array_flip($keys));
            }

            if ($item instanceof Model) {
                $result = [];
                foreach ($keys as $key) {
                    $result[$key] = $item->getAttribute($key);
                }
                return $result;
            }

            return $item;
        });
    }

    /**
     * Get all values for a given key
     * @return static < TKey, TValue>
     */
    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = $this->getItemValue($item, $value);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $results[$this->getItemValue($item, $key)] = $itemValue;
            }
        }

        return new self($results);
    }

    /**
     * Push an item onto the end of the collection
     */
    public function push(mixed ...$values): static
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    /**
     * Put an item in the collection by key
     */
    public function put(mixed $key, mixed $value): static
    {
        $this->items[$key] = $value;
        return $this;
    }

    /**
     * Remove and return the last item
     */
    public function pop(): mixed
    {
        return array_pop($this->items);
    }

    /**
     * Remove and return the first item
     */
    public function shift(): mixed
    {
        return array_shift($this->items);
    }

    /**
     * Get and remove an item by key
     */
    public function pull(mixed $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        unset($this->items[$key]);
        return $value;
    }

    /**
     * Get an item by key
     */
    public function get(mixed $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Check if the collection is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Check if the collection is not empty
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Convert the collection to an array
     */
    public function toArray(): array
    {
        return array_map(function ($item) {
            if ($item instanceof Model) {
                return $item->toArray();
            }

            if ($item instanceof Collection) {
                return $item->toArray();
            }

            return $item;
        }, $this->items);
    }

    /**
     * Convert the collection to JSON
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Get value from an item using dot notation
     */
    protected function getItemValue(mixed $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        if ($item instanceof Model) {
            return $item->getAttribute($key);
        }

        if (is_object($item)) {
            return $item->$key ?? null;
        }

        return null;
    }

    /**
     * Compare values using an operator
     */
    protected function operatorForWhere(mixed $value, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            '=' => $value == $expected,
            '!=' => $value != $expected,
            '<' => $value < $expected,
            '>' => $value > $expected,
            '<=' => $value <= $expected,
            '>=' => $value >= $expected,
            '===' => $value === $expected,
            '!==' => $value !== $expected,
            default => false,
        };
    }

    // ArrayAccess interface
    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function offsetGet($key): mixed
    {
        return $this->items[$key];
    }

    public function offsetSet($key, $value): void
    {
        if ($key === null) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    public function offsetUnset($key): void
    {
        unset($this->items[$key]);
    }

    // Countable interface
    public function count(): int
    {
        return count($this->items);
    }

    // IteratorAggregate interface
    /**
     * @return ArrayIterator < TKey, TValue>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    // JsonSerializable interface
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
