<?php

namespace Refynd\Auth\Contracts;

/**
 * PermissionInterface - Contract for permission system
 *
 * Defines the interface for managing and checking permissions
 * in the framework's RBAC system.
 */
interface PermissionInterface
{
    /**
     * Check if a permission exists
     */
    public function exists(string $permission): bool;

    /**
     * Grant a permission to a subject
     */
    public function grant(mixed $subject, string $permission): bool;

    /**
     * Revoke a permission from a subject
     */
    public function revoke(mixed $subject, string $permission): bool;

    /**
     * Check if a subject has a permission
     */
    public function has(mixed $subject, string $permission): bool;

    /**
     * Get all permissions for a subject
     */
    public function getPermissions(mixed $subject): array;

    /**
     * Check if a subject has any of the given permissions
     */
    public function hasAny(mixed $subject, array $permissions): bool;

    /**
     * Check if a subject has all of the given permissions
     */
    public function hasAll(mixed $subject, array $permissions): bool;
}

/**
 * RoleInterface - Contract for role system
 *
 * Defines the interface for managing roles and their
 * hierarchical relationships.
 */
interface RoleInterface
{
    /**
     * Check if a role exists
     */
    public function exists(string $role): bool;

    /**
     * Create a new role
     */
    public function create(string $role, array $permissions = []): bool;

    /**
     * Delete a role
     */
    public function delete(string $role): bool;

    /**
     * Assign a role to a subject
     */
    public function assign(mixed $subject, string $role): bool;

    /**
     * Remove a role from a subject
     */
    public function remove(mixed $subject, string $role): bool;

    /**
     * Check if a subject has a role
     */
    public function has(mixed $subject, string $role): bool;

    /**
     * Get all roles for a subject
     */
    public function getRoles(mixed $subject): array;

    /**
     * Get all permissions for a role
     */
    public function getPermissions(string $role): array;

    /**
     * Add permission to a role
     */
    public function addPermission(string $role, string $permission): bool;

    /**
     * Remove permission from a role
     */
    public function removePermission(string $role, string $permission): bool;
}

/**
 * AccessControlInterface - Main RBAC contract
 *
 * Provides unified interface for role-based access control
 * combining roles and permissions.
 */
interface AccessControlInterface
{
    /**
     * Check if a subject can perform an action
     */
    public function can(mixed $subject, string $permission, mixed $resource = null): bool;

    /**
     * Check if a subject cannot perform an action
     */
    public function cannot(mixed $subject, string $permission, mixed $resource = null): bool;

    /**
     * Authorize or throw exception
     */
    public function authorize(mixed $subject, string $permission, mixed $resource = null): void;

    /**
     * Get all abilities for a subject
     */
    public function getAbilities(mixed $subject): array;

    /**
     * Define a policy for a resource type
     */
    public function define(string $ability, callable $callback): void;

    /**
     * Clear all policy definitions
     */
    public function clear(): void;
}
