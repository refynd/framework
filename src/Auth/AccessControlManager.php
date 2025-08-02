<?php

namespace Refynd\Auth;

use Refynd\Auth\Contracts\AccessControlInterface;
use Refynd\Container\Container;

/**
 * AccessControlManager - Main RBAC orchestrator
 *
 * Provides comprehensive role-based access control with policy support,
 * hierarchical roles, and flexible permission checking.
 */
class AccessControlManager implements AccessControlInterface
{
    protected Container $container;
    protected array $roles = [];
    protected array $permissions = [];
    protected array $policies = [];
    protected array $userRoles = [];
    protected array $userPermissions = [];
    protected array $rolePermissions = [];
    protected bool $superAdminBypass = true;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->loadDefaultRoles();
        $this->loadDefaultPermissions();
    }

    /**
     * Check if a subject can perform an action
     */
    public function can(mixed $subject, string $permission, mixed $resource = null): bool
    {
        $subjectId = $this->getSubjectId($subject);

        // Super admin bypass
        if ($this->superAdminBypass && $this->isSuperAdmin($subject)) {
            return true;
        }

        // Check direct permissions
        if ($this->hasDirectPermission($subjectId, $permission)) {
            return true;
        }

        // Check role-based permissions
        if ($this->hasRolePermission($subjectId, $permission)) {
            return true;
        }

        // Check policies
        if ($this->hasPolicy($permission) && $resource !== null) {
            return $this->checkPolicy($permission, $subject, $resource);
        }

        return false;
    }

    /**
     * Check if a subject cannot perform an action
     */
    public function cannot(mixed $subject, string $permission, mixed $resource = null): bool
    {
        return !$this->can($subject, $permission, $resource);
    }

    /**
     * Authorize or throw exception
     */
    public function authorize(mixed $subject, string $permission, mixed $resource = null): void
    {
        if (!$this->can($subject, $permission, $resource)) {
            throw new AccessDeniedException(
                "Access denied for permission: {$permission}"
            );
        }
    }

    /**
     * Get all abilities for a subject
     */
    public function getAbilities(mixed $subject): array
    {
        $subjectId = $this->getSubjectId($subject);
        $abilities = [];

        // Direct permissions
        $abilities = array_merge($abilities, $this->userPermissions[$subjectId] ?? []);

        // Role permissions
        $roles = $this->userRoles[$subjectId] ?? [];
        foreach ($roles as $roleSlug) {
            if (isset($this->roles[$roleSlug])) {
                $role = $this->roles[$roleSlug];
                $abilities = array_merge($abilities, $role->getPermissions());
            }
        }

        return array_unique($abilities);
    }

    /**
     * Define a policy for a resource type
     */
    public function define(string $ability, callable $callback): void
    {
        $this->policies[$ability] = $callback;
    }

    /**
     * Clear all policy definitions
     */
    public function clear(): void
    {
        $this->policies = [];
    }

    /**
     * Create a new role
     */
    public function createRole(string $name, ?string $description = null, array $permissions = []): Role
    {
        $role = new Role($name, $description, $permissions);
        $this->roles[$role->getSlug()] = $role;

        // Store role permissions mapping
        $this->rolePermissions[$role->getSlug()] = $permissions;

        return $role;
    }

    /**
     * Get a role by slug
     */
    public function getRole(string $slug): ?Role
    {
        return $this->roles[$slug] ?? null;
    }

    /**
     * Get all roles
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Delete a role
     */
    public function deleteRole(string $slug): bool
    {
        if (!isset($this->roles[$slug])) {
            return false;
        }

        // Remove role from all users
        foreach ($this->userRoles as $userId => $roles) {
            $this->userRoles[$userId] = array_filter($roles, fn ($role) => $role !== $slug);
        }

        unset($this->roles[$slug], $this->rolePermissions[$slug]);
        return true;
    }

    /**
     * Assign role to subject
     */
    public function assignRole(mixed $subject, string $roleSlug): bool
    {
        $subjectId = $this->getSubjectId($subject);

        if (!isset($this->roles[$roleSlug])) {
            throw new \InvalidArgumentException("Role '{$roleSlug}' does not exist");
        }

        if (!isset($this->userRoles[$subjectId])) {
            $this->userRoles[$subjectId] = [];
        }

        if (!in_array($roleSlug, $this->userRoles[$subjectId])) {
            $this->userRoles[$subjectId][] = $roleSlug;
        }

        return true;
    }

    /**
     * Remove role from subject
     */
    public function removeRole(mixed $subject, string $roleSlug): bool
    {
        $subjectId = $this->getSubjectId($subject);

        if (isset($this->userRoles[$subjectId])) {
            $this->userRoles[$subjectId] = array_filter(
                $this->userRoles[$subjectId],
                fn ($role) => $role !== $roleSlug
            );
        }

        return true;
    }

    /**
     * Check if subject has role
     */
    public function hasRole(mixed $subject, string $roleSlug): bool
    {
        $subjectId = $this->getSubjectId($subject);
        return in_array($roleSlug, $this->userRoles[$subjectId] ?? []);
    }

    /**
     * Get subject roles
     */
    public function getSubjectRoles(mixed $subject): array
    {
        $subjectId = $this->getSubjectId($subject);
        $roleNames = $this->userRoles[$subjectId] ?? [];

        return array_filter(array_map(fn ($slug) => $this->roles[$slug] ?? null, $roleNames));
    }

    /**
     * Create a new permission
     */
    public function createPermission(
        string $name,
        ?string $description = null,
        ?string $resource = null,
        ?string $action = null
    ): Permission {
        $permission = new Permission($name, $description, $resource, $action);
        $this->permissions[$permission->getSlug()] = $permission;
        return $permission;
    }

    /**
     * Grant permission to subject
     */
    public function grantPermission(mixed $subject, string $permission): bool
    {
        $subjectId = $this->getSubjectId($subject);

        if (!isset($this->userPermissions[$subjectId])) {
            $this->userPermissions[$subjectId] = [];
        }

        if (!in_array($permission, $this->userPermissions[$subjectId])) {
            $this->userPermissions[$subjectId][] = $permission;
        }

        return true;
    }

    /**
     * Revoke permission from subject
     */
    public function revokePermission(mixed $subject, string $permission): bool
    {
        $subjectId = $this->getSubjectId($subject);

        if (isset($this->userPermissions[$subjectId])) {
            $this->userPermissions[$subjectId] = array_filter(
                $this->userPermissions[$subjectId],
                fn ($p) => $p !== $permission
            );
        }

        return true;
    }

    /**
     * Add permission to role
     */
    public function addPermissionToRole(string $roleSlug, string $permission): bool
    {
        if (!isset($this->roles[$roleSlug])) {
            return false;
        }

        $this->roles[$roleSlug]->addPermission($permission);

        if (!isset($this->rolePermissions[$roleSlug])) {
            $this->rolePermissions[$roleSlug] = [];
        }

        if (!in_array($permission, $this->rolePermissions[$roleSlug])) {
            $this->rolePermissions[$roleSlug][] = $permission;
        }

        return true;
    }

    /**
     * Remove permission from role
     */
    public function removePermissionFromRole(string $roleSlug, string $permission): bool
    {
        if (!isset($this->roles[$roleSlug])) {
            return false;
        }

        $this->roles[$roleSlug]->removePermission($permission);

        if (isset($this->rolePermissions[$roleSlug])) {
            $this->rolePermissions[$roleSlug] = array_filter(
                $this->rolePermissions[$roleSlug],
                fn ($p) => $p !== $permission
            );
        }

        return true;
    }

    /**
     * Enable/disable super admin bypass
     */
    public function setSuperAdminBypass(bool $enabled): self
    {
        $this->superAdminBypass = $enabled;
        return $this;
    }

    /**
     * Check if subject has direct permission
     */
    protected function hasDirectPermission(string $subjectId, string $permission): bool
    {
        $userPermissions = $this->userPermissions[$subjectId] ?? [];

        foreach ($userPermissions as $userPermission) {
            if ($this->matchesPermission($userPermission, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if subject has permission through roles
     */
    protected function hasRolePermission(string $subjectId, string $permission): bool
    {
        $roles = $this->userRoles[$subjectId] ?? [];

        foreach ($roles as $roleSlug) {
            if (isset($this->roles[$roleSlug])) {
                $role = $this->roles[$roleSlug];
                if ($role->hasPermission($permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a policy exists for permission
     */
    protected function hasPolicy(string $permission): bool
    {
        return isset($this->policies[$permission]);
    }

    /**
     * Check policy for permission
     */
    protected function checkPolicy(string $permission, mixed $subject, mixed $resource): bool
    {
        $policy = $this->policies[$permission];
        return $policy($subject, $resource);
    }

    /**
     * Check if subject is super admin
     */
    protected function isSuperAdmin(mixed $subject): bool
    {
        return $this->hasRole($subject, 'super-admin');
    }

    /**
     * Get subject ID from various subject types
     */
    protected function getSubjectId(mixed $subject): string
    {
        if (is_string($subject) || is_numeric($subject)) {
            return (string) $subject;
        }

        if (is_object($subject)) {
            if (method_exists($subject, 'getId')) {
                return (string) $subject->getId();
            }
            if (method_exists($subject, 'id')) {
                return (string) $subject->id;
            }
            if (property_exists($subject, 'id')) {
                return (string) $subject->id;
            }
        }

        return 'anonymous';
    }

    /**
     * Check if permission pattern matches target permission
     */
    protected function matchesPermission(string $pattern, string $permission): bool
    {
        if ($pattern === $permission) {
            return true;
        }

        // Wildcard matching
        $regex = str_replace(['*', '.'], ['.*', '\.'], $pattern);
        $regex = "/^{$regex}$/";

        return preg_match($regex, $permission) === 1;
    }

    /**
     * Load default roles
     */
    protected function loadDefaultRoles(): void
    {
        // Super Admin - has all permissions
        $this->createRole('Super Admin', 'Full system access', ['*']);

        // Admin - manage most resources
        $this->createRole('Admin', 'Administrative access', ['users:*', 'roles:*', 'permissions:*', 'settings:*']);

        // Moderator - moderate content
        $this->createRole('Moderator', 'Content moderation', ['content:read', 'content:update', 'content:moderate']);

        // User - basic user permissions
        $this->createRole('User', 'Standard user access', ['profile:read', 'profile:update']);

        // Guest - minimal permissions
        $this->createRole('Guest', 'Guest access', ['content:read']);
    }

    /**
     * Load default permissions
     */
    protected function loadDefaultPermissions(): void
    {
        $defaultPermissions = [// User management
            'users:create' => 'Create users',
            'users:read' => 'View users',
            'users:update' => 'Update users',
            'users:delete' => 'Delete users',

            // Role management
            'roles:create' => 'Create roles',
            'roles:read' => 'View roles',
            'roles:update' => 'Update roles',
            'roles:delete' => 'Delete roles',

            // Permission management
            'permissions:create' => 'Create permissions',
            'permissions:read' => 'View permissions',
            'permissions:update' => 'Update permissions',
            'permissions:delete' => 'Delete permissions',

            // Content management
            'content:create' => 'Create content',
            'content:read' => 'View content',
            'content:update' => 'Update content',
            'content:delete' => 'Delete content',
            'content:moderate' => 'Moderate content',

            // Profile management
            'profile:read' => 'View profile',
            'profile:update' => 'Update profile',

            // System settings
            'settings:read' => 'View settings',
            'settings:update' => 'Update settings',];

        foreach ($defaultPermissions as $name => $description) {
            $parts = explode(':', $name);
            $resource = $parts[0] ?? null;
            $action = $parts[1] ?? null;

            $this->createPermission($name, $description, $resource, $action);
        }
    }
}

/**
 * AccessDeniedException - Thrown when access is denied
 */
class AccessDeniedException extends \Exception
{
    public function __construct(string $message = 'Access Denied', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
