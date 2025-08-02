<?php

namespace Refynd\Auth;

/**
 * Permission - Represents a permission in the RBAC system
 *
 * A permission defines what actions can be performed on what resources
 * and provides granular access control throughout the application.
 */
class Permission
{
    protected string $name;
    protected string $slug;
    protected ?string $description;
    protected ?string $resource;
    protected ?string $action;
    protected array $conditions = [];
    protected array $metadata = [];

    public function __construct(
        string $name,
        ?string $description = null,
        ?string $resource = null,
        ?string $action = null
    ) {
        $this->name = $name;
        $this->slug = $this->slugify($name);
        $this->description = $description;
        $this->resource = $resource;
        $this->action = $action;
    }

    /**
     * Get permission name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get permission slug
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get permission description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set permission description
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get resource
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * Set resource
     */
    public function setResource(?string $resource): self
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * Get action
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Set action
     */
    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * Add condition
     */
    public function addCondition(string $name, callable $callback): self
    {
        $this->conditions[$name] = $callback;
        return $this;
    }

    /**
     * Remove condition
     */
    public function removeCondition(string $name): self
    {
        unset($this->conditions[$name]);
        return $this;
    }

    /**
     * Get conditions
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * Check if permission matches given criteria
     */
    public function matches(string $permission): bool
    {
        // Exact match
        if ($this->name === $permission || $this->slug === $permission) {
            return true;
        }

        // Wildcard match
        return $this->matchesWildcard($this->name, $permission);
    }

    /**
     * Check if permission is satisfied for given context
     */
    public function isSatisfied(mixed $subject, mixed $resource = null): bool
    {
        // Check all conditions
        foreach ($this->conditions as $condition) {
            if (!$condition($subject, $resource, $this)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Set metadata
     */
    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Get metadata
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Create permission from string notation
     */
    public static function fromString(string $permission): self
    {
        $parts = explode(':', $permission, 3);

        $name = $permission;
        $resource = $parts[0] ?? null;
        $action = $parts[1] ?? null;
        $description = $parts[2] ?? null;

        return new self($name, $description, $resource, $action);
    }

    /**
     * Create multiple permissions from array
     */
    public static function createMany(array $permissions): array
    {
        $result = [];

        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $result[] = self::fromString($permission);
            } elseif (is_array($permission)) {
                $result[] = new self(
                    $permission['name'],
                    $permission['description'] ?? null,
                    $permission['resource'] ?? null,
                    $permission['action'] ?? null
                );
            }
        }

        return $result;
    }

    /**
     * Get standard CRUD permissions for a resource
     */
    public static function crud(string $resource): array
    {
        return [new self("{$resource}:create", "Create {$resource}", $resource, 'create'),
            new self("{$resource}:read", "Read {$resource}", $resource, 'read'),
            new self("{$resource}:update", "Update {$resource}", $resource, 'update'),
            new self("{$resource}:delete", "Delete {$resource}", $resource, 'delete'),];
    }

    /**
     * Get admin permissions for a resource
     */
    public static function admin(string $resource): array
    {
        $crud = self::crud($resource);
        $crud[] = new self("{$resource}:*", "Full access to {$resource}", $resource, '*');

        return $crud;
    }

    /**
     * Convert permission to array
     */
    public function toArray(): array
    {
        return ['name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'resource' => $this->resource,
            'action' => $this->action,
            'metadata' => $this->metadata,];
    }

    /**
     * Check if permission matches wildcard pattern
     */
    protected function matchesWildcard(string $pattern, string $permission): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(['*', '.'], ['.*', '\.'], $pattern);
        $regex = "/^{$regex}$/";

        return preg_match($regex, $permission) === 1;
    }

    /**
     * Create URL-friendly slug from name
     */
    protected function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\-:]/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-');
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
