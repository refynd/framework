<?php

namespace Refynd\Storage;

interface StorageInterface
{
    public function put(string $path, string $contents): bool;
    public function get(string $path): string;
    public function exists(string $path): bool;
    public function delete(string $path): bool;
    public function copy(string $from, string $to): bool;
    public function move(string $from, string $to): bool;
    public function size(string $path): int;
    public function lastModified(string $path): int;
    public function url(string $path): string;
    public function files(string $directory = ''): array;
    public function directories(string $directory = ''): array;
    public function makeDirectory(string $path): bool;
    public function deleteDirectory(string $path): bool;
}
