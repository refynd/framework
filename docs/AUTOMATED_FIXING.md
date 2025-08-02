# Refynd Phixer - Automated Code Quality Fixing

The Refynd Phixer is a comprehensive automated code quality fixing system built directly into the framework. It provides intelligent code analysis and automated fixing capabilities for maintaining enterprise-grade code quality.

## Overview

Phixer is now a core component of the Refynd Framework, located in `src/Phixer/`. It provides:

- **PHPStan Issue Resolution**: Automatic fixing of static analysis issues
- **Code Style Standardization**: PSR-12 compliance with PHP 8.4+ features
- **DocBlock Enhancement**: Automatic generation and formatting of documentation
- **Import Optimization**: Sorting and cleanup of use statements
- **Comprehensive Integration**: Built into the framework's module system

## Architecture

### Core Classes

- **`PhixerEngine`**: Main engine for automated fixing operations
- **`PhixerConfig`**: Configuration management for Phixer operations
- **`PhixerResult`**: Result object containing fix outcomes and statistics
- **`Phixer`**: Static facade for easy access to Phixer functionality
- **`PhixerModule`**: Framework module for dependency injection integration
- **`PhixerCommand`**: Console command for CLI usage

## Usage

### 1. Framework Integration

When using Phixer as part of the Refynd Framework:

```php
use Refynd\Phixer\Phixer;

// Initialize with project root
Phixer::init('/path/to/project');

// Run all fixes
$result = Phixer::fixAll();

// Run specific fixes
$result = Phixer::fixStyle();
$result = Phixer::fixPhpStan();
$result = Phixer::fixDocBlocks();
$result = Phixer::fixImports();

// Dry run mode
$result = Phixer::dryRun();

// Silent mode
$result = Phixer::silent();
```

### 2. Direct Engine Usage

For more control over the fixing process:

```php
use Refynd\Phixer\PhixerEngine;
use Refynd\Phixer\PhixerConfig;

// Create configuration
$config = new PhixerConfig(
    verbose: true,
    dryRun: false,
    enabledFixes: ['style', 'phpstan'],
    excludedPaths: ['vendor', 'cache']
);

// Create engine
$engine = new PhixerEngine('/path/to/project', $config);

// Run fixes
$result = $engine->runAllFixes();

// Check results
if ($result->isSuccessful()) {
    echo "Fixed " . $result->getFixedFileCount() . " files\n";
} else {
    echo "Errors: " . implode("\n", $result->getErrors()) . "\n";
}
```

### 3. Configuration Options

```php
use Refynd\Phixer\PhixerConfig;

// Create configurations for different scenarios
$config = PhixerConfig::silent();        // No output
$config = PhixerConfig::dryRun();        // Check only, no changes
$config = PhixerConfig::onlyFixes(['style']); // Specific fixes only

// Fluent configuration
$config = (new PhixerConfig())
    ->setVerbose(true)
    ->setDryRun(false)
    ->setEnabledFixes(['all'])
    ->setExcludedPaths(['vendor', 'node_modules']);
```

## Command Line Usage

### 1. Via Composer Scripts (Recommended)

```bash
# Run all fixes
composer fix

# Specific fixes
composer fix:style       # Style fixes only
composer fix:dry         # Dry run mode

# Quality checks
composer check:all       # Complete validation
composer analyse         # PHPStan only
composer test           # Tests only
```

### 2. Via Framework Console (when available)

```bash
# Using the framework's console system
php refynd phixer
php refynd phixer style --dry-run
php refynd phixer all --verbose
```

### 3. Direct PHP Usage

```bash
# Direct Phixer calls
php -r "require 'vendor/autoload.php'; \Refynd\Phixer\Phixer::init(__DIR__); \Refynd\Phixer\Phixer::fixAll();"
php -r "require 'vendor/autoload.php'; \Refynd\Phixer\Phixer::init(__DIR__); \Refynd\Phixer\Phixer::dryRun();"
```

## Fixing Capabilities

### 1. PHPStan Issues

- **Missing return types**: Automatically infers and adds return types
- **Missing parameter types**: Adds type hints to method parameters
- **Unused imports**: Removes unnecessary use statements
- **Type annotations**: Improves type safety throughout codebase

### 2. Code Style

- **PSR-12 Compliance**: Full PSR-12 standard implementation
- **Modern PHP Features**: PHP 8.4+ syntax and features
- **Array Syntax**: Converts to short array syntax
- **Spacing**: Consistent spacing around operators and brackets
- **Imports**: Alphabetical sorting and organization

