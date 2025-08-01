<?php

namespace Refynd\Prism;

use RuntimeException;

/**
 * PrismEngine - Refynd's Template Engine
 * 
 * Compiles and renders Prism templates with expressive syntax.
 * Supports template inheritance, includes, and powerful directives.
 */
class PrismEngine
{
    protected string $viewPath;
    protected string $cachePath;
    protected array $globals = [];
    protected bool $cacheEnabled = true;

    public function __construct(string $viewPath, string $cachePath = '')
    {
        $this->viewPath = rtrim($viewPath, '/');
        $this->cachePath = rtrim($cachePath, '/') ?: sys_get_temp_dir() . '/prism_cache';
        
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    /**
     * Render a template with data
     */
    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);
        $compiledPath = $this->getCompiledPath($templatePath);

        if (!$this->isCacheValid($templatePath, $compiledPath)) {
            $this->compileTemplate($templatePath, $compiledPath);
        }

        return $this->renderCompiled($compiledPath, array_merge($this->globals, $data));
    }

    /**
     * Add global variables available to all templates
     */
    public function addGlobal(string $key, $value): void
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
     * Find template file
     */
    protected function findTemplate(string $template): string
    {
        // Add .prism extension if not present
        if (!str_ends_with($template, '.prism')) {
            $template .= '.prism';
        }

        $templatePath = $this->viewPath . '/' . ltrim($template, '/');

        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template not found: {$template}");
        }

        return $templatePath;
    }

    /**
     * Get compiled template path
     */
    protected function getCompiledPath(string $templatePath): string
    {
        $hash = md5($templatePath);
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
     * Compile template to PHP
     */
    protected function compileTemplate(string $templatePath, string $compiledPath): void
    {
        $content = file_get_contents($templatePath);
        $compiler = new PrismCompiler();
        $compiled = $compiler->compile($content);

        file_put_contents($compiledPath, $compiled);
    }

    /**
     * Render compiled template
     */
    protected function renderCompiled(string $compiledPath, array $data): string
    {
        extract($data);
        
        ob_start();
        include $compiledPath;
        return ob_get_clean();
    }
}

/**
 * PrismCompiler - Compiles Prism syntax to PHP
 */
class PrismCompiler
{
    protected array $patterns = [];

    public function __construct()
    {
        $this->registerPatterns();
    }

    /**
     * Compile Prism template to PHP
     */
    public function compile(string $content): string
    {
        $content = $this->compileComments($content);
        $content = $this->compileExtends($content);
        $content = $this->compileIncludes($content);
        $content = $this->compileSections($content);
        $content = $this->compileYields($content);
        $content = $this->compileControlStructures($content);
        $content = $this->compileEchos($content);

        return "<?php\n" . $content;
    }

    /**
     * Register compilation patterns
     */
    protected function registerPatterns(): void
    {
        // Control structures
        $this->patterns = [
            // If statements
            '/\{\%\s*if\s+(.+?)\s*\%\}/' => '<?php if ($1): ?>',
            '/\{\%\s*elseif\s+(.+?)\s*\%\}/' => '<?php elseif ($1): ?>',
            '/\{\%\s*else\s*\%\}/' => '<?php else: ?>',
            '/\{\%\s*endif\s*\%\}/' => '<?php endif; ?>',

            // Foreach loops
            '/\{\%\s*foreach\s+(.+?)\s+as\s+(.+?)\s*\%\}/' => '<?php foreach ($1 as $2): ?>',
            '/\{\%\s*endforeach\s*\%\}/' => '<?php endforeach; ?>',

            // For loops
            '/\{\%\s*for\s+(.+?)\s*\%\}/' => '<?php for ($1): ?>',
            '/\{\%\s*endfor\s*\%\}/' => '<?php endfor; ?>',

            // While loops
            '/\{\%\s*while\s+(.+?)\s*\%\}/' => '<?php while ($1): ?>',
            '/\{\%\s*endwhile\s*\%\}/' => '<?php endwhile; ?>',

            // PHP code blocks
            '/\{\%\s*php\s*\%\}/' => '<?php',
            '/\{\%\s*endphp\s*\%\}/' => '?>',

            // Variables
            '/\{\{\s*(.+?)\s*\}\}/' => '<?= htmlspecialchars($1 ?? \'\', ENT_QUOTES, \'UTF-8\') ?>',
            '/\{\{\{\s*(.+?)\s*\}\}\}/' => '<?= $1 ?? \'\' ?>',
        ];
    }

    /**
     * Compile template comments
     */
    protected function compileComments(string $content): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $content);
    }

    /**
     * Compile @extends directive
     */
    protected function compileExtends(string $content): string
    {
        $pattern = '/@extends\s*\(\s*[\'"](.+?)[\'"]\s*\)/';
        
        if (preg_match($pattern, $content, $matches)) {
            $layout = $matches[1];
            $content = preg_replace($pattern, '', $content);
            
            // This is a simplified implementation
            // In a full implementation, you'd handle layout inheritance properly
            $content = "<?php /* @extends {$layout} */ ?>\n" . $content;
        }

        return $content;
    }

    /**
     * Compile @include directive
     */
    protected function compileIncludes(string $content): string
    {
        $pattern = '/@include\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/';
        
        return preg_replace_callback($pattern, function ($matches) {
            $template = $matches[1];
            $data = $matches[2] ?? '[]';
            
            return "<?php echo \$this->render('{$template}', {$data}); ?>";
        }, $content);
    }

    /**
     * Compile @section directive
     */
    protected function compileSections(string $content): string
    {
        // @section('name')
        $content = preg_replace('/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php $this->startSection(\'$1\'); ?>', $content);
        
        // @endsection
        $content = preg_replace('/@endsection/', '<?php $this->endSection(); ?>', $content);

        return $content;
    }

    /**
     * Compile @yield directive
     */
    protected function compileYields(string $content): string
    {
        return preg_replace('/@yield\s*\(\s*[\'"](.+?)[\'"]\s*\)/', '<?php echo $this->yieldContent(\'$1\'); ?>', $content);
    }

    /**
     * Compile control structures
     */
    protected function compileControlStructures(string $content): string
    {
        foreach ($this->patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }

        return $content;
    }

    /**
     * Compile echo statements
     */
    protected function compileEchos(string $content): string
    {
        // Raw echo {{{ }}}
        $content = preg_replace('/\{\{\{\s*(.+?)\s*\}\}\}/', '<?= $1 ?? \'\' ?>', $content);
        
        // Escaped echo {{ }}
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?= htmlspecialchars($1 ?? \'\', ENT_QUOTES, \'UTF-8\') ?>', $content);

        return $content;
    }
}
