# Refynd

<p align="center">
<img src="https://img.shields.io/badge/PHP-8.2%2B-blue" alt="PHP Version">
<img src="https://img.shields.io/badge/License-MIT-green" alt="License">
<img src="https://img.shields.io/github/v/tag/refynd/framework?label=Version" alt="Latest Version">
<img src="https://img.shields.io/packagist/v/refynd/framework?label=Packagist" alt="Packagist Version">
<img src="https://img.shields.io/packagist/dt/refynd/framework" alt="Total Downloads">
</p>

> **"In the forge of code, we craft not just software, but experiences."**

Refynd is a modern PHP platform that combines **enterprise-grade power** with **elegant simplicity**. Built for developers who refuse to compromise between functionality and beauty, Refynd provides everything you need to craft exceptional web applications.

**GitHub Repository**: https://github.com/refynd/framework

## ✨ The Philosophy

**Every line of code tells a story.** Refynd believes that great software emerges when powerful architecture meets intuitive design. We've created a platform that feels natural to use while providing the robust foundation your applications deserve.

**Refynd doesn't just work—it flows.**

## 🚀 Installation

Install Refynd via Composer:

```bash
composer require refynd/framework
```

> **Note:** This is the core package. To create new applications, use the [Refynd application skeleton](https://github.com/refynd/refynd).

## 🎯 Core Features

### Core Foundation
- **Engine** - Orchestrates your application's lifecycle with precision
- **Container** - Advanced dependency injection with automatic resolution
- **Modules** - Self-contained packages that promote clean architecture

### HTTP Excellence  
- **Routing** - Expressive route definitions with middleware support
- **Validation** - Fluent, readable validation with custom rules
- **Middleware** - Request/response filtering for cross-cutting concerns

### Data & Persistence
- **Ledger ORM** - Intuitive Active Record pattern for database interactions
- **Query Builder** - Fluent interface for complex database operations
- **Multi-Database** - Support for MySQL, PostgreSQL, and SQLite

### Performance & Scale
- **Caching** - Multi-driver caching with Redis, Memcached, and file support
- **Events** - Decoupled communication with attribute-based listeners
- **Templates** - Prism engine with elegant syntax and automatic compilation

## 🏗️ Framework Usage

### Bootstrapping Your Application

```php
<?php
// public/index.php

use Refynd\Bootstrap\Engine;
use YourApp\Bootstrap\AppProfile;

require_once '../vendor/autoload.php';

$engine = new Engine(new AppProfile());
$response = $engine->runHttp();
$response->send();
```

### Using the Container

```php
use Refynd\Container\Container;

$container = new Container();

// Automatic dependency injection
class UserService
{
    public function __construct(
        private UserRepository $users,
        private EmailService $email
    ) {}
}

$service = $container->make(UserService::class);
```

### Database Operations

```php
use Refynd\Database\Record;

class User extends Record
{
    protected string $table = 'users';
    
    public static function findByEmail(string $email): ?self
    {
        return static::query()
            ->where('email', $email)
            ->first();
    }
}
```

### Caching

```php
use Refynd\Cache\Cache;

// Simple caching
$posts = Cache::remember('recent_posts', 3600, function() {
    return Post::recent()->limit(10)->get();
});

// Multiple cache stores
Cache::store('redis')->put('session', $data, 7200);
```

### Events

```php
use Refynd\Events\Event;
use Refynd\Events\Listener;

class UserRegistered
{
    public function __construct(public User $user) {}
}

class WelcomeHandler
{
    #[Listener(UserRegistered::class)]
    public function sendWelcomeEmail(UserRegistered $event): void
    {
        // Send welcome email
    }
}

Event::fire(new UserRegistered($user));
```

## 🏢 Built for the Real World

Refynd powers applications that matter:

- **🌐 Web Applications** - From simple sites to complex platforms
- **🔌 REST APIs** - Scalable backends for mobile and SPA applications  
- **⚡ Microservices** - Event-driven architectures that scale
- **🏢 Enterprise Systems** - Business applications with complex workflows
- **📱 Modern Platforms** - Content management, e-commerce, forums

## 🔧 Requirements

- **PHP 8.2+** - Modern PHP with all the latest features
- **Composer** - For dependency management
- **Extensions** (optional):
  - `ext-redis` - For Redis cache driver
  - `ext-memcached` - For Memcached cache driver

## 📚 Documentation

- **[Core Capabilities](docs/CURRENT_CAPABILITIES.md)** - Complete component overview
- **[What You Can Build](docs/WHAT_YOU_CAN_BUILD.md)** - Application examples and patterns
- **[API Reference](https://github.com/refynd/framework/wiki)** - Detailed API documentation

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
# Clone the repository
git clone https://github.com/refynd/framework.git
cd refynd

# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse
```

## 📊 Testing

```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run static analysis
composer analyse

# Run all checks
composer check
```

## 📜 License

Refynd is open-source software licensed under the [MIT license](LICENSE).

## 🌟 Ecosystem

- **[refynd/refynd](https://github.com/refynd/refynd)** - Application skeleton for creating new projects
- **[refynd/cli](https://github.com/refynd/cli)** - Global CLI installer and project management

---

<p align="center">
<strong>Ready to forge something extraordinary?</strong><br>
<em>Your next great application starts with Refynd.</em>
</p>

<p align="center">
🔥 <strong>Start Building Today</strong> 🔥
</p>
