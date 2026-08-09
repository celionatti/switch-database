# Switch Database (`switch/database`)

> An advanced, intuitive, zero-raw-SQL Active Record ORM and Query Builder with native **PostgreSQL**, **MySQL**, and **SQLite** support.

---

## 📦 Installation

```bash
composer require switch/database
```

---

## ⚙️ Connection Setup

```php
use Switch\Database\Connection\Connection;
use Switch\Database\ORM\Model;

// 1. PostgreSQL Connection
$db = Connection::postgres(
    database: 'my_database',
    host: '127.0.0.1',
    port: 5432,
    username: 'postgres',
    password: 'secret'
);

// 2. MySQL Connection
$db = Connection::mysql(database: 'my_db', username: 'root', password: '');

// 3. SQLite Connection
$db = Connection::sqlite(':memory:'); // or path/to/database.sqlite

// Set default connection for all ORM Models
Model::setConnection($db);
```

---

## 💎 Active Record ORM Features

### 1. Model Definition

```php
use Switch\Database\ORM\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'status', 'role', 'settings', 'password'];
    
    // Smart Attribute Casting
    protected array $casts = [
        'settings' => 'json',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];
}
```

### 2. Magic Dynamic Finders & Wheres (No Raw SQL Required)

```php
// Dynamic Finders
$user = User::findByEmail('alice@example.com');
$post = Post::findBySlug('my-first-post');
$firstAdmin = User::firstWhere('role', 'admin');

// Dynamic Compound Where Conditions
$activeAdmins = User::whereStatusAndRole('active', 'admin')->get();
$verifiedUsers = User::whereEmailVerifiedAndStatus(true, 'active')->get();
```

### 3. Query Scopes

Define reusable scopes on your model:

```php
class User extends Model
{
    public function scopeActive($query) {
        return $query->where('status', 'active');
    }

    public function scopeRole($query, string $role) {
        return $query->where('role', $role);
    }
}

// Reusable Scope Chaining
$users = User::active()->role('admin')->get();
```

### 4. Attribute Accessors & Mutators

```php
class User extends Model
{
    // Accessor: $user->full_name
    public function getFullNameAttribute(): string
    {
        return $this->attributes['first_name'] . ' ' . $this->attributes['last_name'];
    }

    // Mutator: $user->password = 'secret' (automatically hashes before save)
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT);
    }
}
```

### 5. Relationships & Eager Loading (Solves N+1)

```php
class User extends Model
{
    public function posts(): HasMany {
        return $this->hasMany(Post::class, 'user_id');
    }
}

class Post extends Model
{
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}

// Eager Loading (Executes 2 queries total instead of N+1)
$users = User::with('posts')->get();

// Relationship Existence Filtering
$authors = User::has('posts')->get();
$publishedAuthors = User::whereHas('posts', fn($q) => $q->where('status', 'published'))->get();
$usersWithoutPosts = User::doesntHave('posts')->get();

// With Count (attaches posts_count to models without extra queries)
$usersWithCount = User::withCount('posts')->get();
foreach ($usersWithCount as $u) {
    echo $u->name . ' wrote ' . $u->posts_count . ' posts.';
}
```

---

## 🛠️ Fluent Query Builder

```php
use Switch\Database\Connection\Connection;

$db = Connection::postgres('my_db');

// PostgreSQL ILIKE case-insensitive search
$users = $db->table('users')
    ->whereILike('email', '%@gmail.com')
    ->whereIn('status', ['active', 'pending'])
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();

// Pagination
$paginator = $db->table('users')->where('status', 'active')->paginate(perPage: 15, page: 1);
echo $paginator->total;
echo $paginator->lastPage;

// Chunking large datasets
$db->table('users')->chunk(100, function ($rows, $page) {
    foreach ($rows as $row) {
        // process batch
    }
});
```

---

## 🏗️ Schema Builder & Migrations

```php
use Switch\Database\Schema\SchemaBuilder;
use Switch\Database\Schema\Blueprint;

$schema = new SchemaBuilder($db);

$schema->create('users', function (Blueprint $table) {
    $table->id();
    $table->uuid();
    $table->string('name');
    $table->string('email')->unique();
    $table->boolean('is_active')->default(true);
    $table->jsonb('settings')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

---

## 📄 License
MIT License.
