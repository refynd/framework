# Refynd

<p align="center">
<img src="https://img.shields.io/badge/PHP-8.4%2B-blue" alt="PHP Version">
<img src="https://img.shields.io/badge/License-MIT-green" alt="License">
<img src="https://img.shields.io/github/v/tag/refynd/framework?label=Version" alt="Latest Version">
<img src="https://img.shields.io/packagist/v/refynd/framework?label=Packagist" alt="Packagist Version">
<img src="https://img.shields.io/packagist/dt/refynd/framework" alt="Total Downloads">
</p>

> **"In the forge of code, we craft not just software, but experiences."**

Refynd is a **modern PHP framework** that combines **enterprise-grade power** with **elegant simplicity**. Built for developers who refuse to compromise between functionality and beauty, Refynd provides everything you need to craft exceptional web applications with **advanced templating**, **complete authentication**, and **blazing performance**.

**🎉 NEW in v2.1.0**: WebSocket support, Queue system, Storage abstraction, and comprehensive Rate limiting!
**✨ Enhanced in v2.0.0**: Enterprise-grade Prism template engine with inheritance, complete authentication system, and advanced features!

**GitHub Repository**: https://github.com/refynd/framework

## ✨ The Philosophy

**Every line of code tells a story.** Refynd believes that great software emerges when powerful architecture meets intuitive design. We've created a platform that feels natural to use while providing the robust foundation your applications deserve.

**Refynd doesn't just work—it flows.**

## 🚀 Quick Start

Install Refynd via Composer:

```bash
composer create-project refynd/framework my-app
cd my-app
php -S localhost:8000 -t public
```

### CLI Tool (Optional but Recommended)

For the best development experience, install the Refynd CLI tool globally:

```bash
composer global require refynd/cli
```

> **Requirements:** Refynd CLI requires Refynd Framework ^2.0 for full compatibility with the latest features.

The CLI provides powerful commands for:
- **Project creation**: `refynd new my-app`
- **Code generation**: `refynd make:controller UserController`
- **Development server**: `refynd serve`
- **Enhanced testing**: `refynd test --coverage`

