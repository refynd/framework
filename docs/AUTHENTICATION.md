# Refynd Authentication System

The Refynd authentication system provides a comprehensive, secure, and flexible solution for user authentication in PHP applications. It includes password hashing, user authentication, session management, and middleware protection.

## Features

- **Multiple Hash Algorithms**: Support for Bcrypt and Argon2ID
- **Database Integration**: Seamless integration with Refynd ORM
- **Session Management**: Built-in session handling with "remember me" functionality
- **Middleware Protection**: Route protection for authenticated and guest users
- **Flexible Architecture**: Interface-driven design for easy customization

## Quick Start

### 1. Register the Authentication Module

```php
use Refynd\Container\Container;
use Refynd\Modules\AuthModule;

$container = new Container();
$authModule = new AuthModule();

$authModule->register($container);
$authModule->boot();
```

### 2. Create a User Model

```php
use Refynd\Database\Model;
use Refynd\Auth\AuthenticatableInterface;
use Refynd\Auth\AuthenticatableTrait;

class User extends Model implements AuthenticatableInterface
{
    use AuthenticatableTrait;

    protected string $table = 'users';
    
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password', 'remember_token'];
}
```

### 3. Authenticate Users

```php
use Refynd\Auth\GuardInterface;

$guard = $container->make(GuardInterface::class);

// Attempt login
$success = $guard->attempt([
    'email' => 'user@example.com',
    'password' => 'secret123'
], true); // true = remember me

if ($success) {
    $user = $guard->user();
    echo "Welcome, " . $user->name;
}
```

## Hash Management

### Available Drivers

#### Bcrypt Hasher
- **Algorithm**: PASSWORD_BCRYPT
- **Configuration**: Configurable cost rounds (default: 12)
- **Usage**: Industry standard, well-tested

```php
use Refynd\Hash\HashManager;

$hashManager = $container->make(HashManager::class);
$bcryptHasher = $hashManager->driver('bcrypt');

$hash = $bcryptHasher->make('password');
$valid = $bcryptHasher->check('password', $hash);
```

#### Argon2ID Hasher
- **Algorithm**: PASSWORD_ARGON2ID
- **Configuration**: Memory cost, time cost, and thread count
- **Usage**: Modern, recommended for new applications

```php
$argonHasher = $hashManager->driver('argon');

$hash = $argonHasher->make('password');
$valid = $argonHasher->check('password', $hash);
```

### Hash Manager API

```php
// Make a hash with default driver
$hash = $hashManager->make('password');

// Check a hash with default driver
$valid = $hashManager->check('password', $hash);

// Use specific driver
$hash = $hashManager->driver('argon')->make('password');

// Check if rehashing is needed
if ($hashManager->needsRehash($hash)) {
    $newHash = $hashManager->make('password');
}
```

## Authentication Guards

### SessionGuard

The SessionGuard provides stateful authentication using PHP sessions.

```php
use Refynd\Auth\SessionGuard;
use Refynd\Auth\AuthManager;

$sessionGuard = new SessionGuard($authManager, $_SESSION);

// Check authentication
if ($sessionGuard->check()) {
    $user = $sessionGuard->user();
}

// Login user
$sessionGuard->login($user, $remember = true);

// Logout user
$sessionGuard->logout();
```

### Guard Interface Methods

```php
interface GuardInterface
{
    public function check(): bool;                              // Is authenticated?
    public function guest(): bool;                              // Is guest?
    public function user(): ?AuthenticatableInterface;         // Get current user
    public function id(): mixed;                                // Get user ID
    public function validate(array $credentials): bool;        // Validate credentials
    public function setUser(AuthenticatableInterface $user): void; // Set current user
}
```

### Stateful Guard Interface

```php
interface StatefulGuardInterface extends GuardInterface
{
    public function attempt(array $credentials, bool $remember = false): bool;
    public function once(array $credentials): bool;            // Login without persistence
    public function login(AuthenticatableInterface $user, bool $remember = false): void;
    public function logout(): void;
    public function viaRemember(): ?AuthenticatableInterface;  // Login via remember token
}
```

## User Providers

