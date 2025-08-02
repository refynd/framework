<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Auth\AccessControlManager;
use Refynd\Auth\Contracts\AccessControlInterface;
use Refynd\Auth\Middleware\RoleMiddleware;
use Refynd\Auth\Middleware\PermissionMiddleware;
use Refynd\Auth\Middleware\GateMiddleware;

/**
 * RbacModule - Role-Based Access Control Module
 *
 * Provides comprehensive RBAC functionality including roles,
 * permissions, policies, and authorization middleware.
 */
class RbacModule extends Module
{
    protected Container $container;
    protected array $config = [];

    protected array $defaultConfig = ['enable_rbac' => true,
        'super_admin_bypass' => true,
        'default_role' => 'user',
        'middleware' => ['role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'gate' => GateMiddleware::class,],
        'policies' => [],
        'roles' => ['super-admin' => ['name' => 'Super Admin',
                'description' => 'Full system access',
                'permissions' => ['*']],
            'admin' => ['name' => 'Admin',
                'description' => 'Administrative access',
                'permissions' => ['users:*', 'roles:*', 'permissions:*', 'settings:*']],
            'moderator' => ['name' => 'Moderator',
                'description' => 'Content moderation',
                'permissions' => ['content:read', 'content:update', 'content:moderate']],
            'user' => ['name' => 'User',
                'description' => 'Standard user access',
                'permissions' => ['profile:read', 'profile:update']],],
        'permissions' => [// User management
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
            'settings:update' => 'Update settings',],];

    /**
     * Register module services
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        // Register configuration
        $this->registerConfig($container);

        // Register RBAC services
        $this->registerRbacServices($container);

        // Register middleware
        $this->registerMiddleware($container);
    }

    /**
     * Boot the module
     */
    public function boot(): void
    {
        if (!$this->config['enable_rbac']) {
            return;
        }

        // Set up access control manager
        $this->setupAccessControl();

        // Register policies
        $this->registerPolicies();

        // Set up default roles and permissions
        $this->setupDefaultRolesAndPermissions();
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return [AuthModule::class];
    }

    /**
     * Register RBAC configuration
     */
    protected function registerConfig(Container $container): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // Load environment variables
        $config = $this->loadEnvironmentConfig($config);

        $this->config = $config;
        $container->instance('rbac.config', $config);
    }

    /**
     * Register RBAC services
     */
    protected function registerRbacServices(Container $container): void
    {
        // Register access control manager
        $container->singleton('rbac.manager', function (Container $container) {
            return new AccessControlManager($container);
        });

        // Register access control interface
        $container->bind(AccessControlInterface::class, function (Container $container) {
            return $container->get('rbac.manager');
        });
    }

    /**
     * Register RBAC middleware
     */
    protected function registerMiddleware(Container $container): void
    {
        $middlewareConfig = $this->config['middleware'];

        foreach ($middlewareConfig as $name => $class) {
            $container->singleton("middleware.{$name}", function (Container $container) use ($class) {
                return new $class(
                    $container->get('rbac.manager'),
                    $container->get('auth.guard')
                );
            });
        }
    }

    /**
     * Set up access control manager
     */
    protected function setupAccessControl(): void
    {
        $acl = $this->container->get('rbac.manager');

        // Configure super admin bypass
        $acl->setSuperAdminBypass($this->config['super_admin_bypass']);
    }

    /**
     * Register policies from configuration
     */
    protected function registerPolicies(): void
    {
        $acl = $this->container->get('rbac.manager');
        $policies = $this->config['policies'];

        foreach ($policies as $ability => $policyClass) {
            if (class_exists($policyClass)) {
                $policy = new $policyClass($this->container);

                if (method_exists($policy, 'handle')) {
                    $acl->define($ability, [$policy, 'handle']);
                }
            }
        }
    }

    /**
     * Set up default roles and permissions
     */
    protected function setupDefaultRolesAndPermissions(): void
    {
        $acl = $this->container->get('rbac.manager');

        // Create permissions
        foreach ($this->config['permissions'] as $name => $description) {
            $parts = explode(':', $name);
            $resource = $parts[0] ?? null;
            $action = $parts[1] ?? null;

            $acl->createPermission($name, $description, $resource, $action);
        }

        // Create roles and assign permissions
        foreach ($this->config['roles'] as $slug => $roleConfig) {
            $role = $acl->createRole(
                $roleConfig['name'],
                $roleConfig['description'],
                $roleConfig['permissions'] ?? []
            );

            // Add permissions to role
            foreach ($roleConfig['permissions'] ?? [] as $permission) {
                $acl->addPermissionToRole($role->getSlug(), $permission);
            }
        }
    }

    /**
     * Load environment configuration
     */
    protected function loadEnvironmentConfig(array $config): array
    {
        // RBAC settings
        if (isset($_ENV['RBAC_ENABLED'])) {
            $config['enable_rbac'] = filter_var($_ENV['RBAC_ENABLED'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_ENV['RBAC_SUPER_ADMIN_BYPASS'])) {
            $config['super_admin_bypass'] = filter_var($_ENV['RBAC_SUPER_ADMIN_BYPASS'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_ENV['RBAC_DEFAULT_ROLE'])) {
            $config['default_role'] = $_ENV['RBAC_DEFAULT_ROLE'];
        }

        return $config;
    }

    /**
     * Create a policy class for a resource
     */
    public function createPolicy(string $resourceName, array $methods = []): string
    {
        $className = ucfirst($resourceName) . 'Policy';
        $namespace = 'App\\Policies';

        $defaultMethods = ['view', 'create', 'update', 'delete'];
        $methods = array_merge($defaultMethods, $methods);

        $policyContent = $this->generatePolicyClass($namespace, $className, $methods);

        // In a real implementation, this would write to file
        // For now, return the class name
        return $namespace . '\\' . $className;
    }

    /**
     * Generate policy class content
     */
    protected function generatePolicyClass(string $namespace, string $className, array $methods): string
    {
        $methodsCode = '';

        foreach ($methods as $method) {
            $methodsCode .= "
    public function {$method}(\$user, \$resource = null): bool
    {
        // Implement your {$method} logic here
        return false;
    }
";
        }

        return "<?php

namespace {$namespace};

use Refynd\\Container\\Container;

class {$className}
{
    protected Container \$container;

    public function __construct(Container \$container)
    {
        \$this->container = \$container;
    }

    public function handle(\$user, \$resource = null): bool
    {
        // Default policy implementation
        return false;
    }
{$methodsCode}
}";
    }

    /**
     * Add a custom role
     */
    public function addRole(string $name, string $description, array $permissions = []): void
    {
        $this->config['roles'][strtolower(str_replace(' ', '-', $name))] = ['name' => $name,
            'description' => $description,
            'permissions' => $permissions,];
    }

    /**
     * Add a custom permission
     */
    public function addPermission(string $name, string $description): void
    {
        $this->config['permissions'][$name] = $description;
    }

    /**
     * Add a policy
     */
    public function addPolicy(string $ability, string $policyClass): void
    {
        $this->config['policies'][$ability] = $policyClass;
    }

    /**
     * Get module name
     */
    public function getName(): string
    {
        return 'RBAC';
    }

    /**
     * Get module description
     */
    public function getDescription(): string
    {
        return 'Role-Based Access Control system with hierarchical roles and flexible permissions';
    }
}
