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

    public function __construct(AppProfile $profile)
    {
        $this->profile = $profile;
        $this->container = new Container();
        $this->moduleManager = new ModuleManager($this->container);
        
        static::$instance = $this;
        $this->registerCoreBindings();
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

        $this->loadModules();
        $this->bootModules();
        
        $this->booted = true;
    }

    /**
     * Load all modules defined in the application profile
     */
    protected function loadModules(): void
    {
        $modules = $this->profile->get('modules', []);
        
        foreach ($modules as $moduleClass) {
            $this->moduleManager->register($moduleClass);
        }
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
}
