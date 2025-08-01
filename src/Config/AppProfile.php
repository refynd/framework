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

    public function __construct(string $basePath, string $environment = 'production')
    {
        $this->basePath = $basePath;
        $this->environment = $environment;
        
        $this->loadEnvironment();
        $this->loadConfiguration();
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
     * Get a configuration value
     */
    public function get(string $key, $default = null)
    {
        return $this->getValue($key, $default);
    }

    /**
     * Set a configuration value
     */
    public function set(string $key, $value): void
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
    protected function getValue(string $key, $default = null)
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
    protected function setValue(string $key, $value): void
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
