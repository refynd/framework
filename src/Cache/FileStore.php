<?php

namespace Refynd\Cache;

class FileStore implements CacheInterface
{
    private string $path;

    public function __construct(array $config)
    {
        $this->path = $config['path'] ?? sys_get_temp_dir() . '/refynd_cache';
        
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        
        if (!file_exists($file)) {
            return $default;
        }

        $content = file_get_contents($file);
        $data = unserialize($content);

        if ($data['expires'] !== null && $data['expires'] < time()) {
            $this->forget($key);
            return $default;
        }

        return $data['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        $file = $this->getFilePath($key);
        $expires = $ttl > 0 ? time() + $ttl : null;
        
        $data = [
            'value' => $value,
            'expires' => $expires,
        ];

        return file_put_contents($file, serialize($data)) !== false;
    }

    public function forget(string $key): bool
    {
        $file = $this->getFilePath($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }

    public function flush(): bool
    {
        $files = glob($this->path . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        return true;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = $this->get($key, 0);
        
        if (!is_numeric($current)) {
            return false;
        }
        
        $new = $current + $value;
        $this->put($key, $new);
        
        return $new;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    public function forever(string $key, mixed $value): bool
    {
        return $this->put($key, $value, 0);
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->put($key, $value, $ttl);
        
        return $value;
    }

    public function rememberForever(string $key, callable $callback): mixed
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->forever($key, $value);
        
        return $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function many(array $keys): array
    {
        $result = [];
        
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        
        return $result;
    }

    public function putMany(array $values, int $ttl = 3600): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->put($key, $value, $ttl)) {
                return false;
            }
        }
        
        return true;
    }

    public function forgetMany(array $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->forget($key)) {
                return false;
            }
        }
        
        return true;
    }

    private function getFilePath(string $key): string
    {
        return $this->path . '/' . md5($key) . '.cache';
    }
}
