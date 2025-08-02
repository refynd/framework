<?php

namespace Refynd\Storage;

class S3Storage implements StorageInterface
{
    private string $bucket;
    private string $region;
    private string $accessKey;
    private string $secretKey;
    private string $endpoint;

    public function __construct(array $config)
    {
        $this->bucket = $config['bucket'];
        $this->region = $config['region'] ?? 'us-east-1';
        $this->accessKey = $config['key'];
        $this->secretKey = $config['secret'];
        $this->endpoint = $config['endpoint'] ?? "https://s3.{$this->region}.amazonaws.com";
    }

    public function put(string $path, string $contents): bool
    {
        $url = $this->endpoint . '/' . $this->bucket . '/' . ltrim($path, '/');
        $headers = $this->getAuthHeaders('PUT', $path, $contents);

        $context = stream_context_create(['http' => ['method' => 'PUT',
                'header' => implode("\r\n", $headers),
                'content' => $contents]]);

        $result = file_get_contents($url, false, $context);
        return $result !== false;
    }

    public function get(string $path): string
    {
        $url = $this->endpoint . '/' . $this->bucket . '/' . ltrim($path, '/');
        $headers = $this->getAuthHeaders('GET', $path);

        $context = stream_context_create(['http' => ['method' => 'GET',
                'header' => implode("\r\n", $headers)]]);

        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            throw new \Exception("Failed to get file: {$path}");
        }
        return $result;
    }

    public function exists(string $path): bool
    {
        try {
            $url = $this->endpoint . '/' . $this->bucket . '/' . ltrim($path, '/');
            $headers = $this->getAuthHeaders('HEAD', $path);

            $context = stream_context_create(['http' => ['method' => 'HEAD',
                    'header' => implode("\r\n", $headers)]]);

            $result = @file_get_contents($url, false, $context);
            return $result !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        $url = $this->endpoint . '/' . $this->bucket . '/' . ltrim($path, '/');
        $headers = $this->getAuthHeaders('DELETE', $path);

        $context = stream_context_create(['http' => ['method' => 'DELETE',
                'header' => implode("\r\n", $headers)]]);

        $result = file_get_contents($url, false, $context);
        return $result !== false;
    }

    public function copy(string $from, string $to): bool
    {
        $content = $this->get($from);
        return $this->put($to, $content);
    }

    public function move(string $from, string $to): bool
    {
        $copied = $this->copy($from, $to);
        if ($copied) {
            $this->delete($from);
        }
        return $copied;
    }

    public function size(string $path): int
    {
        // Implementation would require S3 HEAD request
        return 0;
    }

    public function lastModified(string $path): int
    {
        // Implementation would require S3 HEAD request
        return time();
    }

    public function url(string $path): string
    {
        return $this->endpoint . '/' . $this->bucket . '/' . ltrim($path, '/');
    }

    public function files(string $directory = ''): array
    {
        // Implementation would require S3 LIST request
        return [];
    }

    public function directories(string $directory = ''): array
    {
        // Implementation would require S3 LIST request
        return [];
    }

    public function makeDirectory(string $path): bool
    {
        // S3 doesn't have directories, but we can create a placeholder
        return $this->put($path . '/.keep', '');
    }

    public function deleteDirectory(string $path): bool
    {
        // Implementation would require listing and deleting all objects
        return true;
    }

    private function getAuthHeaders(string $method, string $path, string $content = ''): array
    {
        $timestamp = gmdate('D, d M Y H:i:s T');
        $contentMd5 = base64_encode(md5($content, true));
        $contentType = 'application/octet-stream';

        $stringToSign = "{$method}\n{$contentMd5}\n{$contentType}\n{$timestamp}\n/{$this->bucket}/{$path}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->secretKey, true));

        return ["Date: {$timestamp}",
            "Content-Type: {$contentType}",
            "Content-MD5: {$contentMd5}",
            "Authorization: AWS {$this->accessKey}:{$signature}"];
    }
}
