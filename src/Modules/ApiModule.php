<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Api\ApiVersion;
use Refynd\Api\ApiVersionManager;
use Refynd\Api\PaginatorFactory;
use Refynd\Api\Contracts\PaginatorInterface;

/**
 * ApiModule - API Development Suite Module
 *
 * Provides comprehensive API functionality including versioning,
 * resource transformation, pagination, and documentation generation.
 */
class ApiModule extends Module
{
    protected Container $container;
    protected array $config = [];

    protected array $defaultConfig = [
        'enable_api' => true,
        'default_version' => '1.0',
        'api_prefix' => '/api',
        'pagination' => [
            'default_per_page' => 15,
            'max_per_page' => 100,
            'type' => 'length_aware', // length_aware, simple, cursor
        ],
        'versioning' => [
            'strategy' => 'header', // header, path, query
            'header_name' => 'API-Version',
            'path_pattern' => '/v{version}',
        ],
        'response' => [
            'format' => 'json', // json, jsonapi
            'include_meta' => true,
            'include_links' => true,
            'wrap_single_resource' => true,
        ],
        'features' => [
            'rate_limiting' => true,
            'caching' => true,
            'documentation' => true,
            'validation' => true,
        ],
        'versions' => [
            '1.0' => [
                'features' => ['basic_crud', 'authentication'],
                'deprecation' => null,
            ],
            '1.1' => [
                'features' => ['basic_crud', 'authentication', 'advanced_filtering'],
                'deprecation' => null,
            ],
            '2.0' => [
                'features' => ['basic_crud', 'authentication', 'advanced_filtering', 'real_time'],
                'deprecation' => null,
            ],
        ],
    ];

    /**
     * Register module services
     */
    public function register(Container $container): void
    {
        $this->container = $container;

        // Register configuration
        $this->registerConfig($container);

        // Register API services
        $this->registerApiServices($container);

        // Register pagination services
        $this->registerPaginationServices($container);

        // Register versioning services
        $this->registerVersioningServices($container);
    }

    /**
     * Boot the module
     */
    public function boot(): void
    {
        if (!$this->config['enable_api']) {
            return;
        }

        // Set up API versions
        $this->setupApiVersions();

        // Configure pagination defaults
        $this->configurePagination();

        // Register API documentation if enabled
        if ($this->config['features']['documentation']) {
            $this->registerDocumentation();
        }
    }

    /**
     * Register API configuration
     */
    protected function registerConfig(Container $container): void
    {
        $config = array_merge($this->defaultConfig, $this->config);

        // Load environment variables
        $config = $this->loadEnvironmentConfig($config);

        $this->config = $config;
        $container->instance('api.config', $config);
    }

    /**
     * Register API services
     */
    protected function registerApiServices(Container $container): void
    {
        // Register API version manager
        $container->singleton('api.version.manager', function () {
            return new ApiVersionManager();
        });

        // Register API response builder
        $container->singleton('api.response.builder', function (Container $container) {
            return new ApiResponseBuilder($container->get('api.config'));
        });
    }

    /**
     * Register pagination services
     */
    protected function registerPaginationServices(Container $container): void
    {
        // Register paginator factory
        $container->singleton('api.paginator.factory', function () {
            return new PaginatorFactory();
        });

        // Register pagination resolver
        $container->singleton('api.pagination.resolver', function (Container $container) {
            return new PaginationResolver($container->get('api.config')['pagination']);
        });
    }

    /**
     * Register versioning services
     */
    protected function registerVersioningServices(Container $container): void
    {
        // Register version negotiator
        $container->singleton('api.version.negotiator', function (Container $container) {
            return new VersionNegotiator(
                $container->get('api.version.manager'),
                $container->get('api.config')['versioning']
            );
        });
    }

    /**
     * Set up API versions from configuration
     */
    protected function setupApiVersions(): void
    {
        $versionManager = $this->container->get('api.version.manager');
        $versionsConfig = $this->config['versions'];

        foreach ($versionsConfig as $versionNumber => $versionConfig) {
            $version = new ApiVersion(
                $versionNumber,
                $versionConfig['features'] ?? [],
                $versionConfig['deprecation'] ?? null
            );

            // Add version-specific changes
            if (isset($versionConfig['changes'])) {
                foreach ($versionConfig['changes'] as $change) {
                    $version->addChange($change['type'], $change['description']);
                }
            }

            $versionManager->register($version);
        }

        // Set default version
        $versionManager->setDefault($this->config['default_version']);
    }

    /**
     * Configure pagination defaults
     */
    protected function configurePagination(): void
    {
        $resolver = $this->container->get('api.pagination.resolver');
        $config = $this->config['pagination'];

        $resolver->setDefaultPerPage($config['default_per_page']);
        $resolver->setMaxPerPage($config['max_per_page']);
        $resolver->setDefaultType($config['type']);
    }

    /**
     * Register API documentation
     */
    protected function registerDocumentation(): void
    {
        // This would integrate with a documentation generator
        // For now, we'll create a simple endpoint registry
        $this->container->singleton('api.documentation', function (Container $container) {
            return new ApiDocumentation($container);
        });
    }

