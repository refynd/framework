<?php

declare(strict_types = 1);

namespace Refynd\Phixer;

/**
 * Phixer Exception
 *
 * Custom exception for Phixer-related errors.
 */
class PhixerException extends \Exception
{
    public static function configurationError(string $message): self
    {
        return new self("Configuration error: $message");
    }

    public static function fileNotFound(string $path): self
    {
        return new self("File not found: $path");
    }

    public static function fixingError(string $message, string $file = ''): self
    {
        $location = $file ? " in $file" : '';
        return new self("Fixing error$location: $message");
    }

    public static function commandFailed(string $command, string $output = ''): self
    {
        $message = "Command failed: $command";
        if ($output) {
            $message .= "\nOutput: $output";
        }
        return new self($message);
    }
}
