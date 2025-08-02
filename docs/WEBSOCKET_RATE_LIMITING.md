# WebSocket Rate Limiting

The Refynd Framework includes a comprehensive rate limiting system for WebSocket connections to prevent abuse and ensure fair resource usage.

## Features

- **Per-client rate limiting**: Each client connection is tracked independently
- **Configurable limits**: Set maximum requests per time window
- **Automatic blocking**: Clients exceeding limits are temporarily blocked
- **Graceful error handling**: Rate-limited clients receive informative error messages
- **Real-time statistics**: Monitor rate limiting effectiveness
- **Memory efficient**: Automatic cleanup of old client data

## Configuration

### Basic Usage

```php
use Refynd\WebSocket\RateLimiter;
use Refynd\WebSocket\WebSocketServer;

// Create rate limiter with default settings (60 requests per 60 seconds, 5-minute block)
$rateLimiter = new RateLimiter();

// Create rate limiter with custom settings
$rateLimiter = new RateLimiter(
    maxRequests: 100,    // Maximum requests per time window
    timeWindow: 60,      // Time window in seconds
    blockDuration: 300   // Block duration in seconds when limit exceeded
);

// Use with WebSocket server
$server = new WebSocketServer('127.0.0.1', 8080, $rateLimiter);
```

### Command Line Options

Start the WebSocket server with custom rate limiting:

```bash
# Default rate limiting (60 req/60s)
php refynd websocket:serve

# Custom rate limiting
php refynd websocket:serve --max-requests=100 --time-window=60 --block-duration=300

# Disable rate limiting
php refynd websocket:serve --disable-rate-limit
```

## Rate Limiting Parameters

| Parameter | Default | Description |
|-----------|---------|-------------|
| `maxRequests` | 60 | Maximum number of requests allowed per time window |
| `timeWindow` | 60 | Time window in seconds for request counting |
| `blockDuration` | 300 | Duration in seconds to block clients who exceed the limit |

## Client Responses

### Rate Limit Error

When a client exceeds the rate limit, they receive:

```json
{
    "type": "rate_limit_error",
    "message": "Rate limit exceeded",
    "remaining_requests": 0,
    "blocked_until": "2025-08-02T15:30:00+00:00"
}
```

### Status Responses

Successful operations include rate limiting information:

```json
{
    "type": "status",
    "action": "joined",
    "channel": "chat",
    "rate_limit": {
        "remaining_requests": 45
    }
}
```

## Management Commands

### View Statistics

```bash
php refynd websocket:rate-limit stats
```

Shows:
- Total tracked clients
- Active clients (with recent requests)
- Currently blocked clients
- Rate limiting configuration

### Reset Rate Limits

```bash
# Reset all clients
php refynd websocket:rate-limit reset

# Reset specific client
php refynd websocket:rate-limit reset --client="192.168.1.100:8080"
```

### Configuration Testing

```bash
# Test rate limiting with custom settings
php refynd websocket:rate-limit test --max-requests=10 --time-window=60

# View configuration
php refynd websocket:rate-limit config --max-requests=100
```

## Client Implementation

### JavaScript Example

```javascript
const ws = new WebSocket('ws://localhost:8080');

ws.onmessage = function(event) {
    const message = JSON.parse(event.data);
    
    if (message.type === 'rate_limit_error') {
        console.error('Rate limited:', message.message);
        console.log('Blocked until:', message.blocked_until);
        
        // Implement exponential backoff or user notification
        showRateLimitWarning(message);
        return;
    }
    
    if (message.rate_limit) {
        console.log('Remaining requests:', message.rate_limit.remaining_requests);
    }
    
    // Handle normal messages
    handleMessage(message);
};

function showRateLimitWarning(error) {
    // Show user-friendly message
    alert('You are sending messages too quickly. Please slow down.');
}
```

### Rate Limit Aware Client

```javascript
class RateLimitedWebSocket {
    constructor(url) {
        this.ws = new WebSocket(url);
        this.remainingRequests = Infinity;
        this.setupEventHandlers();
    }
    
    setupEventHandlers() {
        this.ws.onmessage = (event) => {
            const message = JSON.parse(event.data);
            
            if (message.type === 'rate_limit_error') {
                this.handleRateLimit(message);
                return;
            }
            
            if (message.rate_limit) {
                this.remainingRequests = message.rate_limit.remaining_requests;
                this.updateUI();
            }
            
            this.onMessage(message);
        };
    }
    
    send(data) {
        if (this.remainingRequests <= 0) {
            console.warn('Rate limit reached, message queued');
            // Implement message queuing or user notification
            return false;
        }
        
        this.ws.send(JSON.stringify(data));
        return true;
    }
    
    handleRateLimit(error) {
        console.error('Rate limited:', error);
        // Implement user feedback
        this.showRateLimitNotification(error);
    }
    
    updateUI() {
        // Update UI to show remaining requests
        document.getElementById('rate-limit-counter').textContent = 
            `${this.remainingRequests} requests remaining`;
    }
}
```

## Monitoring

### Real-time Statistics

The WebSocket server provides built-in statistics via the `stats` message type:

```javascript
// Request server statistics
ws.send(JSON.stringify({ type: 'stats' }));

// Response includes rate limiter statistics
{
    "type": "stats",
    "server": {
        "connected_clients": 25,
        "channels": 5
    },
    "rate_limiter": {
        "total_clients": 25,
        "active_clients": 18,
        "blocked_clients": 2,
        "max_requests": 60,
        "time_window": 60,
        "block_duration": 300
    }
}
```

### Server-side Monitoring

```php
// Get rate limiter statistics
$stats = $server->getRateLimiterStats();

// Reset specific client
$server->resetRateLimit($specificClient);

// Reset all clients
$server->resetRateLimit();
```

## Best Practices

### For Applications

1. **Implement client-side throttling** to prevent hitting rate limits
2. **Show user feedback** when approaching limits
3. **Queue messages** when rate limited instead of dropping them
4. **Use exponential backoff** for reconnection attempts

### For Server Configuration

1. **Set reasonable limits** based on expected usage patterns
2. **Monitor statistics** to adjust limits as needed
3. **Consider burst allowances** for legitimate high-activity periods
4. **Implement gradual penalties** rather than hard blocks for first-time offenders

### Security Considerations

1. **Use IP-based identification** for better client tracking
2. **Implement connection limits** per IP address
3. **Log rate limiting events** for security monitoring
4. **Consider DDoS protection** at the network level

## Troubleshooting

### Common Issues

**High Block Rate**: If many clients are being blocked, consider:
- Increasing the rate limit
- Checking for legitimate high-traffic scenarios
- Investigating potential abuse

**Memory Usage**: For high-traffic servers:
- The rate limiter automatically cleans up old data
- Manual cleanup can be triggered more frequently if needed

**False Positives**: If legitimate users are being blocked:
- Review rate limiting parameters
- Consider implementing user authentication for higher limits
- Implement appeals or manual override mechanisms

### Debugging

Enable verbose logging to monitor rate limiting behavior:

```php
// In development, log rate limiting events
$rateLimiter = new RateLimiter(60, 60, 300);

// Custom logging can be added by extending the RateLimiter class
```
