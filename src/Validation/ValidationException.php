<?php

namespace Refynd\Validation;

use Exception;

class ValidationException extends Exception
{
    private array $errors;

    public function __construct(string $message, array $errors = [], int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrorsForField(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public function getFirstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }

        return null;
    }

    public function getFirstErrorForField(string $field): ?string
    {
        $fieldErrors = $this->getErrorsForField($field);
        return $fieldErrors[0] ?? null;
    }

    public function toArray(): array
    {
        return ['message' => $this->getMessage(),
            'errors' => $this->errors,];
    }
}
