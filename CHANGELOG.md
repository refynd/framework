# Changelog

All notable changes to the Refynd Framework will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2024-12-19

### Added
- **WebSocket Support**: Complete WebSocket server and client implementation with real-time communication capabilities
  - WebSocket server with connection management and channel support
  - Rate limiting integration for WebSocket connections
  - Broadcasting and channel management features
  - Console command for starting WebSocket server (`websocket:serve`)

- **Queue System**: Background job processing system for asynchronous tasks
  - Database-driven queue implementation
  - Job interface and queued job wrapper
  - Queue worker with graceful shutdown handling
  - Console commands for queue management (`queue:work`, `queue:listen`)

- **Storage Abstraction**: Multi-driver file storage system
  - Local file storage driver
  - Cloud storage interface for future implementations (S3, Google Cloud, etc.)
  - File operations (put, get, delete, exists, size, lastModified)
  - Storage module integration

- **Framework-wide Rate Limiting**: Comprehensive rate limiting system
  - Base `RateLimiter` class with cache backend support
  - Specialized `WebSocketRateLimiter` for real-time connections
  - `HttpRateLimiter` and `ApiRateLimiter` for web requests
  - Rate limiting middleware (`ThrottleMiddleware`)
  - Console commands for rate limit management
  - Rate limit exception handling with detailed limit information

- **Enhanced Modules System**:
  - `WebSocketModule` for WebSocket functionality
  - `QueueModule` for background job processing
  - `StorageModule` for file storage operations
  - `RateLimiterModule` for rate limiting features

### Improved
- **Type Safety**: Added comprehensive type hints across all new components
- **Error Handling**: Enhanced exception handling with specific rate limit exceptions
- **Documentation**: Updated documentation with new features and capabilities
- **Performance**: Optimized cache usage in rate limiting system

### Fixed
- **PHP 8.4 Compatibility**: Fixed nullable parameter deprecation warnings
- **Static Analysis**: Resolved PHPStan type safety issues in rate limiter components
- **Code Quality**: Improved type declarations and method signatures
- **Test Stability**: Fixed performance benchmark timing issues for reliable CI/CD

### Technical Details
- **PHP Requirements**: PHP 8.4+ with ext-sockets and ext-pcntl extensions
- **Cache Integration**: Rate limiting leverages existing cache system for performance
- **Modular Architecture**: All new features implemented as optional modules
- **Backward Compatibility**: All existing functionality remains unchanged

### Developer Experience
- **Console Commands**: Added multiple new commands for managing WebSockets, queues, and rate limits
- **Configuration**: Environment-based configuration for all new features
- **Testing**: Comprehensive test coverage for new components
- **Static Analysis**: Level 6 PHPStan compliance across all new code

## [2.0.0] - 2024-12-19

### Added
- Initial framework architecture with modern PHP 8.4+ features
- Core container and dependency injection system
- Database ORM with relationships and migrations
- HTTP routing and middleware system
- Authentication and authorization
- Caching system with multiple drivers
- Event system for decoupled components
- Validation system for data integrity
- Bootstrap engine for application initialization
- Console kernel for command-line operations

### Framework Foundation
- PSR-4 autoloading and modern PHP practices
- Modular architecture for extensibility
- Configuration management system
- Service provider pattern implementation