    /**
     * Load environment configuration
     */
    protected function loadEnvironmentConfig(array $config): array
    {
        // API settings
        if (isset($_ENV['API_ENABLED'])) {
            $config['enable_api'] = filter_var($_ENV['API_ENABLED'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($_ENV['API_DEFAULT_VERSION'])) {
            $config['default_version'] = $_ENV['API_DEFAULT_VERSION'];
        }

        if (isset($_ENV['API_PREFIX'])) {
            $config['api_prefix'] = $_ENV['API_PREFIX'];
        }

        // Pagination settings
        if (isset($_ENV['API_DEFAULT_PER_PAGE'])) {
            $config['pagination']['default_per_page'] = (int) $_ENV['API_DEFAULT_PER_PAGE'];
        }

        if (isset($_ENV['API_MAX_PER_PAGE'])) {
            $config['pagination']['max_per_page'] = (int) $_ENV['API_MAX_PER_PAGE'];
        }

        return $config;
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Get module name
     */
    public function getName(): string
    {
        return 'API';
    }

    /**
     * Get module description
     */
    public function getDescription(): string
    {
        return 'Comprehensive API development suite with versioning, pagination, and resource transformation';
    }
}

/**
 * ApiResponseBuilder - Builds standardized API responses
 */
class ApiResponseBuilder
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Build success response
     */
    public function success(mixed $data, array $meta = [], int $statusCode = 200): array
    {
        $response = [];

        if ($this->config['response']['wrap_single_resource'] || is_array($data)) {
            $response['data'] = $data;
        } else {
            $response = $data;
        }

        if ($this->config['response']['include_meta'] && !empty($meta)) {
            $response['meta'] = $meta;
        }

        return $response;
    }

    /**
     * Build error response
     */
    public function error(string $message, int $statusCode = 400, ?string $code = null): array
    {
        return [
            'error' => [
                'code' => $code ?: 'error',
                'message' => $message,
                'status' => $statusCode,
            ],
        ];
    }

    /**
     * Build paginated response
     */
    public function paginated(iterable $data, PaginatorInterface $paginator, array $meta = []): array
    {
        $response = [
            'data' => $data,
            'meta' => array_merge([
                'pagination' => $paginator->toArray(),
            ], $meta),
        ];

        if ($this->config['response']['include_links']) {
            $response['links'] = $paginator->getLinks();
        }

        return $response;
    }
}

/**
 * PaginationResolver - Resolves pagination parameters from requests
 */
class PaginationResolver
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Resolve pagination from request parameters
     */
    public function resolve(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(
            $this->config['max_per_page'],
            max(1, (int) ($params['per_page'] ?? $this->config['default_per_page']))
        );

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    /**
     * Set default per page
     */
    public function setDefaultPerPage(int $perPage): void
    {
        $this->config['default_per_page'] = $perPage;
    }

    /**
     * Set max per page
     */
    public function setMaxPerPage(int $perPage): void
    {
        $this->config['max_per_page'] = $perPage;
    }

    /**
     * Set default pagination type
     */
    public function setDefaultType(string $type): void
    {
        $this->config['type'] = $type;
    }
}

/**
 * VersionNegotiator - Handles API version negotiation
 */
class VersionNegotiator
{
    protected ApiVersionManager $versionManager;
    protected array $config;

    public function __construct(ApiVersionManager $versionManager, array $config)
    {
        $this->versionManager = $versionManager;
        $this->config = $config;
    }

    /**
     * Negotiate version from request
     */
    public function negotiate(array $headers, string $path, array $query): ?ApiVersion
    {
        $strategy = $this->config['strategy'];

        return match ($strategy) {
            'header' => $this->negotiateFromHeader($headers),
            'path' => $this->negotiateFromPath($path),
            'query' => $this->negotiateFromQuery($query),
            default => $this->versionManager->default()
        };
    }

    /**
     * Negotiate version from header
     */
    protected function negotiateFromHeader(array $headers): ?ApiVersion
    {
        $headerName = strtolower($this->config['header_name']);

        foreach ($headers as $name => $value) {
            if (strtolower($name) === $headerName) {
                if ($this->versionManager->exists($value)) {
                    return $this->versionManager->get($value);
                }
            }
        }

        return $this->versionManager->default();
    }

    /**
     * Negotiate version from path
     */
    protected function negotiateFromPath(string $path): ?ApiVersion
    {
        $pattern = $this->config['path_pattern'];
        $regex = str_replace('{version}', '([0-9.]+)', $pattern);

        if (preg_match("#$regex#", $path, $matches)) {
            $version = $matches[1];
            if ($this->versionManager->exists($version)) {
                return $this->versionManager->get($version);
            }
        }

        return $this->versionManager->default();
    }

    /**
     * Negotiate version from query parameter
     */
    protected function negotiateFromQuery(array $query): ?ApiVersion
    {
        if (isset($query['version'])) {
            $version = $query['version'];
            if ($this->versionManager->exists($version)) {
                return $this->versionManager->get($version);
            }
        }

        return $this->versionManager->default();
    }
}

/**
 * ApiDocumentation - Simple API documentation registry
 */
class ApiDocumentation
{
    protected Container $container;
    protected array $endpoints = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register an endpoint
     */
    public function endpoint(string $method, string $path, array $info): void
    {
        $this->endpoints[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'info' => $info,
        ];
    }

    /**
     * Get all endpoints
     */
    public function getEndpoints(): array
    {
        return $this->endpoints;
    }

    /**
     * Generate OpenAPI specification
     */
    public function generateOpenApi(): array
    {
        // Basic OpenAPI structure
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Refynd API',
                'version' => '1.0.0',
                'description' => 'API documentation for Refynd framework',
            ],
            'paths' => $this->generatePaths(),
        ];
    }

    /**
     * Generate paths for OpenAPI
     */
    protected function generatePaths(): array
    {
        $paths = [];

        foreach ($this->endpoints as $endpoint) {
            $path = $endpoint['path'];
            $method = strtolower($endpoint['method']);

            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $paths[$path][$method] = $endpoint['info'];
        }

        return $paths;
    }
}
