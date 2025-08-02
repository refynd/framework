<?php

declare(strict_types = 1);

namespace Refynd\Phixer;

/**
 * Phixer Facade
 *
 * Provides a simple interface to the Phixer functionality.
 */
class Phixer
{
    private static ?PhixerEngine $engine = null;
    private static string $projectRoot = '';

    /**
     * Initialize Phixer with project root
     */
    public static function init(string $projectRoot): void
    {
        self::$projectRoot = $projectRoot;
        self::$engine = null; // Reset engine to pick up new root
    }

    /**
     * Get or create the Phixer engine
     */
    private static function getEngine(?PhixerConfig $config = null): PhixerEngine
    {
        if (self::$engine === null || $config !== null) {
            $root = self::$projectRoot ?: dirname(dirname(__DIR__));
            self::$engine = new PhixerEngine($root, $config);
        }

        return self::$engine;
    }

    /**
     * Run all fixes
     */
    public static function fixAll(?PhixerConfig $config = null): PhixerResult
    {
        return self::getEngine($config)->runAllFixes();
    }

    /**
     * Run specific fixes
     */
    public static function fix(array $fixTypes, ?PhixerConfig $config = null): PhixerResult
    {
        return self::getEngine($config)->runSpecificFixes($fixTypes);
    }

    /**
     * Run fixes in dry-run mode
     */
    public static function dryRun(): PhixerResult
    {
        return self::getEngine(PhixerConfig::dryRun())->runAllFixes();
    }

    /**
     * Run fixes silently
     */
    public static function silent(): PhixerResult
    {
        return self::getEngine(PhixerConfig::silent())->runAllFixes();
    }

    /**
     * Fix only code style
     */
    public static function fixStyle(): PhixerResult
    {
        return self::fix(['style', 'code-style']);
    }

    /**
     * Fix only PHPStan issues
     */
    public static function fixPhpStan(): PhixerResult
    {
        return self::fix(['phpstan']);
    }

    /**
     * Fix only DocBlocks
     */
    public static function fixDocBlocks(): PhixerResult
    {
        return self::fix(['docblocks']);
    }

    /**
     * Fix only imports
     */
    public static function fixImports(): PhixerResult
    {
        return self::fix(['imports']);
    }

    /**
     * Create a new configuration
     */
    public static function config(): PhixerConfig
    {
        return new PhixerConfig();
    }
}
