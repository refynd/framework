<?php

namespace Refynd\Config;

use Refynd\Support\EnvironmentLoader;
use RuntimeException;

/**
 * AppProfile - Configuration Profile Manager
 * 
 * Manages application configuration with environment-specific profiles
 * and modular configuration loading.
 */
class AppProfile
{
    protected array $config = [];
    protected string $basePath;
    protected string $environment;

    public function __construct(?string $basePath = null, string $environment = 'production')
    {
        $this->basePath = $basePath ?? $this->detectBasePath();
        $this->environment = $environment;
        
        $this->loadEnvironment();
        $this->loadConfiguration();
    }

    /**
     * Detect the base path automatically
     */
    protected function detectBasePath(): string
    {
        // For classes that extend AppProfile, use their location as reference
        $reflection = new \ReflectionClass($this);
        $classPath = dirname($reflection->getFileName());
        
        // Go up directories until we find composer.json or vendor directory
        $current = $classPath;
        $maxLevels = 10; // Prevent infinite loops
        $level = 0;
        
        while ($level < $maxLevels) {
            if (file_exists($current . '/composer.json') || 
                file_exists($current . '/vendor') ||
                file_exists($current . '/public/index.php')) {
                return $current;
            }
            
            $parent = dirname($current);
            if ($parent === $current) {
                break; // Reached filesystem root
            }
            
            $current = $parent;
            $level++;
        }
        
        // Fallback to current working directory
        return getcwd() ?: __DIR__;
    }

    /**
     * Load an application profile from the given path
     */
    public static function load(string $basePath): self
    {
        $environment = $_ENV['REFYND_ENV'] ?? $_SERVER['REFYND_ENV'] ?? 'production';
        
        return new self($basePath, $environment);
    }

    /**
     * Get the application name (can be overridden)
     */
    public function name(): string
    {
        return $this->get('app.name', 'Refynd Application');
    }

    /**
     * Get the application version (can be overridden)
     */
    public function version(): string
    {
        return $this->get('app.version', '1.0.0');
    }

    /**
     * Get the application URL (can be overridden)
     */
    public function url(): string
    {
        return $this->get('app.url', 'http://localhost');
    }

    /**
     * Determine if debug mode is enabled (can be overridden)
     */
    public function debug(): bool
    {
        return (bool) $this->get('app.debug', false);
    }

    /**
     * Register application modules (can be overridden)
     */
    public function registerModules(\Refynd\Container\Container $container): void
    {
        // Default implementation - can be overridden by child classes
    }

    /**
     * Get a configuration value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getValue($key, $default);
    }

    /**
     * Set a configuration value
     */
    public function set(string $key, mixed $value): void
    {
        $this->setValue($key, $value);
    }

    /**
     * Check if a configuration key exists
     */
    public function has(string $key): bool
    {
        return $this->getValue($key) !== null;
    }

    /**
     * Get the base path of the application
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get the current environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Get a path relative to the base path
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Get the config directory path
     */
    public function configPath(string $path = ''): string
    {
        return $this->path('config' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    /**
     * Get the storage directory path
     */
    public function storagePath(string $path = ''): string
    {
        return $this->path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    /**
     * Get the public directory path
     */
    public function publicPath(string $path = ''): string
    {
        return $this->path('public' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''));
    }

    /**
     * Load environment variables
     */
    protected function loadEnvironment(): void
    {
        EnvironmentLoader::load($this->basePath);
    }

    /**
     * Load configuration from files
     */
    protected function loadConfiguration(): void
    {
        $configPath = $this->configPath();
        
        if (!is_dir($configPath)) {
            return;
        }

        // Load base configuration files
        $this->loadConfigFiles($configPath);
        
        // Load environment-specific configuration
        $envConfigPath = $configPath . DIRECTORY_SEPARATOR . $this->environment;
        if (is_dir($envConfigPath)) {
            $this->loadConfigFiles($envConfigPath);
        }
    }

    /**
     * Load configuration files from a directory
     */
    protected function loadConfigFiles(string $path): void
    {
        $files = glob($path . DIRECTORY_SEPARATOR . '*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $config = require $file;
            
            if (is_array($config)) {
                $this->config[$key] = array_merge(
                    $this->config[$key] ?? [],
                    $config
                );
            }
        }
    }

    /**
     * Get a nested configuration value using dot notation
     */
    protected function getValue(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a nested configuration value using dot notation
     */
    protected function setValue(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Determine if the application is in debug mode
     */
    public function isDebug(): bool
    {
        return (bool) $this->get('app.debug', false);
    }

    /**
     * Determine if the application is in a specific environment
     */
    public function isEnvironment(string $environment): bool
    {
        return $this->environment === $environment;
    }
}