> **Note:** This is the core package. To create new applications, use the [Refynd application skeleton](https://github.com/refynd/refynd).

## 🎯 Enterprise Features

### 🌐 **Real-time WebSocket Support** (NEW in v2.1.0)
- **WebSocket Server** - High-performance socket server with connection management
- **Channel Broadcasting** - Organize real-time communications with channels
- **Rate Limiting Integration** - Protect WebSocket connections from abuse
- **Console Commands** - Easy server management with `websocket:serve`

### 🚀 **Background Queue System** (NEW in v2.1.0)
- **Database Queue Driver** - Persistent job storage with retry mechanisms
- **Job Processing** - Queue jobs for asynchronous background processing
- **Worker Management** - Robust queue workers with graceful shutdown
- **Console Commands** - Manage queues with `queue:work` and `queue:listen`

### 📁 **Multi-driver Storage System** (NEW in v2.1.0)
- **Local Storage** - File operations with local disk driver
- **Cloud-ready Interface** - Extensible design for S3, Google Cloud, and more
- **File Operations** - Complete CRUD operations (put, get, delete, exists, size)
- **Storage Module** - Integrated into the framework's module system

### ⚡ **Comprehensive Rate Limiting** (NEW in v2.1.0)
- **Framework-wide Protection** - Rate limiting for HTTP, API, and WebSocket traffic
- **Multiple Strategies** - Per-user, per-IP, and custom rate limiting rules
- **Cache-backed** - High-performance with Redis/Memcached support
- **Rate Limit Middleware** - Easy integration with `ThrottleMiddleware`
- **Management Commands** - Monitor and reset limits via console

### 🔥 **Enhanced Prism Template Engine**
- **Template Inheritance** - Build complex layouts with `@extends`, `@section`, `@yield`
- **Component System** - Reusable template components with `@component`
- **20+ Custom Directives** - `@if`, `@foreach`, `@auth`, `@csrf`, `@switch`, and more
- **15+ Built-in Filters** - Transform data with `|upper`, `|currency`, `|date`, `|truncate`
- **XSS Protection** - Automatic output escaping with secure raw output option
- **Performance Tracking** - Debug mode with compilation and render timing

### 🔐 **Complete Authentication System**
- **Session-Based Auth** - Secure user session management with guards
- **Password Hashing** - Bcrypt and Argon2 support with configurable options
- **User Providers** - Database-backed authentication with flexible contracts
- **Middleware Protection** - Route-level authentication and guest-only access
- **AuthManager** - Centralized authentication configuration and management

### ⚡ **Performance & Scale**
- **Optimized Container** - Lightning-fast dependency injection resolution
- **Route Compilation** - Advanced route matching and compilation
- **Multi-Driver Caching** - Redis, Memcached, file, and array cache support
- **Performance Benchmarks** - Built-in tools to measure and optimize speed

### 🏗️ **Core Foundation**
- **Engine** - Orchestrates your application's lifecycle with precision
- **Container** - Advanced dependency injection with automatic resolution
- **Modules** - Self-contained packages that promote clean architecture

### 📊 **Data & Persistence**
- **Complete ORM** - Enterprise-grade with relationships, collections, and migrations
- **Query Builder** - Fluent interface for complex database operations  
- **Schema Management** - Migrations and blueprints for database versioning
- **Multi-Database** - Support for MySQL, PostgreSQL, and SQLite

### 🛡️ **Security & Validation**
- **Hash Management** - Secure password hashing with multiple algorithms
- **CSRF Protection** - Built-in CSRF token generation and validation
- **XSS Prevention** - Automatic output escaping in templates
- **Validation** - Fluent, readable validation with custom rules
- **Middleware** - Request/response filtering for cross-cutting concerns

## � Template Showcase

### **Template Inheritance & Components**

```html
{{-- layouts/app.prism --}}
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'My App')</title>
    <meta name="csrf-token" content="@csrf">
</head>
<body>
    <nav>@include('partials.navigation')</nav>
    
    <main class="container">
        @yield('content')
    </main>
    
    @include('partials.footer')
</body>
</html>

{{-- pages/dashboard.prism --}}
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="dashboard">
        <h1>Welcome back, {{ $user->name | title }}!</h1>
        
        @auth
            <div class="user-stats">
                @foreach($stats as $stat)
                    @component('components.stat-card')
                        @slot('title', $stat->name)
                        @slot('value', $stat->value | number)
                        @slot('trend', $stat->change | currency)
                        @slot('icon', $stat->icon)
                    @endcomponent
                @endforeach
            </div>
        @endauth
        
        @guest
            <p>Please <a href="/login">log in</a> to view your dashboard.</p>
        @endguest
    </div>
@endsection
```

### **Advanced Directives & Logic**

```html
{{-- Conditional content with authentication --}}
@auth
    @if($user->isAdmin())
        <div class="admin-panel">
            <h3>Admin Controls</h3>
            <a href="/admin/users" class="btn btn-primary">Manage Users</a>
        </div>
    @elseif($user->isModerator())
        <div class="mod-panel">
            <h3>Moderator Tools</h3>
            <a href="/moderate" class="btn btn-secondary">Review Content</a>
        </div>
    @endif
@endauth

{{-- Switch statements for role-based content --}}
@switch($user->subscription)
    @case('premium')
        <div class="premium-features">
            <h4>Premium Features Available</h4>
            @include('features.premium')
        </div>
        @break
    @case('pro')
        <div class="pro-features">
            <h4>Pro Features</h4>
            @include('features.pro')
        </div>
        @break
    @default
        <div class="upgrade-prompt">
            <h4>Upgrade for More Features</h4>
            <a href="/upgrade" class="btn btn-upgrade">Upgrade Now</a>
        </div>
@endswitch

{{-- Loops with filtering --}}
<div class="product-grid">
    @foreach($products as $product)
        <div class="product-card">
            <h3>{{ $product->name | title }}</h3>
            <p class="price">{{ $product->price | currency }}</p>
            <p class="description">{{ $product->description | truncate:100 }}</p>
            <small>Added {{ $product->created_at | date:'M j, Y' }}</small>
        </div>
    @endforeach
</div>
```

## 🔐 Authentication Examples

### **Setting Up Authentication**

```php
<?php
// Bootstrap authentication in your application

use Refynd\Auth\AuthManager;
use Refynd\Hash\HashManager;

// Configure authentication
$authManager = $container->make(AuthManager::class);

// Login attempt
if ($authManager->attempt(['email' => $email, 'password' => $password])) {
    // User authenticated
    $user = $authManager->user();
    redirect('/dashboard');
} else {
    // Authentication failed
    back()->withErrors(['Invalid credentials']);
}

// Logout
$authManager->logout();
```

### **Protected Routes with Middleware**

```php
<?php
// Set up authenticated routes

$router->middleware(['auth'])->group(function($router) {
    $router->get('/dashboard', 'DashboardController@index');
    $router->get('/profile', 'ProfileController@show');
    $router->post('/profile', 'ProfileController@update');
});

$router->middleware(['guest'])->group(function($router) {
    $router->get('/login', 'Auth\LoginController@show');
    $router->post('/login', 'Auth\LoginController@login');
    $router->get('/register', 'Auth\RegisterController@show');
});
```

## �🏗️ Framework Usage

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

### Enhanced ORM System

```php
use Refynd\Database\Model;

class User extends Model
{
    protected array $fillable = ['name', 'email'];
    
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Fluent queries with relationships
$users = User::with(['posts'])
    ->where('status', 'active')
    ->get();

// Collections with Laravel-style methods
$activeUsers = $users->filter(fn($user) => $user->posts->isNotEmpty())
    ->sortBy('name');
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

### Schema & Migrations

```php
use Refynd\Database\Schema;
use Refynd\Database\Blueprint;

// Create tables with fluent syntax
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});
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

