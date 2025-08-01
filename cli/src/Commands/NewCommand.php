<?php

namespace Refynd\Cli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Create a new Refynd application
 */
class NewCommand extends Command
{
    protected static $defaultName = 'new';
    protected static $defaultDescription = 'Create a new Refynd application';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the application')
            ->addOption('template', 't', InputOption::VALUE_OPTIONAL, 'The template to use', 'api')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite existing directory')
            ->setDescription('Create a new Refynd application')
            ->setHelp('This command creates a new Refynd application with the specified name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $template = $input->getOption('template');
        $force = $input->getOption('force');

        $projectPath = getcwd() . '/' . $name;

        // Check if directory exists
        if (is_dir($projectPath) && !$force) {
            $io->error("Directory '{$name}' already exists. Use --force to overwrite.");
            return Command::FAILURE;
        }

        $io->title("🚀 Creating new Refynd application: {$name}");

        try {
            $this->createProject($name, $template, $projectPath, $io);
            $this->installDependencies($projectPath, $io);
            $this->setupEnvironment($projectPath, $io);

            $io->success("✨ Refynd application '{$name}' created successfully!");
            $io->note([
                "Next steps:",
                "  cd {$name}",
                "  refynd serve",
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Failed to create application: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function createProject(string $name, string $template, string $projectPath, SymfonyStyle $io): void
    {
        $io->section("📁 Creating project structure...");

        $filesystem = new Filesystem();
        
        // Remove existing directory if force is used
        if (is_dir($projectPath)) {
            $filesystem->remove($projectPath);
        }

        $filesystem->mkdir($projectPath);

        // Copy template files
        $templatePath = __DIR__ . "/../../templates/{$template}";
        
        if (!is_dir($templatePath)) {
            throw new \InvalidArgumentException("Template '{$template}' not found.");
        }

        $this->copyDirectory($templatePath, $projectPath);

        // Replace placeholders in files
        $this->replacePlaceholders($projectPath, $name);

        $io->text("✅ Project structure created");
    }

    private function installDependencies(string $projectPath, SymfonyStyle $io): void
    {
        $io->section("📦 Installing dependencies...");

        $process = new Process(['composer', 'install'], $projectPath);
        $process->setTimeout(300); // 5 minutes timeout

        $process->run(function ($type, $buffer) use ($io) {
            if (Process::ERR === $type) {
                $io->text("Error: " . $buffer);
            } else {
                $io->text($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Failed to install dependencies via Composer.');
        }

        $io->text("✅ Dependencies installed");
    }

    private function setupEnvironment(string $projectPath, SymfonyStyle $io): void
    {
        $io->section("⚙️ Setting up environment...");

        $envFile = $projectPath . '/.env';
        $envExampleFile = $projectPath . '/.env.example';

        if (file_exists($envExampleFile)) {
            copy($envExampleFile, $envFile);
            $io->text("✅ Environment file created");
        }

        // Set proper permissions
        chmod($projectPath . '/storage', 0755);
        if (is_dir($projectPath . '/storage/logs')) {
            chmod($projectPath . '/storage/logs', 0755);
        }
        if (is_dir($projectPath . '/storage/cache')) {
            chmod($projectPath . '/storage/cache', 0755);
        }

        $io->text("✅ Permissions set");
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $filesystem = new Filesystem();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                $filesystem->mkdir($target);
            } else {
                $filesystem->copy($item->getRealPath(), $target);
            }
        }
    }

    private function replacePlaceholders(string $projectPath, string $name): void
    {
        $replacements = [
            '{{APP_NAME}}' => $name,
            '{{APP_NAMESPACE}}' => $this->toPascalCase($name),
        ];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'json', 'env', 'md'])) {
                $content = file_get_contents($file->getRealPath());
                $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                file_put_contents($file->getRealPath(), $content);
            }
        }
    }

    private function toPascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }
}
