<?php

namespace Refynd\Bootstrap;

use Refynd\Config\AppProfile;
use Refynd\Container\Container;
use Refynd\Http\HttpKernel;
use Refynd\Console\ConsoleKernel;
use Refynd\Modules\ModuleManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Engine - The Heart of Refynd
 * 
 * The Engine orchestrates the entire framework lifecycle:
 * - Loads configuration profiles
 * - Registers and boots modules
 * - Builds the dependency injection container
 * - Delegates to appropriate kernels (HTTP/Console)
 */
class Engine
{
    protected Container $container;
    protected AppProfile $profile;
    protected ModuleManager $moduleManager;
    protected bool $booted = false;
    protected static ?Engine $instance = null;
    
    // Performance optimizations
    protected array $lazyServices = [];
    protected bool $debugMode = false;
    protected float $bootStartTime;
    protected array $bootMetrics = [];

    public function __construct(AppProfile $profile)
    {
        $this->bootStartTime = microtime(true);
        $this->profile = $profile;
        $this->debugMode = $profile->get('debug', false);
        $this->container = new Container();
        $this->moduleManager = new ModuleManager($this->container);
        
        static::$instance = $this;
        $this->registerCoreBindings();
        
        if ($this->debugMode) {
            $this->bootMetrics['construct_time'] = microtime(true) - $this->bootStartTime;
        }
    }

    /**
     * Run the framework in HTTP mode for web requests
     */
    public function runHttp(): Response
    {
        $this->boot();
        
        $request = Request::createFromGlobals();
        $kernel = $this->container->make(HttpKernel::class);
        
        return $kernel->handle($request);
    }

    /**
     * Run the framework in Console mode for CLI commands
     */
    public function runConsole(): int
    {
        $this->boot();
        
        $kernel = $this->container->make(ConsoleKernel::class);
        
        return $kernel->handle();
    }

    /**
     * Boot the framework by loading modules and finalizing container
     */
    protected function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $bootStart = microtime(true);
        
        $this->loadModules();
        $this->bootModules();
        
        $this->booted = true;
        
        if ($this->debugMode) {
            $this->bootMetrics['boot_time'] = microtime(true) - $bootStart;
            $this->bootMetrics['total_time'] = microtime(true) - $this->bootStartTime;
            $this->bootMetrics['memory_usage'] = memory_get_usage();
            $this->bootMetrics['peak_memory'] = memory_get_peak_usage();
        }
    }

    /**
     * Load all modules defined in the application profile
     */
    protected function loadModules(): void
    {
        // Load modules from configuration
        $modules = $this->profile->get('modules', []);
        
        foreach ($modules as $moduleClass) {
            $this->moduleManager->register($moduleClass);
        }
        
        // Allow profile to register additional modules
        $this->profile->registerModules($this->container);
    }

    /**
     * Boot all registered modules
     */
    protected function bootModules(): void
    {
        $this->moduleManager->bootAll();
    }

    /**
     * Register core framework bindings in the container
     */
    protected function registerCoreBindings(): void
    {
        $this->container->singleton(Container::class, fn() => $this->container);
        $this->container->singleton(AppProfile::class, fn() => $this->profile);
        $this->container->singleton(Engine::class, fn() => $this);
        $this->container->singleton(ModuleManager::class, fn() => $this->moduleManager);
        
        // Register lazy services for better performance
        $this->registerLazyServices();
    }
    
    /**
     * Register lazy-loaded services
     */
    protected function registerLazyServices(): void
    {
        // Cache service with high-performance wrapper
        $this->container->singleton('cache.high_performance', function ($container) {
            $manager = $container->make(\Refynd\Cache\CacheManager::class);
            return new \Refynd\Cache\HighPerformanceCache($manager->store());
        });
        
        // Router with compilation enabled
        $this->container->singleton('router.optimized', function ($container) {
            $router = $container->make(\Refynd\Http\Router::class);
            $router->setCompilationEnabled(true);
            return $router;
        });
    }

    /**
     * Get the application container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the application profile
     */
    public function getProfile(): AppProfile
    {
        return $this->profile;
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance(): ?Engine
    {
        return static::$instance;
    }

    /**
     * Get the container from the singleton instance
     */
    public static function getContainerInstance(): ?Container
    {
        return static::$instance?->container;
    }

    /**
     * Get the container (shorthand method)
     */
    public static function container(): Container
    {
        if (!static::$instance) {
            throw new \RuntimeException('Engine not initialized. Call Engine::create() first.');
        }

        return static::$instance->container;
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $metrics = $this->bootMetrics;
        
        if ($this->container instanceof Container) {
            $metrics['container'] = $this->container->getCacheStats();
        }
        
        return $metrics;
    }
    
    /**
     * Enable or disable debug mode
     */
    public function setDebugMode(bool $debug): void
    {
        $this->debugMode = $debug;
    }
    
    /**
     * Check if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }
    
    /**
     * Clear all performance caches
     */
    public function clearPerformanceCaches(): void
    {
        if ($this->container instanceof Container) {
            $this->container->clearCaches();
        }
        
        // Clear router cache if available
        try {
            $router = $this->container->make('router.optimized');
            if (method_exists($router, 'clearCompilationCache')) {
                $router->clearCompilationCache();
            }
        } catch (\Exception $e) {
            // Router not available, ignore
        }
        
        // Clear high-performance cache if available
        try {
            $cache = $this->container->make('cache.high_performance');
            if (method_exists($cache, 'clearLocal')) {
                $cache->clearLocal();
            }
        } catch (\Exception $e) {
            // Cache not available, ignore
        }
    }
}
