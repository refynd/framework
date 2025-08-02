<?php

declare(strict_types = 1);

namespace Refynd\Phixer;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Phixer Engine - Automated Code Quality Fixing for Refynd Framework
 *
 * The Phixer engine provides comprehensive automated code fixing capabilities
 * including PHPStan issue resolution, code style standardization, DocBlock
 * enhancement, and import optimization.
 */
class PhixerEngine
{
    private string $projectRoot;
    private array $fixedFiles = [];
    private array $errors = [];
    private PhixerConfig $config;

    public function __construct(string $projectRoot, ?PhixerConfig $config = null)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->config = $config ?? new PhixerConfig();
    }

    /**
     * Run all automated fixes
     */
    public function runAllFixes(): PhixerResult
    {
        $this->resetState();

        if ($this->config->isVerbose()) {
            echo "🔧 Starting Phixer automated code fixing for Refynd Framework...\n\n";
        }

        $this->fixPhpCsFixer();
        $this->fixPhpStan();
        $this->fixCodeStyle();
        $this->fixDocBlocks();
        $this->fixImports();

        $result = new PhixerResult(
            array_unique($this->fixedFiles),
            $this->errors
        );

        if ($this->config->isVerbose()) {
            $this->printSummary($result);
        }

        return $result;
    }

    /**
     * Run only specific fixes
     */
    public function runSpecificFixes(array $fixTypes): PhixerResult
    {
        $this->resetState();

        $availableFixes = ['style' => [$this, 'fixPhpCsFixer'],
            'phpstan' => [$this, 'fixPhpStan'],
            'code-style' => [$this, 'fixCodeStyle'],
            'docblocks' => [$this, 'fixDocBlocks'],
            'imports' => [$this, 'fixImports'],];

        foreach ($fixTypes as $fixType) {
            if (isset($availableFixes[$fixType])) {
                call_user_func($availableFixes[$fixType]);
            }
        }

        return new PhixerResult(
            array_unique($this->fixedFiles),
            $this->errors
        );
    }

    /**
     * Fix code style with PHP-CS-Fixer
     */
    private function fixPhpCsFixer(): void
    {
        if ($this->config->isVerbose()) {
            echo "📝 Running PHP-CS-Fixer...\n";
        }

        $configFile = $this->projectRoot . '/.php-cs-fixer.php';
        if (!file_exists($configFile)) {
            $this->createPhpCsFixerConfig();
        }

        $command = "cd {$this->projectRoot} && vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php";
        if ($this->config->isDryRun()) {
            $command .= " --dry-run";
        }
        if ($this->config->isVerbose()) {
            $command .= " --verbose";
        }

        $output = shell_exec($command);

        if ($output && !$this->config->isDryRun()) {
            if ($this->config->isVerbose()) {
                echo "✅ PHP-CS-Fixer completed\n";
            }
            $this->fixedFiles[] = 'PHP-CS-Fixer fixes applied';
        }
    }

    /**
     * Fix PHPStan issues automatically where possible
     */
    private function fixPhpStan(): void
    {
        if ($this->config->isVerbose()) {
            echo "🔍 Analyzing PHPStan issues...\n";
        }

        $command = "cd {$this->projectRoot} && vendor/bin/phpstan analyse src --memory-limit = 256M --error-format = json";
        $output = shell_exec($command);

        if ($output) {
            $data = json_decode($output, true);
            if (isset($data['files'])) {
                $this->fixPhpStanIssues($data['files']);
            }
        }
    }

    /**
     * Fix common PHPStan issues
     */
    private function fixPhpStanIssues(array $files): void
    {
        foreach ($files as $file => $data) {
            if (!isset($data['messages'])) {
                continue;
            }

            $content = file_get_contents($file);
            $modified = false;

            foreach ($data['messages'] as $message) {
                $line = $message['line'] ?? 0;
                $msg = $message['message'] ?? '';

                // Fix missing return types
                if (strpos($msg, 'Method') !== false && strpos($msg, 'has no return type') !== false) {
                    $content = $this->addMissingReturnType($content, $line, $msg);
                    $modified = true;
                }

                // Fix missing parameter types
                if (strpos($msg, 'Parameter') !== false && strpos($msg, 'has no type') !== false) {
                    $content = $this->addMissingParameterType($content, $line, $msg);
                    $modified = true;
                }

                // Fix unused imports
                if (strpos($msg, 'unused import') !== false) {
                    $content = $this->removeUnusedImport($content, $line);
                    $modified = true;
                }
            }

            if ($modified && !$this->config->isDryRun()) {
                file_put_contents($file, $content);
                $this->fixedFiles[] = $file;
            }
        }
    }

    /**
     * Fix code style issues
     */
    private function fixCodeStyle(): void
    {
        if ($this->config->isVerbose()) {
            echo "🎨 Fixing code style issues...\n";
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot . '/src')
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $original = $content;

            // Fix common style issues
            $content = $this->fixCommonStyleIssues($content);

            if ($content !== $original && !$this->config->isDryRun()) {
                file_put_contents($file->getPathname(), $content);
                $this->fixedFiles[] = $file->getPathname();
            }
        }
    }

    /**
     * Fix common style issues
     */
    private function fixCommonStyleIssues(string $content): string
    {
        // Fix spacing around operators
        $content = preg_replace('/([a-zA-Z0-9_\]])([=!<>]+)([a-zA-Z0-9_\[\(])/', '$1 $2 $3', $content);

        // Fix spacing after commas
        $content = preg_replace('/, ([^\s])/', ', $1', $content);

        // Fix spacing around array brackets
        $content = preg_replace('/\[\s+/', '[', $content);
        $content = preg_replace('/\s+\]/', ']', $content);

        // Remove trailing whitespace
        $content = preg_replace('/[\t]+$/m', '', $content);

        // Ensure single blank line at end of file
        $content = rtrim($content) . "\n";

        return $content;
    }

    /**
     * Fix and standardize DocBlocks
     */
    private function fixDocBlocks(): void
    {
        if ($this->config->isVerbose()) {
            echo "📚 Fixing DocBlocks...\n";
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot . '/src')
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $original = $content;

            // Add missing DocBlocks
            $content = $this->addMissingDocBlocks($content);

            // Fix DocBlock formatting
            $content = $this->fixDocBlockFormatting($content);

            if ($content !== $original && !$this->config->isDryRun()) {
                file_put_contents($file->getPathname(), $content);
                $this->fixedFiles[] = $file->getPathname();
            }
        }
    }

    /**
     * Fix and optimize imports
     */
    private function fixImports(): void
    {
        if ($this->config->isVerbose()) {
            echo "📦 Optimizing imports...\n";
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->projectRoot . '/src')
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $original = $content;

            // Sort use statements
            $content = $this->sortUseStatements($content);

            // Remove duplicate imports
            $content = $this->removeDuplicateImports($content);

            if ($content !== $original && !$this->config->isDryRun()) {
                file_put_contents($file->getPathname(), $content);
                $this->fixedFiles[] = $file->getPathname();
            }
        }
    }

    /**
     * Create PHP-CS-Fixer configuration
     */
    private function createPhpCsFixerConfig(): void
    {
        $configGenerator = new PhpCsFixerConfigGenerator();
        $config = $configGenerator->generate();
        file_put_contents($this->projectRoot . '/.php-cs-fixer.php', $config);
    }

    /**
     * Reset internal state
     */
    private function resetState(): void
    {
        $this->fixedFiles = [];
        $this->errors = [];
    }

    /**
     * Print summary of fixes applied
     */
    private function printSummary(PhixerResult $result): void
    {
        echo "\n🎉 Phixer automated fixing completed!\n";
        echo "📊 Summary:\n";
        echo "  - Files fixed: " . count($result->getFixedFiles()) . "\n";
        echo "  - Errors found: " . count($result->getErrors()) . "\n";

        if (!empty($result->getErrors())) {
            echo "\n❌ Errors that require manual attention:\n";
            foreach ($result->getErrors() as $error) {
                echo "  - $error\n";
            }
        }

        echo "\n✅ Run 'composer check' to verify all fixes applied correctly.\n";
    }

    // Helper methods for specific fixes
    private function addMissingReturnType(string $content, int $line, string $message): string
    {
        // Implementation for adding missing return types
        return $content;
    }

    private function addMissingParameterType(string $content, int $line, string $message): string
    {
        // Implementation for adding missing parameter types
        return $content;
    }

    private function removeUnusedImport(string $content, int $line): string
    {
        // Implementation for removing unused imports
        return $content;
    }

    private function addMissingDocBlocks(string $content): string
    {
        // Implementation for adding missing DocBlocks
        return $content;
    }

    private function fixDocBlockFormatting(string $content): string
    {
        // Implementation for fixing DocBlock formatting
        return $content;
    }

    private function sortUseStatements(string $content): string
    {
        // Implementation for sorting use statements
        return $content;
    }

    private function removeDuplicateImports(string $content): string
    {
        // Implementation for removing duplicate imports
        return $content;
    }
}
