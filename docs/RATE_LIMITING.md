# Framework Rate Limiting

The Refynd Framework includes a comprehensive, framework-wide rate limiting system that can be used across all components including HTTP requests, WebSocket connections, API endpoints, and any custom functionality.

## Architecture

The rate limiting system is organized in the `src/RateLimiter/` directory with the following structure:

```
src/RateLimiter/
├── RateLimiterInterface.php    # Interface defining rate limiter contract
├── RateLimiter.php             # Base rate limiter implementation
├── RateLimitExceededException.php # Exception thrown when limits are exceeded
├── WebSocketRateLimiter.php    # WebSocket-specific rate limiter
├── HttpRateLimiter.php         # HTTP request rate limiter
└── ApiRateLimiter.php          # API endpoint rate limiter
```

## Features

- **Framework-wide**: Use across HTTP, WebSocket, API, and custom components
- **Cache-backed**: Supports all cache drivers (Array, File, Redis, Memcached)
- **Flexible limits**: Configurable per component, endpoint, or user
- **Exception-based**: Clean error handling with detailed limit information
- **Container integration**: Dependency injection support
- **Console management**: CLI tools for monitoring and management

## Basic Usage

### HTTP Rate Limiting

```php
use Refynd\RateLimiter\HttpRateLimiter;
use Refynd\RateLimiter\RateLimitExceededException;

$rateLimiter = new HttpRateLimiter($cache);

try {
    // Check rate limit for specific request
    $rateLimiter->checkRequest(
        $ipAddress, 
        $route, 
        $maxRequests = 60, 
        $decayMinutes = 1,
        $userId = null
    );
    
    // Process request...
    
} catch (RateLimitExceededException $e) {
    // Handle rate limit exceeded
    $headers = [
        'Retry-After' => $e->getRetryAfter(),
        'X-RateLimit-Remaining' => $e->getRemainingAttempts(),
    ];
    
    return new Response('Rate limit exceeded', 429, $headers);
}
```

### WebSocket Rate Limiting

```php
use Refynd\RateLimiter\WebSocketRateLimiter;
use Refynd\RateLimiter\RateLimitExceededException;

$rateLimiter = new WebSocketRateLimiter($cache, $maxRequests = 60, $decaySeconds = 60);

try {
    // Check client before processing message
    $rateLimiter->checkClient($client);
    
    // Process WebSocket message...
    
} catch (RateLimitExceededException $e) {
    // Send rate limit error to client
    $error = [
        'type' => 'rate_limit_error',
        'message' => $e->getMessage(),
        'retry_after' => $e->getRetryAfter(),
        'limit_info' => $e->getLimitInfo()
    ];
    
    $this->sendToClient($client, json_encode($error));
}
```

### API Rate Limiting

```php
use Refynd\RateLimiter\ApiRateLimiter;
use Refynd\RateLimiter\RateLimitExceededException;

$rateLimiter = new ApiRateLimiter($cache);

try {
    // Check API key limits
    $rateLimiter->checkApiKey($apiKey, $maxRequests = 1000, $decayMinutes = 60);
    
    // Check endpoint-specific limits
    $rateLimiter->checkEndpoint($endpoint, $identifier, $maxRequests = 100, $decayMinutes = 1);
    
    // Process API request...
    
} catch (RateLimitExceededException $e) {
    return [
        'error' => 'rate_limit_exceeded',
        'message' => $e->getMessage(),
        'limit_info' => $e->getLimitInfo()
    ];
}
```

### Generic Rate Limiting

```php
use Refynd\RateLimiter\RateLimiter;
use Refynd\RateLimiter\RateLimitExceededException;

$rateLimiter = RateLimiter::for('custom_feature', $cache);

try {
    // Use attempt() for automatic rate limiting
    $result = $rateLimiter->attempt(
        $key, 
        $maxAttempts = 10, 
        function() {
            // Your protected operation
            return doSomething();
        },
        $decaySeconds = 60
    );
    
} catch (RateLimitExceededException $e) {
    // Handle rate limit
    echo "Rate limited! Try again in " . $e->getRetryAfter() . " seconds";
}

// Or check manually
if ($rateLimiter->tooManyAttempts($key, $maxAttempts)) {
    $limitInfo = $rateLimiter->getLimitInfo($key, $maxAttempts, $decaySeconds);
    // Handle rate limit
} else {
    $rateLimiter->hit($key, $decaySeconds);
    // Process normally
}
```

## HTTP Middleware

Use the built-in HTTP middleware for automatic rate limiting:

```php
use Refynd\Http\Middleware\ThrottleMiddleware;

// In your route definition
$router->get('/api/endpoint', function() {
    // Your handler
})->middleware([
    ThrottleMiddleware::perMinute(60),  // 60 requests per minute
    // or ThrottleMiddleware::perHour(1000),
    // or ThrottleMiddleware::perDay(10000),
]);

// Manual middleware configuration
$router->get('/api/limited', function() {
    // Your handler
})->middleware([
    'throttle:100,5'  // 100 requests per 5 minutes
]);
```

## Console Commands

### Manage Rate Limits

```bash
# View statistics for different components
php refynd rate-limit websocket stats
php refynd rate-limit http stats
php refynd rate-limit api stats

# Reset rate limits for specific keys
php refynd rate-limit websocket reset --key="192.168.1.100:8080"
php refynd rate-limit http reset --key="user:123"
php refynd rate-limit api reset --key="api_key:abc123"

# Test rate limiting
php refynd rate-limit websocket test --max-requests=10 --time-window=60
php refynd rate-limit http test --max-requests=100 --time-window=3600
```

### WebSocket Server with Rate Limiting

