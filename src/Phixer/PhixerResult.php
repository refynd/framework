<?php

declare(strict_types = 1);

namespace Refynd\Phixer;

/**
 * Phixer Result
 *
 * Contains the results of a Phixer operation including fixed files and errors.
 */
class PhixerResult
{
    private array $fixedFiles;
    private array $errors;
    private float $executionTime;

    public function __construct(array $fixedFiles, array $errors, ?float $executionTime = null)
    {
        $this->fixedFiles = $fixedFiles;
        $this->errors = $errors;
        $this->executionTime = $executionTime ?? 0.0;
    }

    public function getFixedFiles(): array
    {
        return $this->fixedFiles;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function getFixedFileCount(): int
    {
        return count($this->fixedFiles);
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasFixedFiles(): bool
    {
        return !empty($this->fixedFiles);
    }

    public function isSuccessful(): bool
    {
        return !$this->hasErrors();
    }

    /**
     * Get a summary array of the results
     */
    public function toArray(): array
    {
        return ['fixed_files' => $this->fixedFiles,
            'fixed_file_count' => $this->getFixedFileCount(),
            'errors' => $this->errors,
            'error_count' => $this->getErrorCount(),
            'execution_time' => $this->executionTime,
            'successful' => $this->isSuccessful(),];
    }

    /**
     * Get a JSON representation of the results
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Create a successful result with no fixes needed
     */
    public static function success(): self
    {
        return new self([], []);
    }

    /**
     * Create a result with errors
     */
    public static function withErrors(array $errors): self
    {
        return new self([], $errors);
    }

    /**
     * Create a result with fixed files
     */
    public static function withFixes(array $fixedFiles): self
    {
        return new self($fixedFiles, []);
    }
}
