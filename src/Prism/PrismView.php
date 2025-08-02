<?php

namespace Refynd\Prism;

/**
 * PrismView - Template View Class
 *
 * Handles rendering and data injection for Prism templates.
 * Provides a convenient interface for template rendering.
 */
class PrismView
{
    protected PrismEngine $engine;
    protected string $template;
    protected array $data = [];

    public function __construct(PrismEngine $engine, string $template, array $data = [])
    {
        $this->engine = $engine;
        $this->template = $template;
        $this->data = $data;
    }

    /**
     * Add data to the view
     */
    public function with(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Add multiple data items to the view
     */
    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    /**
     * Render the template
     */
    public function render(): string
    {
        return $this->engine->render($this->template, $this->data);
    }

    /**
     * Convert to string (renders the template)
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable $e) {
            return "Template Error: " . $e->getMessage();
        }
    }
}

/**
 * Global helper function for creating views
 */
if (!function_exists('view')) {
    /**
     * Create a new Prism view instance
     *
     * @param string $template
     * @param array $data
     * @return PrismView
     */
    function view(string $template, array $data = []): PrismView
    {
        static $engine = null;

        if ($engine === null) {
            // Default paths - can be configured through container
            $viewPath = getcwd() . '/views';
            $cachePath = getcwd() . '/storage/cache/views';

            $engine = new PrismEngine($viewPath, $cachePath);
        }

        return new PrismView($engine, $template, $data);
    }
}
