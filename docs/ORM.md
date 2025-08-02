# Refynd ORM Documentation

The Refynd ORM provides a powerful, Laravel-inspired database abstraction layer with support for relationships, collections, migrations, and advanced querying capabilities.

## Table of Contents

1. [Models](#models)
2. [Relationships](#relationships)
3. [Collections](#collections)
4. [Query Builder](#query-builder)
5. [Migrations](#migrations)
6. [Schema Builder](#schema-builder)

## Models

Models extend the `Refynd\Database\Model` class and represent database tables.

### Basic Model Definition

```php
<?php

use Refynd\Database\Model;

class User extends Model
{
    protected static string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected bool $timestamps = true;
}
```

### Model Configuration

- `$table`: The database table name
- `$fillable`: Mass assignable attributes
- `$hidden`: Attributes to hide from array/JSON output
- `$timestamps`: Enable automatic timestamp management
- `$primaryKey`: Primary key column (default: 'id')

### Basic Operations

```php
// Create a new record
$user = User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'secret'
]);

// Find by ID
$user = User::find(1);

// Get all records
$users = User::allAsCollection();

// Update a record
$user->update(['name' => 'Jane Doe']);

// Delete a record
$user->delete();
```

### Querying

```php
// Where clauses
$users = User::where('status', 'active')->get();
$users = User::where('age', '>', 18)->get();

// Ordering and limiting
$users = User::orderBy('name')->limit(10)->get();

// Multiple conditions
$users = User::where('status', 'active')
    ->where('created_at', '>', '2024-01-01')
    ->orderBy('name')
    ->get();
```

## Relationships

The ORM supports four types of relationships:

### HasMany

One-to-many relationship where one model has many related models.

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Usage
$user = User::find(1);
$posts = $user->posts()->get();

// Create related model
$post = $user->posts()->create([
    'title' => 'New Post',
    'content' => 'Post content'
]);
```

### BelongsTo

Inverse of hasMany - a model belongs to another model.

```php
class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Usage
$post = Post::find(1);
$user = $post->user;
```

### HasOne

One-to-one relationship.

```php
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}

// Usage
$user = User::find(1);
$profile = $user->profile;
```

### BelongsToMany

Many-to-many relationship with pivot table.

```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }
}

// Usage
$user = User::find(1);
$roles = $user->roles()->get();

// Attach/detach
$user->roles()->attach($roleId);
$user->roles()->detach($roleId);
```

### Eager Loading

Load relationships efficiently to avoid N+1 queries:

```php
// Load posts with users
$posts = Post::with(['user'])->get();

// Load multiple relationships
$users = User::with(['posts', 'roles'])->get();

// Nested relationships
$users = User::with(['posts.comments'])->get();
```

## Collections

Collections provide a fluent interface for working with arrays of data.

### Basic Usage

```php
$users = User::allAsCollection();

// Filter
$activeUsers = $users->filter(function($user) {
    return $user->status === 'active';
});

// Map
$userNames = $users->map(function($user) {
    return $user->name;
});

// Pluck
$emails = $users->pluck('email');

// Group by
$usersByStatus = $users->groupBy('status');

// Sort
$sortedUsers = $users->sortBy('name');
```

### Collection Methods

- `filter($callback)`: Filter items using callback
- `map($callback)`: Transform items using callback
- `pluck($key)`: Extract values for a key
- `groupBy($key)`: Group items by key value
- `sortBy($key)`: Sort by key
- `contains($key, $value)`: Check if collection contains item
- `first()`: Get first item
- `last()`: Get last item
- `count()`: Count items
- `isEmpty()`: Check if empty
- `toArray()`: Convert to array

## Query Builder

Advanced querying capabilities:

### Joins

```php
$posts = Post::join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name as author_name')
    ->get();
```

### Aggregates

```php
$count = User::count();
$max = User::max('age');
$min = User::min('age');
$avg = User::avg('rating');
$sum = User::sum('points');
```

### Raw Queries

```php
$users = User::selectRaw('COUNT(*) as post_count')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->groupBy('users.id')
    ->get();
```

## Migrations

Migrations provide version control for your database schema.

### Creating Migrations

```php
<?php

use Refynd\Database\Migration;
use Refynd\Database\Schema;
use Refynd\Database\Blueprint;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('users');
    }
}
```

### Running Migrations

```php
use Refynd\Database\MigrationRunner;

$runner = new MigrationRunner($pdo, 'database/migrations');

// Run pending migrations
$result = $runner->migrate();

// Rollback last batch
$result = $runner->rollback();

// Check migration status
$status = $runner->status();
```

## Schema Builder

The schema builder provides a fluent interface for creating and modifying database tables.

### Creating Tables

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');
    $table->foreignId('user_id')->references('id')->on('users');
    $table->timestamps();
    
    $table->index(['user_id', 'created_at']);
});
```

### Column Types

- `id()`: Auto-incrementing primary key
- `string($name, $length)`: VARCHAR column
- `text($name)`: TEXT column
- `integer($name)`: INT column
- `bigInteger($name)`: BIGINT column
- `boolean($name)`: BOOLEAN column
- `decimal($name, $precision, $scale)`: DECIMAL column
- `date($name)`: DATE column
- `dateTime($name)`: DATETIME column
- `timestamp($name)`: TIMESTAMP column
- `timestamps()`: created_at and updated_at columns

### Column Modifiers

```php
$table->string('email')->nullable();
$table->integer('count')->default(0);
$table->string('slug')->unique();
$table->text('description')->comment('User description');
```

### Indexes and Constraints

```php
// Indexes
$table->index('email');
$table->unique(['email', 'username']);

// Foreign keys
$table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
```

### Modifying Tables

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
    $table->dropColumn('old_column');
    $table->index('phone');
});
```

## Performance Features

The Refynd ORM is designed for performance:

1. **Efficient Query Building**: Minimal overhead query construction
2. **Eager Loading**: Prevents N+1 query problems
3. **Collection Optimization**: Memory-efficient data manipulation
4. **Connection Pooling**: Reuses database connections
5. **Query Caching**: Built-in query result caching (when configured)

## Best Practices

1. **Use Eager Loading**: Load relationships upfront to avoid N+1 queries
2. **Mass Assignment Protection**: Always define `$fillable` or `$guarded`
3. **Hide Sensitive Data**: Use `$hidden` for passwords and sensitive fields
4. **Use Collections**: Leverage collection methods for data manipulation
5. **Index Your Queries**: Add database indexes for frequently queried columns
6. **Use Migrations**: Version control your database schema changes

This ORM provides a powerful yet lightweight solution for database operations while maintaining Refynd's core values of performance and clean architecture.