```bash
# Start WebSocket server with default rate limiting
php refynd websocket:serve

# Custom rate limiting
php refynd websocket:serve --max-requests=100 --time-window=60

# Disable rate limiting
php refynd websocket:serve --disable-rate-limit
```

## Container Integration

Register rate limiters in your application container:

```php
use Refynd\Modules\RateLimiterModule;

// The RateLimiterModule automatically registers:
// - RateLimiter::class
// - WebSocketRateLimiter::class  
// - HttpRateLimiter::class
// - ApiRateLimiter::class

$container->register(new RateLimiterModule());

// Use in your classes
class MyController
{
    public function __construct(
        private HttpRateLimiter $rateLimiter
    ) {}
    
    public function handleRequest($request)
    {
        $this->rateLimiter->checkRequest(
            $request->ip(),
            $request->route(),
            60, // max requests
            1   // per minute
        );
        
        // Handle request...
    }
}
```

## Configuration

### Cache Backends

Rate limiters work with any cache backend:

```php
use Refynd\Cache\RedisStore;
use Refynd\RateLimiter\RateLimiter;

// Redis for distributed rate limiting
$cache = new RedisStore(['host' => 'redis-server']);
$rateLimiter = new RateLimiter($cache, 'my_app');

// File cache for single-server setups
$cache = new FileStore(['path' => '/tmp/rate_limits']);
$rateLimiter = new RateLimiter($cache, 'my_app');

// Array cache for development/testing
$cache = new ArrayStore();
$rateLimiter = new RateLimiter($cache, 'my_app');
```

### Component-Specific Rate Limiters

```php
// Create rate limiters for specific features
$authRateLimit = RateLimiter::for('authentication', $cache);
$uploadRateLimit = RateLimiter::for('file_uploads', $cache);
$emailRateLimit = RateLimiter::for('email_sending', $cache);

// Each has its own namespace and limits
$authRateLimit->attempt('user:123', 5, function() {
    // Login attempt
}, 300); // 5 attempts per 5 minutes

$uploadRateLimit->attempt('user:123', 10, function() {
    // File upload
}, 3600); // 10 uploads per hour
```

## Rate Limit Information

Get detailed information about rate limit status:

```php
$limitInfo = $rateLimiter->getLimitInfo($key, $maxAttempts, $decaySeconds);

// Returns:
[
    'key' => 'hashed_key',
    'attempts' => 15,
    'max_attempts' => 60,
    'remaining' => 45,
    'available_in' => 0,
    'available_at' => null,
    'is_limited' => false,
    'decay_seconds' => 60,
    'reset_time' => 1691234567,
]
```

## Error Handling

The `RateLimitExceededException` provides comprehensive information:

```php
try {
    $rateLimiter->attempt($key, $maxAttempts, $callback, $decaySeconds);
} catch (RateLimitExceededException $e) {
    $retryAfter = $e->getRetryAfter();          // Seconds until allowed
    $remaining = $e->getRemainingAttempts();     // Attempts left
    $maxAttempts = $e->getMaxAttempts();         // Maximum attempts
    $limitInfo = $e->getLimitInfo();             // Full limit information
    
    // Build appropriate response
    return new JsonResponse([
        'error' => 'rate_limit_exceeded',
        'message' => $e->getMessage(),
        'retry_after' => $retryAfter,
        'limit_info' => $limitInfo
    ], 429);
}
```

## Best Practices

### 1. Choose Appropriate Limits

```php
// Authentication: Strict limits to prevent brute force
$authRateLimit = RateLimiter::for('auth');
$authRateLimit->attempt($ip, 5, $loginCallback, 300); // 5 attempts per 5 minutes

// API: Generous limits for normal usage
$apiRateLimit = RateLimiter::for('api');
$apiRateLimit->attempt($apiKey, 1000, $apiCallback, 3600); // 1000 per hour

// WebSocket: Prevent spam but allow normal chat
$wsRateLimit = new WebSocketRateLimiter(null, 60, 60); // 60 messages per minute
```

### 2. Use Appropriate Keys

```php
// For user-specific limits
$key = "user:{$userId}";

// For IP-based limits  
$key = "ip:{$ipAddress}";

// For API key limits
$key = "api_key:{$apiKey}";

// For feature-specific limits per user
$key = "user:{$userId}:feature:{$feature}";

// For endpoint-specific limits
$key = "endpoint:{$endpoint}:user:{$userId}";
```

### 3. Graceful Degradation

```php
// Use attemptOrNull for non-critical features
$result = $rateLimiter->attemptOrNull($key, $maxAttempts, function() {
    return $this->expensiveOperation();
}, $decaySeconds);

if ($result === null) {
    // Rate limited - return cached result or simplified response
    return $this->getCachedResult();
}

return $result;
```

### 4. User Feedback

```php
// Provide clear feedback to users
if ($rateLimiter->tooManyAttempts($key, $maxAttempts)) {
    $availableIn = $rateLimiter->availableIn($key);
    
    throw new RateLimitExceededException(
        "Too many requests. Please try again in {$availableIn} seconds.",
        $availableIn
    );
}
```

## Security Considerations

1. **Use secure key generation** to prevent key prediction
2. **Implement multiple rate limit layers** (per-IP, per-user, per-endpoint)
3. **Log rate limit violations** for security monitoring
4. **Use distributed cache** (Redis) for multi-server deployments
5. **Implement exponential backoff** for repeated violations
6. **Consider burst allowances** for legitimate high-activity periods

## Performance

- Rate limiters use efficient cache operations (increment, expire)
- Keys are hashed to ensure consistent length and prevent collisions
- Automatic cleanup prevents memory leaks
- Minimal overhead when limits are not exceeded
- Supports high-throughput applications with proper cache backend

The framework-wide rate limiting system provides robust protection against abuse while maintaining excellent performance and flexibility for legitimate usage patterns.