### 3. DocBlocks

- **Missing DocBlocks**: Adds documentation for methods and classes
- **Formatting**: Standardizes DocBlock structure and formatting
- **Type Information**: Enhances type information in comments
- **Parameter Documentation**: Completes @param and @return tags

### 4. Import Optimization

- **Sorting**: Alphabetical organization of use statements
- **Deduplication**: Removes duplicate imports
- **Cleanup**: Removes unused import statements
- **Grouping**: Organizes imports by type and namespace

## Integration Points

### 1. Module System Integration

```php
// In your application bootstrap
use Refynd\Modules\PhixerModule;

$moduleManager->register(new PhixerModule());
```

### 2. Container Integration

```php
// Access via container
$phixerEngine = $container->get(\Refynd\Phixer\PhixerEngine::class);
$result = $phixerEngine->runAllFixes();
```

### 3. Console Integration

The PhixerCommand is automatically registered when the PhixerModule is loaded, providing CLI access to all Phixer functionality.

## Configuration Files

### 1. PHP-CS-Fixer Configuration

Phixer automatically creates `.php-cs-fixer.php` with optimized rules:

```php
// Generated automatically, but can be customized
<?php
$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

$config = new PhpCsFixer\Config();
return $config->setRiskyAllowed(true)->setRules([
    '@PSR12' => true,
    '@PHP82Migration' => true,
    // ... comprehensive rule set
]);
```

### 2. Custom Configuration

```php
// Create custom fixing configuration
$config = new PhixerConfig();
$config->setEnabledFixes(['style', 'imports']);
$config->setExcludedPaths(['legacy', 'vendor']);

// Use with engine
$engine = new PhixerEngine($projectRoot, $config);
```

## Best Practices

### 1. Development Workflow

1. **Pre-commit Hooks**: Install git hooks for automatic validation
2. **CI/CD Integration**: Use GitHub Actions for automated quality checks
3. **Regular Fixing**: Run fixes regularly during development
4. **Team Standards**: Use consistent configuration across team

### 2. Configuration Management

1. **Project-specific**: Customize rules for each project's needs
2. **Incremental**: Start with basic fixes, gradually add more
3. **Documentation**: Document custom rules and exclusions
4. **Version Control**: Track configuration changes in git

### 3. Error Handling

```php
try {
    $result = Phixer::fixAll();
    
    if (!$result->isSuccessful()) {
        foreach ($result->getErrors() as $error) {
            echo "Manual attention needed: $error\n";
        }
    }
} catch (PhixerException $e) {
    echo "Phixer error: " . $e->getMessage() . "\n";
}
```

## Advanced Usage

### 1. Custom Fix Implementation

Extend PhixerEngine for custom fixing logic:

```php
class CustomPhixerEngine extends PhixerEngine
{
    protected function fixCustomIssues(): void
    {
        // Implement custom fixing logic
    }
}
```

### 2. Result Analysis

```php
$result = Phixer::fixAll();

// Get detailed information
$fixedFiles = $result->getFixedFiles();
$errors = $result->getErrors();
$executionTime = $result->getExecutionTime();

// Export results
$json = $result->toJson();
$array = $result->toArray();
```

### 3. Programmatic Integration

```php
// Integrate Phixer into custom workflows
class DeploymentPipeline
{
    public function runQualityChecks(): bool
    {
        $result = Phixer::dryRun();
        
        if ($result->hasErrors()) {
            $this->logger->error('Quality issues found', $result->getErrors());
            return false;
        }
        
        return true;
    }
}
```

## Migration from Legacy Script

The previous `scripts/fix-code.php` has been removed. The functionality is now fully integrated into the framework at `src/Phixer/` and accessible via composer scripts and the Phixer facade.

### Benefits of the New Architecture

1. **Framework Integration**: Proper dependency injection and module system
2. **Better Testing**: Unit testable components
3. **Extensibility**: Easy to extend and customize
4. **Configuration**: Flexible configuration management
5. **Error Handling**: Comprehensive error handling and reporting
6. **Performance**: Optimized execution and memory usage

### Migration Steps

1. **Update Usage**: Use composer scripts or the Phixer facade instead of the old script
2. **Configuration**: Migrate any custom configuration to PhixerConfig classes
3. **Integration**: Register PhixerModule in your application
4. **Testing**: Test the new integration in your development environment

All functionality is now available through the framework's integrated Phixer classes.
