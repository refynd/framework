<?php

namespace Refynd\Cli\Generators;

/**
 * Model generator
 */
class ModelGenerator extends BaseGenerator
{
    public function generate(string $name): string
    {
        $className = $this->toPascalCase($name);
        $tableName = $this->toSnakeCase($className);

        $namespace = $this->getAppNamespace() . '\\Models';
        $directory = getcwd() . '/app/Models';
        $filePath = $directory . '/' . $className . '.php';

        $this->ensureDirectoryExists($directory);

        if (file_exists($filePath)) {
            throw new \RuntimeException("Model {$className} already exists.");
        }

        $content = $this->twig->render('model.php.twig', [
            'namespace' => $namespace,
            'className' => $className,
            'tableName' => $tableName,
        ]);

        file_put_contents($filePath, $content);

        return $filePath;
    }
}
