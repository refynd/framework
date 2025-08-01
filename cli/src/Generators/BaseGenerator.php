<?php

namespace Refynd\Cli\Generators;

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Base generator class for code generation
 */
abstract class BaseGenerator
{
    protected Filesystem $filesystem;
    protected Environment $twig;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
        
        $loader = new FilesystemLoader(__DIR__ . '/../../templates/stubs');
        $this->twig = new Environment($loader);
    }

    /**
     * Ensure the target directory exists
     */
    protected function ensureDirectoryExists(string $directory): void
    {
        if (!$this->filesystem->exists($directory)) {
            $this->filesystem->mkdir($directory, 0755);
        }
    }

    /**
     * Convert string to PascalCase
     */
    protected function toPascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }

    /**
     * Convert string to snake_case
     */
    protected function toSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($string)));
    }

    /**
     * Get the application namespace
     */
    protected function getAppNamespace(): string
    {
        $composerFile = getcwd() . '/composer.json';
        
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            $autoload = $composer['autoload']['psr-4'] ?? [];
            
            foreach ($autoload as $namespace => $path) {
                if ($path === 'app/' || $path === 'src/') {
                    return rtrim($namespace, '\\');
                }
            }
        }
        
        return 'App';
    }
}
