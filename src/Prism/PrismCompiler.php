<?php

namespace Refynd\Prism;

use RuntimeException;

/**
 * PrismCompiler - Advanced Template Compiler
 *
 * Enhanced Prism compiler with support for:
 * - Template inheritance and components
 * - Custom directives and filters
 * - Optimized compilation
 * - Better error handling
 */
class PrismCompiler
{
    protected array $patterns = [];
    protected array $customDirectives = [];
    protected array $filters = [];
    protected array $components = [];
    protected string $currentTemplate = '';
    protected array $sections = [];
    protected array $compilationStack = [];

    public function __construct()
    {
        $this->registerPatterns();
        $this->registerBuiltinFilters();
        $this->registerBuiltinDirectives();
    }

    /**
     * Compile Prism template to PHP
     */
    public function compile(string $content, string $templatePath = ''): string
    {
        $this->currentTemplate = $templatePath;
        $this->compilationStack = [];

        try {
            // Pre-processing
            $content = $this->preprocess($content);

            // Core compilation phases
            $content = $this->compileComments($content);
            $content = $this->compileRawBlocks($content);
            $content = $this->compileExtends($content);
            $content = $this->compileComponents($content);
            $content = $this->compileIncludes($content);
            $content = $this->compileSections($content);
            $content = $this->compileYields($content);
            $content = $this->compileCustomDirectives($content);
            $content = $this->compileControlStructures($content);
            $content = $this->compileFilters($content);
            $content = $this->compileEchos($content);
            $content = $this->compileAssets($content);

            // Post-processing
            $content = $this->postprocess($content);

            return $this->wrapCompiledTemplate($content);
        } catch (\Throwable $e) {
            throw new RuntimeException("Compilation error in template '{$templatePath}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Register a custom directive
     */
    public function directive(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    /**
     * Register a custom filter
     */
    public function filter(string $name, callable $handler): void
    {
        $this->filters[$name] = $handler;
    }

    /**
     * Register a component
     */
    public function component(string $name, string $view): void
    {
        $this->components[$name] = $view;
    }

    /**
     * Pre-process template content
     */
    protected function preprocess(string $content): string
    {
        // Remove BOM if present
        $content = ltrim($content, "\xEF\xBB\xBF");

        // Normalize line endings
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        return $content;
    }

    /**
     * Post-process compiled content
     */
    protected function postprocess(string $content): string
    {
        // Remove unnecessary whitespace between PHP tags
        $content = preg_replace('/\?>\s+<\?php/', '', $content);

        // Optimize consecutive echo statements
        $content = preg_replace('/\?>\s*<\?=/', '; echo ', $content);

        return $content;
    }

    /**
     * Wrap compiled template with proper PHP structure
     */
    protected function wrapCompiledTemplate(string $content): string
    {
        return "<?php\n" .
               "/* Compiled Prism Template: {$this->currentTemplate} */\n" .
               "/* Compilation Time: " . date('Y-m-d H:i:s') . " */\n" .
               "?>" . $content;
    }

    /**
     * Register compilation patterns
     */
    protected function registerPatterns(): void
    {
        $this->patterns = [// Enhanced control structures with better error handling
            '/\{\%\s*if\s+(.+?)\s*\%\}/s' => '<?php if ($1): ?>',
            '/\{\%\s*elseif\s+(.+?)\s*\%\}/s' => '<?php elseif ($1): ?>',
            '/\{\%\s*else\s*\%\}/' => '<?php else: ?>',
            '/\{\%\s*endif\s*\%\}/' => '<?php endif; ?>',

            // Enhanced loops with key support
            '/\{\%\s*foreach\s+(.+?)\s+as\s+(.+?)\s*\%\}/s' => '<?php foreach ($1 as $2): ?>',
            '/\{\%\s*forelse\s+(.+?)\s+as\s+(.+?)\s*\%\}/s' => '<?php if (!empty($1)): foreach ($1 as $2): ?>',
            '/\{\%\s*empty\s*\%\}/' => '<?php endforeach; else: ?>',
            '/\{\%\s*endforelse\s*\%\}/' => '<?php endif; ?>',
            '/\{\%\s*endforeach\s*\%\}/' => '<?php endforeach; ?>',

            // For loops with range support
            '/\{\%\s*for\s+(.+?)\s*\%\}/s' => '<?php for ($1): ?>',
            '/\{\%\s*endfor\s*\%\}/' => '<?php endfor; ?>',

            // While loops
            '/\{\%\s*while\s+(.+?)\s*\%\}/s' => '<?php while ($1): ?>',
            '/\{\%\s*endwhile\s*\%\}/' => '<?php endwhile; ?>',

            // Break and continue
            '/\{\%\s*break\s*\%\}/' => '<?php break; ?>',
            '/\{\%\s*continue\s*\%\}/' => '<?php continue; ?>',

            // Switch statements
            '/\{\%\s*switch\s+(.+?)\s*\%\}/s' => '<?php switch ($1): ?>',
            '/\{\%\s*case\s+(.+?)\s*\%\}/s' => '<?php case $1: ?>',
            '/\{\%\s*default\s*\%\}/' => '<?php default: ?>',
            '/\{\%\s*endswitch\s*\%\}/' => '<?php endswitch; ?>',

            // PHP code blocks
            '/\{\%\s*php\s*\%\}/' => '<?php',
            '/\{\%\s*endphp\s*\%\}/' => '?>',

            // Isset and empty checks
            '/\{\%\s*isset\s+(.+?)\s*\%\}/s' => '<?php if (isset($1)): ?>',
            '/\{\%\s*empty\s+(.+?)\s*\%\}/s' => '<?php if (empty($1)): ?>',
            '/\{\%\s*endisset\s*\%\}/' => '<?php endif; ?>',
            '/\{\%\s*endempty\s*\%\}/' => '<?php endif; ?>',];
    }

    /**
     * Register built-in filters
     */
    protected function registerBuiltinFilters(): void
    {
        $this->filters = ['upper' => fn ($value) => strtoupper($value),
            'lower' => fn ($value) => strtolower($value),
            'title' => fn ($value) => ucwords($value),
            'capitalize' => fn ($value) => ucfirst($value),
            'length' => fn ($value) => is_countable($value) ? count($value) : strlen($value),
            'reverse' => fn ($value) => is_array($value) ? array_reverse($value) : strrev($value),
            'sort' => function ($value) {
                if (is_array($value)) {
                    sort($value);
                    return $value;
                }
                return $value;
            },
            'json' => fn ($value) => json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'date' => fn ($value, $format = 'Y-m-d') => date($format, is_numeric($value) ? $value : strtotime($value)),
            'default' => fn ($value, $default = '') => $value ?: $default,
            'escape' => fn ($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'raw' => fn ($value) => $value,
            'trim' => fn ($value) => trim($value),
            'slug' => fn ($value) => preg_replace('/[^a-z0-9]+/', '-', strtolower($value)),
            'truncate' => fn ($value, $length = 100, $suffix = '...') =>
                strlen($value) > $length ? substr($value, 0, $length) . $suffix : $value,];
    }

    /**
     * Register built-in directives
     */
    protected function registerBuiltinDirectives(): void
    {
        // Control structure directives
        $this->directive('if', function ($expression) {
            return '<?php if (' . $expression . '): ?>';
        });

        $this->directive('elseif', function ($expression) {
            return '<?php elseif (' . $expression . '): ?>';
        });

        $this->directive('else', function () {
            return '<?php else: ?>';
        });

        $this->directive('endif', function () {
            return '<?php endif; ?>';
        });

        $this->directive('foreach', function ($expression) {
            return '<?php foreach (' . $expression . '): ?>';
        });

        $this->directive('endforeach', function () {
            return '<?php endforeach; ?>';
        });

        $this->directive('for', function ($expression) {
            return '<?php for (' . $expression . '): ?>';
        });

        $this->directive('endfor', function () {
            return '<?php endfor; ?>';
        });

        $this->directive('while', function ($expression) {
            return '<?php while (' . $expression . '): ?>';
        });

        $this->directive('endwhile', function () {
            return '<?php endwhile; ?>';
        });

        $this->directive('forelse', function ($expression) {
            return '<?php if (!empty(' . preg_replace('/\s+as\s+.+$/', '', $expression) . ')): foreach (' . $expression . '): ?>';
        });

        $this->directive('empty', function () {
            return '<?php endforeach; else: ?>';
        });

        $this->directive('endforelse', function () {
            return '<?php endif; ?>';
        });

        $this->directive('switch', function ($expression) {
            return '<?php switch (' . $expression . '): ?>';
        });

        $this->directive('case', function ($expression) {
            return '<?php case ' . $expression . ': ?>';
        });

        $this->directive('break', function () {
            return '<?php break; ?>';
        });

        $this->directive('default', function () {
            return '<?php default: ?>';
        });

        $this->directive('endswitch', function () {
            return '<?php endswitch; ?>';
        });

        // Framework integration directives
        $this->directive('csrf', function () {
            return '<?php echo csrf_token(); ?>';
        });

        $this->directive('method', function ($method) {
            return '<?php echo method_field(\'' . trim($method, '"\'') . '\'); ?>';
        });

        $this->directive('auth', function ($guard = null) {
            if ($guard) {
                return '<?php if (auth(' . $guard . ')->check()): ?>';
            }
            return '<?php if (auth()->check()): ?>';
        });

        $this->directive('guest', function ($guard = null) {
            if ($guard) {
                return '<?php if (auth(' . $guard . ')->guest()): ?>';
            }
            return '<?php if (auth()->guest()): ?>';
        });

        $this->directive('endauth', function () {
            return '<?php endif; ?>';
        });

        $this->directive('endguest', function () {
            return '<?php endif; ?>';
        });

        $this->directive('json', function ($expression) {
            return '<?php echo json_encode(' . $expression . ', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>';
        });

        $this->directive('dd', function ($expression) {
            return '<?php dd(' . $expression . '); ?>';
        });

        // Component directive
        $this->directive('component', function ($expression) {
            return '<?php echo $this->renderComponent(' . $expression . '); ?>';
        });

        $this->directive('endcomponent', function () {
            return '<?php echo $this->endComponent(); ?>';
        });
    }

    /**
     * Compile template comments
     */
    protected function compileComments(string $content): string
    {
        // Remove single-line comments {{-- comment --}}
        $content = preg_replace('/\{\{--.*?--\}\}/', '', $content);

        // Remove multi-line comments
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

        return $content;
    }

    /**
     * Compile raw blocks that shouldn't be processed
     */
    protected function compileRawBlocks(string $content): string
    {
        return preg_replace_callback('/\{\%\s*raw\s*\%\}(.*?)\{\%\s*endraw\s*\%\}/s', function ($matches) {
            return base64_encode($matches[1]);
        }, $content);
    }

    /**
     * Compile @extends directive with proper layout inheritance
     */
    protected function compileExtends(string $content): string
    {
        $pattern = '/@extends\s*\(\s*[\'"](.+?)[\'"]\s*\)/';

        if (preg_match($pattern, $content, $matches)) {
            $layout = $matches[1];
            $content = preg_replace($pattern, '', $content);

            // Store layout information for proper inheritance
            $content = "<?php \$__prism_layout = '{$layout}'; ?>\n" . $content;
        }

        return $content;
    }

    /**
     * Compile component directives
     */
    protected function compileComponents(string $content): string
    {
        // @component('name', ['prop' => 'value'])
        $pattern = '/@component\s*\(\s*[\'"](.+?)[\'"]\s*(?:, \s*(.+?))?\s*\)/';

        $content = preg_replace_callback($pattern, function ($matches) {
            $component = $matches[1];
            $props = $matches[2] ?? '[]';

            return "<?php \$__componentData = array_merge(\$__data ?? [], {$props}); ?>" .
                   "<?php \$__component = component('{$component}', \$__componentData); ?>" .
                   "<?php echo \$__component->render(); ?>";
        }, $content);

        // @endcomponent
        $content = preg_replace('/@endcomponent/', '', $content);

        return $content;
    }

    /**
     * Compile @include directive with data isolation
     */
    protected function compileIncludes(string $content): string
    {
        $pattern = '/@include\s*\(\s*[\'"](.+?)[\'"]\s*(?:, \s*(.+?))?\s*\)/';

        return preg_replace_callback($pattern, function ($matches) {
            $template = $matches[1];
            $data = $matches[2] ?? '[]';

            return "<?php echo \$__prism->render('{$template}', array_merge(\$__data ?? [], {$data})); ?>";
        }, $content);
    }

    /**
     * Enhanced section compilation with content stacking
     */
    protected function compileSections(string $content): string
    {
        // @section('name')
        $content = preg_replace(
            '/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php \$__prism->startSection(\'$1\'); ?>',
            $content
        );

        // @section('name', 'content')
        $content = preg_replace(
            '/@section\s*\(\s*[\'"](.+?)[\'"]\s*, \s*[\'"](.+?)[\'"]\s*\)/',
            '<?php \$__prism->setSection(\'$1\', \'$2\'); ?>',
            $content
        );

        // @parent (append to parent section)
        $content = preg_replace('/@parent/', '<?php echo \$__prism->getParentSection(); ?>', $content);

        // @show (display section immediately)
        $content = preg_replace('/@show/', '<?php echo \$__prism->showSection(); ?>', $content);

        // @endsection
        $content = preg_replace('/@endsection/', '<?php \$__prism->endSection(); ?>', $content);

        return $content;
    }

    /**
     * Compile @yield directive with default content
     */
    protected function compileYields(string $content): string
    {
        // @yield('name', 'default')
        $content = preg_replace(
            '/@yield\s*\(\s*[\'"](.+?)[\'"]\s*, \s*[\'"](.+?)[\'"]\s*\)/',
            '<?php echo \$__prism->yieldContent(\'$1\', \'$2\'); ?>',
            $content
        );

        // @yield('name')
        $content = preg_replace(
            '/@yield\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php echo \$__prism->yieldContent(\'$1\'); ?>',
            $content
        );

        return $content;
    }

    /**
     * Compile custom directives
     */
    protected function compileCustomDirectives(string $content): string
    {
        foreach ($this->customDirectives as $name => $handler) {
            $pattern = '/@' . $name . '\s*(?:\(\s*(.+?)\s*\))?/';

            $content = preg_replace_callback($pattern, function ($matches) use ($handler) {
                $expression = $matches[1] ?? '';
                return $handler($expression);
            }, $content);
        }

        return $content;
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
     * Compile filters
     */
    protected function compileFilters(string $content): string
    {
        // Pattern: {{ $variable | filter:arg1:arg2 }}
        $pattern = '/\{\{\s*(.+?)\s*\|\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*(?::\s*(.+?))?\s*\}\}/';

        return preg_replace_callback($pattern, function ($matches) {
            $variable = trim($matches[1]);
            $filter = $matches[2];
            $args = isset($matches[3]) ? explode(':', $matches[3]) : [];

            if (!isset($this->filters[$filter])) {
                throw new RuntimeException("Unknown filter: {$filter}");
            }

            $argString = '';
            if (!empty($args)) {
                $argString = ', ' . implode(', ', array_map('trim', $args));
            }

            return "<?= \$__prism->applyFilter('{$filter}', {$variable}{$argString}) ?>";
        }, $content);
    }

    /**
     * Compile echo statements with XSS protection
     */
    protected function compileEchos(string $content): string
    {
        // Raw echo {{{ }}} - no escaping
        $content = preg_replace('/\{\{\{\s*(.+?)\s*\}\}\}/', '<?= $1 ?? \'\' ?>', $content);

        // Escaped echo {{ }} - HTML escaped
        $content = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/',
            '<?= htmlspecialchars($1 ?? \'\', ENT_QUOTES, \'UTF-8\') ?>',
            $content
        );

        return $content;
    }

    /**
     * Get registered filters
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get registered directives
     */
    public function getDirectives(): array
    {
        return $this->customDirectives;
    }

    /**
     * Get registered components
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * Compile asset directives
     */
    protected function compileAssets(string $content): string
    {
        // @asset('path')
        $content = preg_replace(
            '/@asset\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php echo asset(\'$1\'); ?>',
            $content
        );

        // @css('path')
        $content = preg_replace(
            '/@css\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php echo \'<link rel="stylesheet" href="\' . asset(\'$1\') . \'">\'; ?>',
            $content
        );

        // @js('path')
        $content = preg_replace(
            '/@js\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            '<?php echo \'<script src="\' . asset(\'$1\') . \'"></script>\'; ?>',
            $content
        );

        return $content;
    }
}
