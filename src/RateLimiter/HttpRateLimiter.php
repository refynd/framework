<?php

namespace Refynd\RateLimiter;

use Refynd\Cache\CacheInterface;

class HttpRateLimiter extends RateLimiter
{
    public function __construct(?CacheInterface $cache = null)
    {
        parent::__construct($cache, 'http');
    }

    /**
     * Get rate limit key for HTTP request.
     */
    public function getRequestKey(string $ip, string $route, ?string $userId = null): string
    {
        $parts = [$ip, $route];
        
        if ($userId) {
            $parts[] = "user:{$userId}";
        }
        
        return implode(':', $parts);
    }

    /**
     * Check HTTP request rate limit.
     */
    public function checkRequest(string $ip, string $route, int $maxAttempts, int $decayMinutes = 1, ?string $userId = null): void
    {
        $key = $this->getRequestKey($ip, $route, $userId);
        $decaySeconds = $decayMinutes * 60;
        
        if ($this->tooManyAttempts($key, $maxAttempts)) {
            $limitInfo = $this->getLimitInfo($key, $maxAttempts, $decaySeconds);
            throw new RateLimitExceededException(
                'HTTP rate limit exceeded',
                $this->availableIn($key),
                $limitInfo
            );
        }

        $this->hit($key, $decaySeconds);
    }

    /**
     * Get rate limit headers for HTTP response.
     */
    public function getHeaders(string $key, int $maxAttempts, int $decaySeconds = 60): array
    {
        $limitInfo = $this->getLimitInfo($key, $maxAttempts, $decaySeconds);
        
        return [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $limitInfo['remaining']),
            'X-RateLimit-Reset' => $limitInfo['reset_time'],
            'Retry-After' => $limitInfo['available_in'] > 0 ? $limitInfo['available_in'] : null,
        ];
    }
}
