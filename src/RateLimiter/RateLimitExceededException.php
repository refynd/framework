<?php

namespace Refynd\RateLimiter;

use Exception;

class RateLimitExceededException extends Exception
{
    private int $retryAfter;
    private array $limitInfo;

    public function __construct(string $message = '', int $retryAfter = 0, array $limitInfo = [], int $code = 429, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
        $this->limitInfo = $limitInfo;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getRetryAfterHeader(): string
    {
        return (string) $this->retryAfter;
    }

    public function getLimitInfo(): array
    {
        return $this->limitInfo;
    }

    public function getRemainingAttempts(): int
    {
        return $this->limitInfo['remaining'] ?? 0;
    }

    public function getMaxAttempts(): int
    {
        return $this->limitInfo['max_attempts'] ?? 0;
    }
}
