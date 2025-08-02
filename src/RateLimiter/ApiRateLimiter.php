<?php

namespace Refynd\RateLimiter;

use Refynd\Cache\CacheInterface;

class ApiRateLimiter extends RateLimiter
{
    public function __construct(?CacheInterface $cache = null)
    {
        parent::__construct($cache, 'api');
    }

    /**
     * Check API key rate limit.
     */
    public function checkApiKey(string $apiKey, int $maxRequests, int $decayMinutes = 60): void
    {
        $key = "api_key:{$apiKey}";
        $decaySeconds = $decayMinutes * 60;
        
        if ($this->tooManyAttempts($key, $maxRequests)) {
            $limitInfo = $this->getLimitInfo($key, $maxRequests, $decaySeconds);
            throw new RateLimitExceededException(
                'API rate limit exceeded',
                $this->availableIn($key),
                $limitInfo
            );
        }

        $this->hit($key, $decaySeconds);
    }

    /**
     * Check endpoint-specific rate limit.
     */
    public function checkEndpoint(string $endpoint, string $identifier, int $maxRequests, int $decayMinutes = 1): void
    {
        $key = "endpoint:{$endpoint}:{$identifier}";
        $decaySeconds = $decayMinutes * 60;
        
        if ($this->tooManyAttempts($key, $maxRequests)) {
            $limitInfo = $this->getLimitInfo($key, $maxRequests, $decaySeconds);
            throw new RateLimitExceededException(
                'Endpoint rate limit exceeded',
                $this->availableIn($key),
                $limitInfo
            );
        }

        $this->hit($key, $decaySeconds);
    }

    /**
     * Get API usage statistics.
     */
    public function getApiUsage(string $apiKey, int $maxRequests, int $decayMinutes = 60): array
    {
        $key = "api_key:{$apiKey}";
        $decaySeconds = $decayMinutes * 60;
        
        return $this->getLimitInfo($key, $maxRequests, $decaySeconds);
    }
}
