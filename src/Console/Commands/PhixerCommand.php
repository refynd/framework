<?php

declare(strict_types = 1);

namespace Refynd\Console\Commands;

use Refynd\Phixer\Phixer;
use Refynd\Phixer\PhixerConfig;

/**
 * Phixer Console Command
 *
 * Provides command-line interface for Phixer functionality.
 */
class PhixerCommand
{
    private string $projectRoot; // @phpstan-ignore-line Property reserved for future command enhancement

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
        Phixer::init($projectRoot);
    }

    /**
     * Handle the phixer command
     */
    public function handle(array $args = []): int
    {
        $action = $args[0] ?? 'all';
        $flags = array_slice($args, 1);

        $config = $this->buildConfig($flags);

        try {
            $result = match ($action) {
                'all' => Phixer::fixAll($config),
                'style' => Phixer::fixStyle(),
                'phpstan' => Phixer::fixPhpStan(),
                'docblocks' => Phixer::fixDocBlocks(),
                'imports' => Phixer::fixImports(),
                'dry-run' => Phixer::dryRun(),
                'check' => Phixer::dryRun(),
                default => $this->showHelp()
            };

            if ($result instanceof \Refynd\Phixer\PhixerResult) {
                $this->displayResult($result);
                return $result->isSuccessful() ? 0 : 1;
            }

            return 0;
        } catch (\Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * Build configuration from command flags
     */
    private function buildConfig(array $flags): PhixerConfig
    {
        $config = new PhixerConfig();

        foreach ($flags as $flag) {
            match ($flag) {
                '--dry-run', '-d' => $config->setDryRun(true),
                '--silent', '-s' => $config->setVerbose(false),
                '--verbose', '-v' => $config->setVerbose(true),
                default => null
            };
        }

        return $config;
    }

    /**
     * Display the result of the Phixer operation
     */
    private function displayResult(\Refynd\Phixer\PhixerResult $result): void
    {
        if (!$result->hasFixedFiles() && !$result->hasErrors()) {
            echo "✅ No issues found - code is already clean!\n";
            return;
        }

        if ($result->hasFixedFiles()) {
            echo "🔧 Fixed " . $result->getFixedFileCount() . " files\n";

            foreach ($result->getFixedFiles() as $file) {
                echo "  - $file\n";
            }
        }

        if ($result->hasErrors()) {
            echo "\n❌ " . $result->getErrorCount() . " errors need manual attention:\n";

            foreach ($result->getErrors() as $error) {
                echo "  - $error\n";
            }
        }

        if ($result->getExecutionTime() > 0) {
            echo "\n⏱️  Execution time: " . number_format($result->getExecutionTime(), 2) . "s\n";
        }
    }

    /**
     * Show help information
     */
    private function showHelp(): void
    {
        echo "Phixer - Automated Code Quality Fixing for Refynd Framework\n\n";
        echo "Usage:\n";
        echo "  php refynd phixer [command] [options]\n\n";
        echo "Commands:\n";
        echo "  all        Run all fixes (default)\n";
        echo "  style      Fix code style issues only\n";
        echo "  phpstan    Fix PHPStan issues only\n";
        echo "  docblocks  Fix DocBlock issues only\n";
        echo "  imports    Fix import issues only\n";
        echo "  dry-run    Check what would be fixed without making changes\n";
        echo "  check      Alias for dry-run\n\n";
        echo "Options:\n";
        echo "  --dry-run, -d    Run in dry-run mode\n";
        echo "  --silent, -s     Run silently\n";
        echo "  --verbose, -v    Run with verbose output\n\n";
        echo "Examples:\n";
        echo "  php refynd phixer\n";
        echo "  php refynd phixer style --dry-run\n";
        echo "  php refynd phixer all --verbose\n";
        echo "  php refynd phixer check\n\n";

        return;
    }
}
