<?php

namespace Refynd\Cli\Generators;

/**
 * Middleware generator
 */
class MiddlewareGenerator extends BaseGenerator
{
    public function generate(string $name): string
    {
        $className = $this->toPascalCase($name);
        if (!str_ends_with($className, 'Middleware')) {
            $className .= 'Middleware';
        }

        $namespace = $this->getAppNamespace() . '\\Http\\Middleware';
        $directory = getcwd() . '/app/Http/Middleware';
        $filePath = $directory . '/' . $className . '.php';

        $this->ensureDirectoryExists($directory);

        if (file_exists($filePath)) {
            throw new \RuntimeException("Middleware {$className} already exists.");
        }

        $content = $this->twig->render('middleware.php.twig', [
            'namespace' => $namespace,
            'className' => $className,
        ]);

        file_put_contents($filePath, $content);

        return $filePath;
    }
}
