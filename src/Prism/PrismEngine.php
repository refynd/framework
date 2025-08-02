<?php

namespace Refynd\Prism;

use RuntimeException;

/**
 * PrismEngine - Advanced Template Engine
 * 
 * Enhanced Prism template engine with support for:
 * - Template inheritance and components
 * - Section management and content stacking
 * - Custom directives and filters
 * - Asset management integration
 * - Enhanced error reporting
 * - Performance optimizations
 */
class PrismEngine
{
    protected string $viewPath;
    protected string $cachePath;
    protected array $globals = [];
    protected bool $cacheEnabled = true;
    protected PrismCompiler $compiler;
    
    // Template inheritance and sections
    protected array $sections = [];
    protected array $sectionStack = [];
    protected array $parentSections = [];
    protected ?string $currentLayout = null;
    
    // Component and include data
    protected array $componentStack = [];
    protected array $dataStack = [];
    protected array $templates = []; // Virtual templates storage
    
    // Error handling and debugging
    protected bool $debugMode = false;
    protected array $compilationErrors = [];
    
    // Performance tracking
    protected array $renderStats = [];

    public function __construct(string $viewPath, string $cachePath = '', bool $debugMode = false)
    {
        $this->viewPath = rtrim($viewPath, '/');
        $this->cachePath = rtrim($cachePath, '/') ?: sys_get_temp_dir() . '/prism_cache';
        $this->debugMode = $debugMode;
        $this->compiler = new PrismCompiler();
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
        
        $this->registerDefaultFilters();
    }

    /**
     * Render a template with data
     */
    public function render(string $template, array $data = []): string
    {
        $startTime = microtime(true);
        
        try {
            $templatePath = $this->findTemplate($template);
            $compiledPath = $this->getCompiledPath($templatePath);

            if (!$this->isCacheValid($templatePath, $compiledPath)) {
                $this->compileTemplate($templatePath, $compiledPath);
            }

            $output = $this->renderCompiled($compiledPath, array_merge($this->globals, $data));
            
            // Track performance if debug mode is enabled
            if ($this->debugMode) {
                $this->renderStats[$template] = [
                    'render_time' => microtime(true) - $startTime,
                    'template_path' => $templatePath,
                    'compiled_path' => $compiledPath,
                    'data_keys' => array_keys($data),
                ];
            }
            
            return $output;
        } catch (\Throwable $e) {
            if ($this->debugMode) {
                return $this->renderError($e, $template);
            }
            throw $e;
        }
    }

    /**
     * Add global variables available to all templates
     */
    public function addGlobal(string $key, mixed $value): void
    {
        $this->globals[$key] = $value;
    }

    /**
     * Set multiple global variables
     */
    public function addGlobals(array $globals): void
    {
        $this->globals = array_merge($this->globals, $globals);
    }

