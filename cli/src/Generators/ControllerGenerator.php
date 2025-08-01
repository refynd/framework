<?php

namespace Refynd\Cli\Generators;

/**
 * Controller generator
 */
class ControllerGenerator extends BaseGenerator
{
    public function generate(string $name, array $options = []): string
    {
        $className = $this->toPascalCase($name);
        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        $namespace = $this->getAppNamespace() . '\\Http\\Controllers';
        $directory = getcwd() . '/app/Http/Controllers';
        $filePath = $directory . '/' . $className . '.php';

        $this->ensureDirectoryExists($directory);

        if (file_exists($filePath)) {
            throw new \RuntimeException("Controller {$className} already exists.");
        }

        $template = $options['resource'] ? 'controller.resource.php.twig' : 'controller.php.twig';
        
        $content = $this->twig->render($template, [
            'namespace' => $namespace,
            'className' => $className,
            'resource' => $options['resource'] ?? false,
            'api' => $options['api'] ?? false,
        ]);

        file_put_contents($filePath, $content);

        return $filePath;
    }
}
