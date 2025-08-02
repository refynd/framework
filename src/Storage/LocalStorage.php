<?php

namespace Refynd\Storage;

class LocalStorage implements StorageInterface
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
        if (!is_dir($this->root)) {
            mkdir($this->root, 0755, true);
        }
    }

    public function put(string $path, string $contents): bool
    {
        $fullPath = $this->fullPath($path);
        $this->ensureDirectoryExists(dirname($fullPath));
        return file_put_contents($fullPath, $contents) !== false;
    }

    public function get(string $path): string
    {
        $fullPath = $this->fullPath($path);
        if (!file_exists($fullPath)) {
            throw new \Exception("File not found: {$path}");
        }
        return file_get_contents($fullPath);
    }

    public function exists(string $path): bool
    {
        return file_exists($this->fullPath($path));
    }

    public function delete(string $path): bool
    {
        $fullPath = $this->fullPath($path);
        return file_exists($fullPath) && unlink($fullPath);
    }

    public function copy(string $from, string $to): bool
    {
        $fromPath = $this->fullPath($from);
        $toPath = $this->fullPath($to);
        $this->ensureDirectoryExists(dirname($toPath));
        return copy($fromPath, $toPath);
    }

    public function move(string $from, string $to): bool
    {
        $fromPath = $this->fullPath($from);
        $toPath = $this->fullPath($to);
        $this->ensureDirectoryExists(dirname($toPath));
        return rename($fromPath, $toPath);
    }

    public function size(string $path): int
    {
        return filesize($this->fullPath($path));
    }

    public function lastModified(string $path): int
    {
        return filemtime($this->fullPath($path));
    }

    public function url(string $path): string
    {
        return '/storage/' . ltrim($path, '/');
    }

    public function files(string $directory = ''): array
    {
        $path = $this->fullPath($directory);
        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        foreach (scandir($path) as $file) {
            if ($file !== '.' && $file !== '..' && is_file($path . '/' . $file)) {
                $files[] = $directory ? $directory . '/' . $file : $file;
            }
        }
        return $files;
    }

    public function directories(string $directory = ''): array
    {
        $path = $this->fullPath($directory);
        if (!is_dir($path)) {
            return [];
        }

        $dirs = [];
        foreach (scandir($path) as $dir) {
            if ($dir !== '.' && $dir !== '..' && is_dir($path . '/' . $dir)) {
                $dirs[] = $directory ? $directory . '/' . $dir : $dir;
            }
        }
        return $dirs;
    }

    public function makeDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);
        return mkdir($fullPath, 0755, true);
    }

    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);
        if (!is_dir($fullPath)) {
            return false;
        }

        $files = array_diff(scandir($fullPath), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $fullPath . '/' . $file;
            is_dir($filePath) ? $this->deleteDirectory($path . '/' . $file) : unlink($filePath);
        }
        return rmdir($fullPath);
    }

    private function fullPath(string $path): string
    {
        return $this->root . '/' . ltrim($path, '/');
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