    /**
     * Enable or disable template caching
     */
    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
    }

    /**
     * Set debug mode
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
    }

    /**
     * Check if debug mode is enabled
     */
    public function isDebugMode(): bool
    {
        return $this->debugMode;
    }

    /**
     * Register a custom directive
     */
    public function directive(string $name, callable $handler): void
    {
        $this->compiler->directive($name, $handler);
    }

    /**
     * Register a custom filter
     */
    public function filter(string $name, callable $handler): void
    {
        $this->compiler->filter($name, $handler);
    }

    /**
     * Render template string directly
     */
    public function renderString(string $content, array $data = []): string
    {
        $hash = md5($content);
        $cacheFile = $this->cachePath . '/string_' . $hash . '.php';
        
        if (!file_exists($cacheFile) || !$this->cacheEnabled) {
            $compiled = $this->compiler->compile($content);
            if (!is_dir($this->cachePath)) {
                mkdir($this->cachePath, 0755, true);
            }
            file_put_contents($cacheFile, $compiled);
        }
        
        return $this->renderCompiled($cacheFile, array_merge($this->globals, $data));
    }

    /**
     * Add a template to the virtual filesystem
     */
    public function addTemplate(string $name, string $content): void
    {
        $this->templates[$name] = $content;
    }

    /**
     * Register a component
     */
    public function component(string $name, string $view): void
    {
        $this->compiler->component($name, $view);
    }

    /**
     * Clear all compiled templates
     */
    public function clearCache(): void
    {
        $files = glob($this->cachePath . '/*.php');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Get render statistics (debug mode only)
     */
    public function getRenderStats(): array
    {
        return $this->renderStats;
    }

    /**
     * Start a new section
     */
    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    /**
     * End the current section
     */
    public function endSection(): void
    {
        if (empty($this->sectionStack)) {
            throw new RuntimeException('Cannot end section: no section started');
        }
        
        $name = array_pop($this->sectionStack);
        $content = ob_get_clean();
        
        if (isset($this->sections[$name])) {
            $this->sections[$name] .= $content;
        } else {
            $this->sections[$name] = $content;
        }
    }

    /**
     * Set section content directly
     */
    public function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    /**
     * Show section content and end it
     */
    public function showSection(): string
    {
        if (empty($this->sectionStack)) {
            throw new RuntimeException('Cannot show section: no section started');
        }
        
        $name = end($this->sectionStack);
        $this->endSection();
        
        return $this->yieldContent($name);
    }

    /**
     * Yield section content
     */
    public function yieldContent(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /**
     * Get parent section content
     */
    public function getParentSection(): string
    {
        if (empty($this->sectionStack)) {
            return '';
        }
        
        $name = end($this->sectionStack);
        return $this->parentSections[$name] ?? '';
    }

    /**
     * Apply a filter to a value
     */
    public function applyFilter(string $filter, mixed $value, mixed ...$args): mixed
    {
        $filters = $this->compiler->getFilters();
        
        if (!isset($filters[$filter])) {
            throw new RuntimeException("Unknown filter: {$filter}");
        }
        
        return $filters[$filter]($value, ...$args);
    }

    /**
     * Find template file with cascading lookup
     */
    protected function findTemplate(string $template): string
    {
        // Add .prism extension if not present
        if (!str_ends_with($template, '.prism')) {
            $template .= '.prism';
        }

        // Try multiple paths
        $paths = [
            $this->viewPath . '/' . ltrim($template, '/'),
            $this->viewPath . '/templates/' . ltrim($template, '/'),
            $this->viewPath . '/views/' . ltrim($template, '/'),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        throw new RuntimeException("Template not found: {$template}");
    }

    /**
     * Get compiled template path with better hashing
     */
    protected function getCompiledPath(string $templatePath): string
    {
        $hash = md5($templatePath . filemtime($templatePath));
        return $this->cachePath . '/' . $hash . '.php';
    }

    /**
     * Check if cache is valid
     */
    protected function isCacheValid(string $templatePath, string $compiledPath): bool
    {
        if (!$this->cacheEnabled) {
            return false;
        }

        if (!file_exists($compiledPath)) {
            return false;
        }

        return filemtime($templatePath) <= filemtime($compiledPath);
    }

    /**
     * Compile template to PHP with enhanced error handling
     */
    protected function compileTemplate(string $templatePath, string $compiledPath): void
    {
        try {
            $content = file_get_contents($templatePath);
            $compiled = $this->compiler->compile($content, $templatePath);

            // Validate compiled PHP syntax
            if ($this->debugMode) {
                $this->validateCompiledTemplate($compiled);
            }

            file_put_contents($compiledPath, $compiled, LOCK_EX);
        } catch (\Throwable $e) {
            $this->compilationErrors[$templatePath] = $e;
            throw new RuntimeException("Failed to compile template '{$templatePath}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate compiled template syntax
     */
    protected function validateCompiledTemplate(string $compiled): void
    {
        $tokens = token_get_all($compiled);
        
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_OPEN_TAG_WITH_ECHO) {
                // Additional validation can be added here
            }
        }
    }

    /**
     * Render compiled template with enhanced variable scope
     */
    protected function renderCompiled(string $compiledPath, array $data): string
    {
        // Create isolated scope for template execution
        $renderTemplate = function () use ($compiledPath, $data) {
            // Make Prism engine available to template
            $__prism = $this;
            $__data = $data;
            
            // Extract variables into local scope
            extract($data, EXTR_SKIP);
            
            ob_start();
            include $compiledPath;
            return ob_get_clean();
        };

        return $renderTemplate();
    }

    /**
     * Render compilation or runtime errors in debug mode
     */
    protected function renderError(\Throwable $e, string $template): string
    {
        return sprintf(
            '<div style="background: #f8d7da; color: #721c24; padding: 1rem; border: 1px solid #f5c6cb; border-radius: 4px; font-family: monospace;">' .
            '<h3>Prism Template Error</h3>' .
            '<p><strong>Template:</strong> %s</p>' .
            '<p><strong>Error:</strong> %s</p>' .
            '<p><strong>File:</strong> %s:%d</p>' .
            '<pre>%s</pre>' .
            '</div>',
            htmlspecialchars($template),
            htmlspecialchars($e->getMessage()),
            htmlspecialchars($e->getFile()),
            $e->getLine(),
            htmlspecialchars($e->getTraceAsString())
        );
    }

    /**
     * Register default filters
     */
    protected function registerDefaultFilters(): void
    {
        // Register enhanced default filters
        $this->compiler->filter('upper', function ($value) {
            return strtoupper((string)$value);
        });

        $this->compiler->filter('lower', function ($value) {
            return strtolower((string)$value);
        });

        $this->compiler->filter('currency', function ($value) {
            return '$' . number_format((float)$value, 2);
        });

        $this->compiler->filter('date', function ($value, $format = 'Y-m-d') {
            $timestamp = is_numeric($value) ? $value : strtotime($value);
            return date($format, $timestamp);
        });

        $this->compiler->filter('truncate', function ($value, $length = 100, $suffix = '...') {
            return strlen($value) > $length ? substr($value, 0, $length) . $suffix : $value;
        });

        $this->compiler->filter('capitalize', function ($value) {
            return ucfirst((string)$value);
        });

        $this->compiler->filter('title', function ($value) {
            return ucwords((string)$value);
        });

        $this->compiler->filter('nl2br', function ($value) {
            return nl2br((string)$value);
        });

        $this->compiler->filter('strip_tags', function ($value) {
            return strip_tags((string)$value);
        });

        $this->compiler->filter('trim', function ($value) {
            return trim((string)$value);
        });

        $this->compiler->filter('reverse', function ($value) {
            if (is_array($value)) {
                return array_reverse($value);
            }
            return strrev((string)$value);
        });

        $this->compiler->filter('length', function ($value) {
            if (is_array($value) || is_countable($value)) {
                return count($value);
            }
            return strlen((string)$value);
        });

        $this->compiler->filter('first', function ($value) {
            if (is_array($value) && !empty($value)) {
                return reset($value);
            }
            return '';
        });

        $this->compiler->filter('last', function ($value) {
            if (is_array($value) && !empty($value)) {
                return end($value);
            }
            return '';
        });

        $this->compiler->filter('join', function ($value, $separator = ', ') {
            if (is_array($value)) {
                return implode($separator, $value);
            }
            return (string)$value;
        });
    }

    /**
     * Get the compiler instance
     */
    public function getCompiler(): PrismCompiler
    {
        return $this->compiler;
    }
}

