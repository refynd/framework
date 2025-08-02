<?php

namespace Refynd\Http\Middleware;

use Refynd\RateLimiter\HttpRateLimiter;
use Refynd\RateLimiter\RateLimitExceededException;
use Refynd\Container\Container;

class ThrottleMiddleware
{
    private HttpRateLimiter $rateLimiter;
    private Container $container; // @phpstan-ignore-line Container reserved for future middleware enhancement

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->rateLimiter = $container->get(HttpRateLimiter::class);
    }

    /**
     * Handle an incoming request.
     *
     * @param mixed $request
     * @param callable $next
     * @param int $maxAttempts
     * @param int $decayMinutes
     * @param string|null $prefix
     * @return mixed
     */
    public function handle($request, callable $next, int $maxAttempts = 60, int $decayMinutes = 1, ?string $prefix = null)
    {
        $ip = $this->getClientIp($request);
        $route = $this->getRoute($request);
        $userId = $this->getUserId($request);

        try {
            $this->rateLimiter->checkRequest($ip, $route, $maxAttempts, $decayMinutes, $userId);
        } catch (RateLimitExceededException $e) {
            return $this->buildTooManyAttemptsResponse($e);
        }

        $response = $next($request);

        $key = $this->rateLimiter->getRequestKey($ip, $route, $userId);
        $headers = $this->rateLimiter->getHeaders($key, $maxAttempts, $decayMinutes * 60);

        return $this->addHeaders($response, $headers);
    }

    /**
     * Get the client IP address.
     */
    protected function getClientIp(object $request): string
    {
        // Try to get real IP from common proxy headers
        $headers = ['HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'];            // Standard];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (X-Forwarded-For)
                if (str_contains($ip, ', ')) {
                    $ip = trim(explode(', ', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get the route identifier.
     */
    protected function getRoute(object $request): string
    {
        // This would be implementation-specific
        // For now, use the request URI
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get the user ID if authenticated.
     */
    protected function getUserId(object $request): ?string
    {
        // This would be implementation-specific
        // For now, return null (anonymous requests)
        return null;
    }

    /**
     * Build the "too many attempts" response.
     */
    protected function buildTooManyAttemptsResponse(RateLimitExceededException $e): array
    {
        $limitInfo = $e->getLimitInfo();

        return ['status' => 429,
            'headers' => ['Retry-After' => $e->getRetryAfter(),
                'X-RateLimit-Limit' => $limitInfo['max_attempts'],
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => $limitInfo['reset_time'],],
            'body' => ['error' => 'Too Many Attempts',
                'message' => $e->getMessage(),
                'retry_after' => $e->getRetryAfter(),
                'limit_info' => $limitInfo,]];
    }

    /**
     * Add rate limiting headers to the response.
     */
    protected function addHeaders(mixed $response, array $headers): mixed
    {
        // This would be implementation-specific based on your response format
        if (is_array($response)) {
            $response['rate_limit_headers'] = array_filter($headers);
        }

        return $response;
    }

    /**
     * Create a middleware instance with specific parameters.
     */
    public static function perMinute(int $maxAttempts): string
    {
        return static::class . ':' . $maxAttempts . ', 1';
    }

    /**
     * Create a middleware instance with specific parameters.
     */
    public static function perHour(int $maxAttempts): string
    {
        return static::class . ':' . $maxAttempts . ', 60';
    }

    /**
     * Create a middleware instance with specific parameters.
     */
    public static function perDay(int $maxAttempts): string
    {
        return static::class . ':' . $maxAttempts . ', 1440';
    }
}