### DatabaseUserProvider

Retrieves users from the database using the Refynd ORM.

```php
use Refynd\Auth\DatabaseUserProvider;

$provider = new DatabaseUserProvider('App\\Models\\User', $hashManager);

// Retrieve user by ID
$user = $provider->retrieveById(1);

// Retrieve user by credentials
$user = $provider->retrieveByCredentials(['email' => 'user@example.com']);

// Validate credentials
$valid = $provider->validateCredentials($user, ['password' => 'secret']);

// Update remember token
$provider->updateRememberToken($user, 'new-token');
```

## Middleware

### AuthMiddleware

Protects routes that require authentication.

```php
use Refynd\Auth\Middleware\AuthMiddleware;

$authMiddleware = new AuthMiddleware($guard, '/login');

// In your route handler
$response = $authMiddleware->handle($request, function($request) {
    // Protected route logic
    return new Response('Protected content');
});
```

**Behavior:**
- Redirects unauthenticated users to login page
- Returns 401 JSON response for AJAX/API requests
- Passes authenticated users to the next middleware

### GuestMiddleware

Protects routes that should only be accessible to guests.

```php
use Refynd\Auth\Middleware\GuestMiddleware;

$guestMiddleware = new GuestMiddleware($guard, '/dashboard');

// Redirects authenticated users to dashboard
```

**Use Cases:**
- Login pages
- Registration pages
- Password reset pages

## User Model Requirements

To use a model with the authentication system, implement the `AuthenticatableInterface`:

```php
use Refynd\Auth\AuthenticatableInterface;
use Refynd\Auth\AuthenticatableTrait;

class User extends Model implements AuthenticatableInterface
{
    use AuthenticatableTrait;

    // Required methods (provided by trait):
    public function getAuthIdentifierName(): string;           // Column name for ID
    public function getAuthIdentifier(): mixed;                // Get ID value
    public function getAuthPassword(): string;                 // Get password
    public function getRememberToken(): ?string;               // Get remember token
    public function setRememberToken(string $value): void;     // Set remember token
    public function getRememberTokenName(): string;            // Token column name
}
```

## Database Schema

### Users Table Example

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Security Features

### Password Hashing
- Uses PHP's `password_hash()` and `password_verify()`
- Automatic salt generation
- Configurable cost factors
- Rehashing detection for security upgrades

### Session Security
- Secure session management
- Remember token functionality
- Session regeneration on login
- Proper logout cleanup

### Protection Features
- SQL injection protection through ORM
- Timing attack resistance
- Secure password comparison
- Token-based remember me functionality

## Advanced Usage

### Custom User Providers

```php
class CustomUserProvider implements UserProviderInterface
{
    public function retrieveById(mixed $identifier): ?AuthenticatableInterface
    {
        // Custom implementation
    }

    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
    {
        // Custom implementation
    }

    // ... other methods
}
```

### Custom Guards

```php
class ApiGuard implements GuardInterface
{
    public function check(): bool
    {
        // Check API token validity
    }

    // ... other methods
}
```

### Configuration

```php
// Custom hash configuration
$container->bind('hash.bcrypt', function () {
    return new BcryptHasher(15); // Higher cost for better security
});

// Custom user provider
$container->bind(UserProviderInterface::class, function (Container $container) {
    return new CustomUserProvider($container->make(HashManager::class));
});
```

## Best Practices

1. **Always hash passwords** before storing them
2. **Use HTTPS** in production for login forms
3. **Implement rate limiting** for login attempts
4. **Validate input** thoroughly
5. **Use CSRF protection** with forms
6. **Log security events** for monitoring
7. **Keep sessions secure** with proper configuration
8. **Regenerate tokens** regularly

## Integration with Refynd Framework

The authentication system integrates seamlessly with other Refynd components:

- **ORM**: User models extend Refynd's Model class
- **Container**: All services are registered with the DI container
- **Modules**: AuthModule handles all service registration
- **Middleware**: Integrates with HTTP middleware stack
- **Validation**: Works with Refynd's validation system

This provides a complete, production-ready authentication solution for Refynd applications.
