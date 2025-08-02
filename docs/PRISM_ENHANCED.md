# Enhanced Prism Template Engine

Prism has been significantly refined and enhanced to provide a powerful, flexible, and developer-friendly templating experience. The new version includes advanced features while maintaining the elegant simplicity that makes Prism a joy to use.

## What's New in Enhanced Prism

### 🚀 **Major Enhancements**

1. **Advanced Template Inheritance** - Proper layout inheritance with section stacking
2. **Reusable Components** - Modular, composable template components
3. **Custom Directives & Filters** - Extensible syntax with custom functionality
4. **Enhanced Error Handling** - Detailed error reporting and debug mode
5. **Performance Optimizations** - Smarter compilation and caching strategies
6. **Asset Management** - Built-in asset helpers and optimization
7. **Security Improvements** - Enhanced XSS protection and validation

### 🎯 **Key Features**

- **Template Inheritance** with `@extends`, `@section`, `@yield`, `@parent`
- **Component System** with `@component` and isolated data scopes
- **Advanced Control Structures** including `@switch`, `@forelse`, `@empty`
- **Rich Filter System** with 15+ built-in filters and custom filter support
- **Custom Directives** for application-specific functionality
- **Asset Management** with `@css`, `@js`, `@asset` directives
- **Authentication Helpers** with `@auth`, `@guest`, `@csrf`
- **Debug Mode** with performance tracking and error visualization

---

## Quick Start Guide

### Basic Setup

```php
use Refynd\Prism\PrismEngine;

// Create engine with debug mode
$prism = new PrismEngine(
    viewPath: '/path/to/views',
    cachePath: '/path/to/cache',
    debugMode: true
);

// Add global variables
$prism->addGlobals([
    'app_name' => 'My Application',
    'current_year' => date('Y'),
]);

// Render template
echo $prism->render('welcome', ['user' => $user]);
```

### Template Inheritance

**Layout Template (`layout.prism`):**
```prism
<!DOCTYPE html>
<html>
<head>
    <title>@yield('title', 'My App')</title>
    @css('app.css')
    @yield('styles')
</head>
<body>
    <header>
        <h1>{{ $app_name }}</h1>
        @auth
            <span>Welcome, {{ auth()->user()->name }}!</span>
        @else
            <a href="/login">Login</a>
        @endauth
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <p>&copy; {{ $current_year }} {{ $app_name }}</p>
    </footer>

    @js('app.js')
    @yield('scripts')
</body>
</html>
```

**Child Template (`dashboard.prism`):**
```prism
@extends('layout')

@section('title', 'Dashboard')

@section('styles')
    @css('dashboard.css')
@endsection

@section('content')
    <h2>Dashboard</h2>
    
    {{-- Component usage --}}
    @component('card', [
        'title' => 'Users',
        'value' => $user_count,
        'icon' => 'fas fa-users'
    ])
    @endcomponent
    
    {{-- Advanced loops --}}
    {% forelse $posts as $post %}
        <article>
            <h3>{{ $post->title }}</h3>
            <p>{{ $post->content | excerpt:100 }}</p>
            <time>{{ $post->created_at | date:'F j, Y' }}</time>
        </article>
    {% empty %}
        <p>No posts available.</p>
    {% endforelse %}
@endsection

@section('scripts')
    @js('dashboard.js')
@endsection
```

---

## Enhanced Syntax Reference

### Control Structures

#### Conditionals
```prism
{{-- Basic if statement --}}
{% if $user %}
    <p>Welcome, {{ $user->name }}!</p>
{% elseif $guest %}
    <p>Welcome, guest!</p>
{% else %}
    <p>Please log in.</p>
{% endif %}

{{-- Authentication shortcuts --}}
@auth
    <p>You are logged in!</p>
@endauth

@guest
    <p>Please log in.</p>
@endguest

{{-- Existence checks --}}
{% isset $variable %}
    <p>Variable is set.</p>
{% endisset %}

{% empty $items %}
    <p>No items found.</p>
{% endempty %}
```

#### Switch Statements
```prism
{% switch $user->role %}
    {% case 'admin' %}
        <p>Administrator access</p>
    {% case 'moderator' %}
        <p>Moderator access</p>
    {% default %}
        <p>Regular user access</p>
{% endswitch %}
```

