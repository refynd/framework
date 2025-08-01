<?php

namespace Refynd\Validation;

class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    private array $errors = [];
    private array $customRules = [];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function validate(): array
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $this->validateField($field, $rules);
        }

        if (!empty($this->errors)) {
            throw new ValidationException('Validation failed', $this->errors);
        }

        return $this->data;
    }

    public function fails(): bool
    {
        try {
            $this->validate();
            return false;
        } catch (ValidationException) {
            return true;
        }
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        try {
            $this->validate();
        } catch (ValidationException) {
            // Errors are already set
        }

        return $this->errors;
    }

    private function validateField(string $field, string|array $rules): void
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;
        $value = $this->getValue($field);

        foreach ($rules as $rule) {
            $this->validateRule($field, $value, $rule);
        }
    }

    private function validateRule(string $field, mixed $value, string $rule): void
    {
        $parameters = [];
        
        if (strpos($rule, ':') !== false) {
            [$rule, $parameterString] = explode(':', $rule, 2);
            $parameters = explode(',', $parameterString);
        }

        $method = 'validate' . ucfirst($rule);

        if (method_exists($this, $method)) {
            $valid = $this->$method($field, $value, $parameters);
        } elseif (isset($this->customRules[$rule])) {
            $valid = call_user_func($this->customRules[$rule], $field, $value, $parameters, $this->data);
        } else {
            throw new \InvalidArgumentException("Validation rule '{$rule}' does not exist");
        }

        if (!$valid) {
            $this->addError($field, $rule, $parameters);
        }
    }

    private function getValue(string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function addError(string $field, string $rule, array $parameters): void
    {
        $message = $this->getErrorMessage($field, $rule, $parameters);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    private function getErrorMessage(string $field, string $rule, array $parameters): string
    {
        $key = "{$field}.{$rule}";
        
        if (isset($this->messages[$key])) {
            return $this->interpolateMessage($this->messages[$key], $field, $parameters);
        }
        
        if (isset($this->messages[$rule])) {
            return $this->interpolateMessage($this->messages[$rule], $field, $parameters);
        }
        
        return $this->getDefaultMessage($field, $rule, $parameters);
    }

    private function interpolateMessage(string $message, string $field, array $parameters): string
    {
        $message = str_replace(':attribute', $field, $message);
        
        foreach ($parameters as $index => $parameter) {
            $message = str_replace(':' . $index, $parameter, $message);
        }
        
        return $message;
    }

    private function getDefaultMessage(string $field, string $rule, array $parameters): string
    {
        $messages = [
            'required' => "The {$field} field is required.",
            'email' => "The {$field} must be a valid email address.",
            'min' => "The {$field} must be at least {$parameters[0]} characters.",
            'max' => "The {$field} may not be greater than {$parameters[0]} characters.",
            'numeric' => "The {$field} must be a number.",
            'integer' => "The {$field} must be an integer.",
            'string' => "The {$field} must be a string.",
            'array' => "The {$field} must be an array.",
            'boolean' => "The {$field} field must be true or false.",
            'confirmed' => "The {$field} confirmation does not match.",
            'same' => "The {$field} and {$parameters[0]} must match.",
            'different' => "The {$field} and {$parameters[0]} must be different.",
            'in' => "The selected {$field} is invalid.",
            'not_in' => "The selected {$field} is invalid.",
            'unique' => "The {$field} has already been taken.",
            'exists' => "The selected {$field} is invalid.",
            'regex' => "The {$field} format is invalid.",
            'url' => "The {$field} must be a valid URL.",
            'date' => "The {$field} is not a valid date.",
            'before' => "The {$field} must be a date before {$parameters[0]}.",
            'after' => "The {$field} must be a date after {$parameters[0]}.",
            'alpha' => "The {$field} may only contain letters.",
            'alpha_num' => "The {$field} may only contain letters and numbers.",
            'alpha_dash' => "The {$field} may only contain letters, numbers, dashes and underscores.",
        ];

        return $messages[$rule] ?? "The {$field} field is invalid.";
    }

    // Validation rules
    private function validateRequired(string $field, mixed $value, array $parameters): bool
    {
        return !is_null($value) && $value !== '' && $value !== [];
    }

    private function validateEmail(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validateMin(string $field, mixed $value, array $parameters): bool
    {
        if (is_null($value)) {
            return true;
        }

        $min = (int) $parameters[0];

        if (is_string($value)) {
            return strlen($value) >= $min;
        }

        if (is_numeric($value)) {
            return $value >= $min;
        }

        if (is_array($value)) {
            return count($value) >= $min;
        }

        return false;
    }

    private function validateMax(string $field, mixed $value, array $parameters): bool
    {
        if (is_null($value)) {
            return true;
        }

        $max = (int) $parameters[0];

        if (is_string($value)) {
            return strlen($value) <= $max;
        }

        if (is_numeric($value)) {
            return $value <= $max;
        }

        if (is_array($value)) {
            return count($value) <= $max;
        }

        return false;
    }

    private function validateNumeric(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || is_numeric($value);
    }

    private function validateInteger(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    private function validateString(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || is_string($value);
    }

    private function validateArray(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || is_array($value);
    }

    private function validateBoolean(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true);
    }

    private function validateConfirmed(string $field, mixed $value, array $parameters): bool
    {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = $this->getValue($confirmationField);
        
        return $value === $confirmationValue;
    }

    private function validateSame(string $field, mixed $value, array $parameters): bool
    {
        $otherValue = $this->getValue($parameters[0]);
        return $value === $otherValue;
    }

    private function validateDifferent(string $field, mixed $value, array $parameters): bool
    {
        $otherValue = $this->getValue($parameters[0]);
        return $value !== $otherValue;
    }

    private function validateIn(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || in_array($value, $parameters);
    }

    private function validateNotIn(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || !in_array($value, $parameters);
    }

    private function validateRegex(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || preg_match($parameters[0], $value) === 1;
    }

    private function validateUrl(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    private function validateDate(string $field, mixed $value, array $parameters): bool
    {
        if (is_null($value)) {
            return true;
        }

        return strtotime($value) !== false;
    }

    private function validateAlpha(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || ctype_alpha($value);
    }

    private function validateAlphaNum(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || ctype_alnum($value);
    }

    private function validateAlphaDash(string $field, mixed $value, array $parameters): bool
    {
        return is_null($value) || preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
    }

    public function extend(string $rule, callable $callback): void
    {
        $this->customRules[$rule] = $callback;
    }
}
