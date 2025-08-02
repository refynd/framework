<?php

namespace Refynd\Auth;

use Refynd\Auth\Contracts\AccessControlInterface;

/**
 * Role - Represents a role in the RBAC system
 *
 * A role is a collection of permissions that can be assigned
 * to users or other entities in the system.
 */
class Role
{
    protected string $name;
    protected string $slug;
    protected ?string $description;
    protected array $permissions = [];
    protected array $children = [];
    protected ?Role $parent = null;
    protected array $metadata = [];

    public function __construct(string $name, ?string $description = null, array $permissions = [])
    {
        $this->name = $name;
        $this->slug = $this->slugify($name);
        $this->description = $description;
        $this->permissions = $permissions;
    }

    /**
     * Get role name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get role slug
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get role description
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set role description
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Add permission to role
     */
    public function addPermission(string $permission): self
    {
        if (!in_array($permission, $this->permissions)) {
            $this->permissions[] = $permission;
        }
        return $this;
    }

    /**
     * Remove permission from role
     */
    public function removePermission(string $permission): self
    {
        $this->permissions = array_filter($this->permissions, fn ($p) => $p !== $permission);
        return $this;
    }

    /**
     * Check if role has permission
     */
    public function hasPermission(string $permission): bool
    {
        // Check direct permissions
        if (in_array($permission, $this->permissions)) {
            return true;
        }

        // Check wildcard permissions
        foreach ($this->permissions as $rolePermission) {
            if ($this->matchesWildcard($rolePermission, $permission)) {
                return true;
            }
        }

        // Check parent role permissions (inheritance)
        if ($this->parent) {
            return $this->parent->hasPermission($permission);
        }

        return false;
    }

    /**
     * Get all permissions (including inherited)
     */
    public function getPermissions(): array
    {
        $permissions = $this->permissions;

        // Add parent permissions
        if ($this->parent) {
            $permissions = array_merge($permissions, $this->parent->getPermissions());
        }

        return array_unique($permissions);
    }

    /**
     * Get direct permissions only
     */
    public function getDirectPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Set permissions
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Add child role
     */
    public function addChild(Role $role): self
    {
        if (!in_array($role, $this->children, true)) {
            $this->children[] = $role;
            $role->setParent($this);
        }
        return $this;
    }

    /**
     * Remove child role
     */
    public function removeChild(Role $role): self
    {
        $this->children = array_filter($this->children, fn ($child) => $child !== $role);
        $role->setParent(null);
        return $this;
    }

    /**
     * Get child roles
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Set parent role
     */
    public function setParent(?Role $parent): self
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Get parent role
     */
    public function getParent(): ?Role
    {
        return $this->parent;
    }

    /**
     * Check if role is ancestor of another role
     */
    public function isAncestorOf(Role $role): bool
    {
        foreach ($this->children as $child) {
            if ($child === $role || $child->isAncestorOf($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if role is descendant of another role
     */
    public function isDescendantOf(Role $role): bool
    {
        if ($this->parent === $role) {
            return true;
        }

        if ($this->parent) {
            return $this->parent->isDescendantOf($role);
        }

        return false;
    }

    /**
     * Get all ancestor roles
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;

        while ($parent) {
            $ancestors[] = $parent;
            $parent = $parent->getParent();
        }

        return $ancestors;
    }

    /**
     * Get all descendant roles
     */
    public function getDescendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }

        return $descendants;
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
     * Convert role to array
     */
    public function toArray(): array
    {
        return ['name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'permissions' => $this->permissions,
            'parent' => $this->parent?->getSlug(),
            'children' => array_map(fn ($child) => $child->getSlug(), $this->children),
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
        $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
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