#### Enhanced Loops
```prism
{{-- Foreach with else --}}
{% forelse $items as $item %}
    <div>{{ $item->name }}</div>
{% empty %}
    <div>No items found</div>
{% endforelse %}

{{-- Traditional loops --}}
{% foreach $users as $user %}
    <p>{{ $user->name }}</p>
    {% if $loop->first %}
        <p>This is the first item</p>
    {% endif %}
{% endforeach %}

{{-- Break and continue --}}
{% foreach $items as $item %}
    {% if $item->hidden %}
        {% continue %}
    {% endif %}
    
    {% if $item->critical %}
        {% break %}
    {% endif %}
    
    <div>{{ $item->name }}</div>
{% endforeach %}
```

### Template Inheritance

#### Sections
```prism
{{-- Define a section --}}
@section('content')
    <h1>Page Content</h1>
@endsection

{{-- Section with immediate display --}}
@section('sidebar')
    <div>Sidebar content</div>
@show

{{-- Append to parent section --}}
@section('scripts')
    @parent
    <script>console.log('Additional script');</script>
@endsection

{{-- Set section content directly --}}
@section('title', 'Page Title')
```

#### Yielding Content
```prism
{{-- Yield with default content --}}
@yield('content', '<p>Default content</p>')

{{-- Simple yield --}}
@yield('title')
```

### Components

#### Component Definition (`components/card.prism`)
```prism
<div class="card {{ $class ?? 'default' }}">
    {% if isset($title) %}
        <h3 class="card-title">{{ $title }}</h3>
    {% endif %}
    
    <div class="card-body">
        {{ $slot ?? $content ?? 'No content' }}
    </div>
    
    {% if isset($footer) %}
        <div class="card-footer">{{ $footer }}</div>
    {% endif %}
</div>
```

#### Component Usage
```prism
{{-- Component with data --}}
@component('card', [
    'title' => 'Card Title',
    'class' => 'primary',
    'content' => 'Card content here'
])
@endcomponent

{{-- Component with slot content --}}
@component('card', ['title' => 'Dynamic Card'])
    <p>This content goes into the slot.</p>
    <button>Click me</button>
@endcomponent
```

### Filters

#### Built-in Filters
```prism
{{-- Text filters --}}
{{ $text | upper }}              <!-- UPPERCASE -->
{{ $text | lower }}              <!-- lowercase -->
{{ $text | title }}              <!-- Title Case -->
{{ $text | capitalize }}         <!-- Capitalize first letter -->
{{ $text | slug }}               <!-- url-friendly-slug -->
{{ $text | truncate:50:'...' }}  <!-- Truncated text... -->

{{-- Number filters --}}
{{ $number | number_format }}    <!-- 1,234 -->
{{ $price | currency:'$' }}      <!-- $1,234.56 -->
{{ $bytes | filesize }}          <!-- 1.2 MB -->

{{-- Date filters --}}
{{ $timestamp | date:'F j, Y' }} <!-- January 1, 2025 -->
{{ $date | time_ago }}           <!-- 2 hours ago -->

{{-- Array filters --}}
{{ $items | length }}            <!-- Array/string length -->
{{ $array | reverse }}           <!-- Reversed array -->
{{ $data | json }}               <!-- JSON encoded -->

{{-- Utility filters --}}
{{ $value | default:'N/A' }}     <!-- Default if empty -->
{{ $html | escape }}             <!-- HTML escaped -->
{{ $content | raw }}             <!-- Unescaped output -->
```

#### Custom Filters
```php
// Register custom filter
$prism->filter('currency', function ($value, $symbol = '$') {
    return $symbol . number_format($value, 2);
});

$prism->filter('excerpt', function ($text, $length = 100) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
});
```

```prism
{{-- Use custom filters --}}
{{ $price | currency:'€' }}      <!-- €29.99 -->
{{ $content | excerpt:150 }}     <!-- Truncated excerpt... -->
```

### Custom Directives

#### Built-in Directives
```prism
{{-- Security --}}
@csrf                           <!-- CSRF token field -->
@method('PUT')                  <!-- Method spoofing -->

{{-- Debugging --}}
@dd($variable)                  <!-- Dump and die -->
@dump($data)                    <!-- Dump variable -->

{{-- JSON output --}}
@json($data)                    <!-- JSON encoded output -->
```

#### Custom Directives
```php
// Register custom directive
$prism->directive('hello', function ($name) {
    return "<?php echo 'Hello, ' . ({$name} ?? 'World') . '!'; ?>";
});

$prism->directive('tooltip', function ($expression) {
    list($text, $tooltip) = explode(',', $expression);
    return "<?php echo '<span title=\"' . {$tooltip} . '\">' . {$text} . '</span>'; ?>";
});
```

```prism
{{-- Use custom directives --}}
@hello($user->name)
@tooltip('Help Text', 'This is helpful information')
```

### Asset Management

