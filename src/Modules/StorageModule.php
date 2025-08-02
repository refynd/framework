<?php

namespace Refynd\Modules;

use Refynd\Container\Container;
use Refynd\Storage\StorageManager;
use Refynd\Storage\LocalStorage;
use Refynd\Storage\S3Storage;

class StorageModule extends Module
{
    private Container $container;

    public function register(Container $container): void
    {
        $this->container = $container;
        
        $storageManager = new StorageManager();
        $container->singleton('storage', fn() => $storageManager);
    }

    public function boot(): void
    {
        $storageManager = $this->container->make('storage');
        
        // Register local storage
        $storageManager->extend('local', new LocalStorage(
            getcwd() . '/storage/app'
        ));
        
        // Register S3 storage if configured
        if (isset($_ENV['AWS_ACCESS_KEY_ID'])) {
            $storageManager->extend('s3', new S3Storage([
                'bucket' => $_ENV['AWS_BUCKET'] ?? 'refynd-storage',
                'region' => $_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1',
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
                'endpoint' => $_ENV['AWS_ENDPOINT'] ?? null,
            ]));
        }
        
        // Set default disk
        $defaultDisk = $_ENV['STORAGE_DISK'] ?? 'local';
        $storageManager->setDefaultDisk($defaultDisk);
    }

    public function getVersion(): string
    {
        return '2.0.0';
    }
}