### Real-time WebSocket Communication

```php
use Refynd\WebSocket\WebSocketServer;

// Start WebSocket server
$server = new WebSocketServer('127.0.0.1', 8080);
$server->start();

// Or via console command
// php refynd websocket:serve --host=127.0.0.1 --port=8080
```

### Background Queue Processing

```php
use Refynd\Queue\QueuedJob;

// Queue a job for background processing
class SendEmailJob implements JobInterface
{
    public function handle(): void {
        // Send email logic
    }
}

$queue = $container->make(QueueInterface::class);
$queue->push(new QueuedJob(SendEmailJob::class, $jobData));

// Start queue worker
// php refynd queue:work
```

### File Storage Operations

```php
use Refynd\Storage\StorageManager;

$storage = $container->make(StorageManager::class);

// Store a file
$storage->put('uploads/photo.jpg', $fileContents);

// Retrieve a file
$contents = $storage->get('uploads/photo.jpg');

// Check if file exists
if ($storage->exists('uploads/photo.jpg')) {
    $size = $storage->size('uploads/photo.jpg');
}
```

### Rate Limiting Protection

```php
use Refynd\RateLimiter\RateLimiter;

// Apply rate limiting
$rateLimiter = $container->make(RateLimiter::class);

try {
    $result = $rateLimiter->attempt('api:user:123', 60, function() {
        // Your rate-limited code here
        return processApiRequest();
    });
} catch (RateLimitExceededException $e) {
    // Handle rate limit exceeded
    return response(['error' => 'Rate limit exceeded'], 429);
}
```

## 🏢 Built for the Real World

Refynd powers applications that matter:

- **🌐 Web Applications** - From simple sites to complex platforms with real-time features
- **🔌 REST APIs** - Scalable backends with rate limiting and queue processing
- **⚡ Real-time Applications** - WebSocket-powered chat, notifications, and live updates
- **🏢 Enterprise Systems** - Business applications with background processing
- **📱 Modern Platforms** - Content management, e-commerce, forums with enterprise features

## 🔧 Requirements

- **PHP 8.4+** - Modern PHP with all the latest features
- **Composer** - For dependency management
- **Extensions**:
  - `ext-sockets` - Required for WebSocket support
  - `ext-pcntl` - Required for queue worker process control
- **Optional Extensions**:
  - `ext-redis` - For Redis cache driver and enhanced rate limiting
  - `ext-memcached` - For Memcached cache driver

## 📚 Documentation

- **[Authentication Guide](docs/AUTHENTICATION.md)** - Complete authentication system setup
- **[Rate Limiting Guide](docs/RATE_LIMITING.md)** - Comprehensive rate limiting documentation
- **[WebSocket Guide](docs/WEBSOCKET_RATE_LIMITING.md)** - Real-time WebSocket implementation
- **[ORM Guide](docs/ORM.md)** - Complete ORM documentation with examples
- **[Core Capabilities](docs/CURRENT_CAPABILITIES.md)** - Complete component overview
- **[What You Can Build](docs/WHAT_YOU_CAN_BUILD.md)** - Application examples and patterns
- **[Performance Optimizations](docs/PERFORMANCE_OPTIMIZATIONS.md)** - Framework optimization guide
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

---

**Created by [Jade Monathrae Lewis](mailto:jade@refynd.dev)** - *Founder & Creator of Refynd*

## 🌟 Ecosystem

- **[refynd/cli](https://github.com/refynd/cli)** - Command line interface for development workflow
- **[refynd/refynd](https://github.com/refynd/refynd)** - Application skeleton for creating new projects

---

<p align="center">
<strong>Ready to forge something extraordinary?</strong><br>
<em>Your next great application starts with Refynd.</em>
</p>

<p align="center">
🔥 <strong>Start Building Today</strong> 🔥
</p>
