<?php

namespace Refynd\Storage;

class StorageManager
{
    private array $disks = [];
    private string $defaultDisk = 'local';

    public function __construct()
    {
        $this->disks['local'] = new LocalStorage(getcwd() . '/storage/app');
    }

    public function disk(?string $name = null): StorageInterface
    {
        $name = $name ?: $this->defaultDisk;
        
        if (!isset($this->disks[$name])) {
            throw new \Exception("Storage disk '{$name}' not found");
        }
        
        return $this->disks[$name];
    }

    public function extend(string $name, StorageInterface $storage): void
    {
        $this->disks[$name] = $storage;
    }

    public function setDefaultDisk(string $name): void
    {
        $this->defaultDisk = $name;
    }

    // Convenience methods for default disk
    public function put(string $path, string $contents): bool
    {
        return $this->disk()->put($path, $contents);
    }

    public function get(string $path): string
    {
        return $this->disk()->get($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function delete(string $path): bool
    {
        return $this->disk()->delete($path);
    }

    public function copy(string $from, string $to): bool
    {
        return $this->disk()->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->disk()->move($from, $to);
    }

    public function url(string $path): string
    {
        return $this->disk()->url($path);
    }

    public function files(string $directory = ''): array
    {
        return $this->disk()->files($directory);
    }

    public function directories(string $directory = ''): array
    {
        return $this->disk()->directories($directory);
    }
}
