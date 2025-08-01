<?php

namespace Refynd\Support;

/**
 * EnvironmentLoader - Load environment variables from .env files
 */
class EnvironmentLoader
{
    /**
     * Load environment variables from .env file
     */
    public static function load(string $path): void
    {
        $envFile = $path . DIRECTORY_SEPARATOR . '.env';
        
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) {
                continue; // Skip comments
            }
            
            if (strpos($line, '=') === false) {
                continue; // Skip invalid lines
            }
            
            [$key, $value] = explode('=', $line, 2);
            
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            
            // Only set if not already set
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}
