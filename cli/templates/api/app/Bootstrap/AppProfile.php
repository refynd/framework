<?php

namespace {{APP_NAMESPACE}}\Bootstrap;

use Refynd\Config\AppProfile as BaseAppProfile;

/**
 * Application Profile for {{APP_NAME}}
 */
class AppProfile extends BaseAppProfile
{
    public function __construct()
    {
        parent::__construct(__DIR__ . '/../../', 'production');
        
        $this->configure();
    }

    /**
     * Configure the application
     */
    protected function configure(): void
    {
        // Set application modules
        $this->set('modules', [
            \Refynd\Modules\CacheModule::class,
            \Refynd\Modules\DatabaseModule::class,
            \Refynd\Modules\EventModule::class,
            \Refynd\Modules\RoutingModule::class,
            \Refynd\Modules\ValidationModule::class,
        ]);

        // Configure database
        $this->set('database.default', 'sqlite');
        $this->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => $this->path('storage/database.sqlite'),
        ]);

        // Configure cache
        $this->set('cache.default', 'file');
        $this->set('cache.stores.file', [
            'driver' => 'file',
            'path' => $this->path('storage/cache'),
        ]);
        
        // Load routes
        $this->loadRoutes();
    }

    /**
     * Load application routes
     */
    protected function loadRoutes(): void
    {
        $routeFile = $this->path('routes/api.php');
        if (file_exists($routeFile)) {
            require $routeFile;
        }
    }
}
