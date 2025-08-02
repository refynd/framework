<?php

declare(strict_types = 1);

namespace Refynd\Phixer;

/**
 * Phixer Configuration
 *
 * Manages configuration settings for the Phixer engine.
 */
class PhixerConfig
{
    private bool $verbose;
    private bool $dryRun;
    private array $enabledFixes;
    private array $excludedPaths;

    public function __construct(
        bool $verbose = true,
        bool $dryRun = false,
        array $enabledFixes = ['all'],
        array $excludedPaths = []
    ) {
        $this->verbose = $verbose;
        $this->dryRun = $dryRun;
        $this->enabledFixes = $enabledFixes;
        $this->excludedPaths = $excludedPaths;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function setDryRun(bool $dryRun): self
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    public function getEnabledFixes(): array
    {
        return $this->enabledFixes;
    }

    public function setEnabledFixes(array $enabledFixes): self
    {
        $this->enabledFixes = $enabledFixes;
        return $this;
    }

    public function getExcludedPaths(): array
    {
        return $this->excludedPaths;
    }

    public function setExcludedPaths(array $excludedPaths): self
    {
        $this->excludedPaths = $excludedPaths;
        return $this;
    }

    public function isFixEnabled(string $fixType): bool
    {
        return in_array('all', $this->enabledFixes) || in_array($fixType, $this->enabledFixes);
    }

    public function isPathExcluded(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if (strpos($path, $excludedPath) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create a configuration for silent operation
     */
    public static function silent(): self
    {
        return new self(verbose: false);
    }

    /**
     * Create a configuration for dry-run operation
     */
    public static function dryRun(): self
    {
        return new self(dryRun: true);
    }

    /**
     * Create a configuration for specific fixes only
     */
    public static function onlyFixes(array $fixes): self
    {
        return new self(enabledFixes: $fixes);
    }
}