```prism
{{-- Asset helpers --}}
@asset('images/logo.png')       <!-- /assets/images/logo.png -->
@css('styles/app.css')          <!-- <link rel="stylesheet" href="..."> -->
@js('scripts/app.js')           <!-- <script src="..."></script> -->

{{-- Manual asset URLs --}}
<img src="{{ asset('images/photo.jpg') }}" alt="Photo">
<link rel="stylesheet" href="{{ asset('css/custom.css') }}">
```

---

## Advanced Features

### Error Handling & Debugging

#### Debug Mode
```php
// Enable debug mode
$prism = new PrismEngine($viewPath, $cachePath, debugMode: true);

// Get render statistics
$stats = $prism->getRenderStats();
foreach ($stats as $template => $data) {
    echo "Template: {$template}\n";
    echo "Render time: {$data['render_time']}ms\n";
}
```

#### Error Templates
When debug mode is enabled, compilation and runtime errors are displayed with:
- Template name and path
- Error message and line number
- Full stack trace
- Helpful suggestions

### Performance Optimizations

#### Smart Caching
- Template modification time tracking
- Intelligent cache invalidation
- Compiled template validation
- Performance metrics collection

#### Compilation Optimizations
- Redundant PHP tag removal
- Consecutive echo statement optimization
- Pattern compilation caching
- Syntax validation

### Security Features

#### XSS Protection
```prism
{{-- Automatically escaped --}}
{{ $user_input }}               <!-- Safe by default -->

{{-- Raw output (use carefully) --}}
{{{ $trusted_html }}}           <!-- Unescaped -->

{{-- Manual escaping --}}
{{ $data | escape }}            <!-- Explicitly escaped -->
```

#### CSRF Protection
```prism
<form method="POST">
    @csrf
    <!-- Form fields -->
</form>
```

---

## Integration with Refynd Framework

### Container Integration
```php
// Register in container
$container->bind(PrismEngine::class, function () {
    $engine = new PrismEngine('/views', '/cache');
    
    // Register application-specific directives
    $engine->directive('route', function ($name) {
        return "<?php echo route({$name}); ?>";
    });
    
    return $engine;
});
```

### Module Integration
```php
// In a Refynd module
class ViewModule extends Module
{
    public function register(Container $container): void
    {
        $container->bind('view.engine', PrismEngine::class);
        $container->bind('view', function ($container) {
            return function ($template, $data = []) use ($container) {
                return $container->make('view.engine')->render($template, $data);
            };
        });
    }
}
```

### Middleware Integration
```php
// View composer middleware
class ViewComposerMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        $engine = $this->container->make(PrismEngine::class);
        
        // Add global view data
        $engine->addGlobals([
            'request' => $request,
            'user' => auth()->user(),
            'flash' => session('flash'),
        ]);
        
        return $next($request);
    }
}
```

---

## Best Practices

### Template Organization
```
views/
├── layouts/
│   ├── app.prism
│   ├── admin.prism
│   └── email.prism
├── components/
│   ├── card.prism
│   ├── form.prism
│   ├── table.prism
│   └── navigation.prism
├── pages/
│   ├── home.prism
│   ├── about.prism
│   └── contact.prism
└── partials/
    ├── header.prism
    ├── footer.prism
    └── sidebar.prism
```

### Performance Tips
1. **Enable caching** in production
2. **Use components** for reusable UI elements
3. **Minimize filter chains** in loops
4. **Leverage template inheritance** to reduce duplication
5. **Use debug mode** only in development

### Security Guidelines
1. **Always escape user input** (default behavior)
2. **Use raw output sparingly** and only with trusted data
3. **Validate component props** before rendering
4. **Include CSRF tokens** in forms
5. **Sanitize file paths** in includes

---

## Migration from Basic Prism

### Simple Syntax Updates
```prism
<!-- Old -->
@include('partial')

<!-- New (same, but with better error handling) -->
@include('partial')

<!-- Old -->
{{ $variable }}

<!-- New (enhanced with filters) -->
{{ $variable | default:'N/A' | escape }}
```

### Enhanced Features
```prism
<!-- Old limited control structures -->
{% if $condition %}
    Content
{% endif %}

<!-- New enhanced structures -->
{% switch $status %}
    {% case 'active' %}
        <span class="badge-success">Active</span>
    {% case 'inactive' %}
        <span class="badge-warning">Inactive</span>
    {% default %}
        <span class="badge-secondary">Unknown</span>
{% endswitch %}
```

The enhanced Prism template engine provides a powerful, secure, and developer-friendly templating solution that scales from simple websites to complex enterprise applications while maintaining the elegant simplicity that makes it a joy to use.
