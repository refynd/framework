# Refynd Phixer

Automated code quality fixing system for the Refynd Framework.

## Quick Start

```php
use Refynd\Phixer\Phixer;

// Initialize and run all fixes
Phixer::init('/path/to/project');
$result = Phixer::fixAll();

if ($result->isSuccessful()) {
    echo "Fixed " . $result->getFixedFileCount() . " files\n";
}
```

## Command Line

```bash
# Via composer (recommended)
composer fix

# Direct usage
php -r "require 'vendor/autoload.php'; \Refynd\Phixer\Phixer::init(__DIR__); \Refynd\Phixer\Phixer::fixAll();"
```

## Features

- **PHPStan Issue Resolution**: Automatic fixing of static analysis issues
- **Code Style Standardization**: PSR-12 compliance with PHP 8.4+ features  
- **DocBlock Enhancement**: Automatic generation and formatting
- **Import Optimization**: Sorting and cleanup of use statements
- **Framework Integration**: Built into Refynd's module system

## Documentation

See [docs/AUTOMATED_FIXING.md](../docs/AUTOMATED_FIXING.md) for comprehensive documentation.

## Classes

- `PhixerEngine` - Main fixing engine
- `PhixerConfig` - Configuration management  
- `PhixerResult` - Operation results
- `Phixer` - Static facade
- `PhixerModule` - Framework module
- `PhixerCommand` - Console command
