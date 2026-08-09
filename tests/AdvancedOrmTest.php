<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use Switch\Database\Connection\Connection;
use Switch\Database\ORM\Model;
use Switch\Database\ORM\Relation\HasMany;
use Switch\Database\Schema\Blueprint;
use Switch\Database\Schema\SchemaBuilder;

class AdvancedOrmUser extends Model
{
    protected string $table = 'orm_users';
    protected array $fillable = ['name', 'email', 'status', 'role', 'settings', 'password'];
    protected array $casts = [
        'settings' => 'json',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(AdvancedOrmPost::class, 'user_id');
    }

    public function scopeActive($query): mixed
    {
        return $query->where('status', 'active');
    }

    public function scopeRole($query, string $role): mixed
    {
        return $query->where('role', $role);
    }

    public function getUpperNameAttribute($value): string
    {
        return strtoupper($this->attributes['name'] ?? '');
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = 'hashed_' . $value;
    }
}

class AdvancedOrmPost extends Model
{
    protected string $table = 'orm_posts';
    protected array $fillable = ['user_id', 'title', 'published'];
    protected array $casts = [
        'published' => 'boolean',
    ];
}

class AdvancedOrmTest
{
    protected function setUp(): void
    {
        $connection = Connection::sqlite(':memory:');
        Model::setConnection($connection);

        $schema = new SchemaBuilder($connection);
        $schema->create('orm_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('active');
            $table->string('role')->default('user');
            $table->text('settings')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('orm_posts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('title');
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void {}

    public function testDynamicMagicFinders(): void
    {
        AdvancedOrmUser::create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $user = AdvancedOrmUser::findByEmail('alice@example.com');
        assert($user !== null, 'findByEmail should return user');
        assert($user->name === 'Alice');
    }

    public function testDynamicCompoundWheres(): void
    {
        AdvancedOrmUser::create(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active', 'role' => 'admin']);
        AdvancedOrmUser::create(['name' => 'Charlie', 'email' => 'charlie@example.com', 'status' => 'inactive', 'role' => 'user']);

        $admins = AdvancedOrmUser::whereStatusAndRole('active', 'admin')->get();
        assert(count($admins) === 1, 'Should match 1 active admin');
        assert($admins->first()->name === 'Bob');
    }

    public function testQueryScopes(): void
    {
        AdvancedOrmUser::create(['name' => 'David', 'email' => 'david@example.com', 'status' => 'active', 'role' => 'admin']);
        AdvancedOrmUser::create(['name' => 'Eve', 'email' => 'eve@example.com', 'status' => 'inactive', 'role' => 'admin']);

        /** @var AdvancedOrmUser $user */
        $user = AdvancedOrmUser::active()->role('admin')->first();
        assert($user !== null, 'Scope chain active()->role() should return user');
        assert($user->name === 'David');
    }

    public function testAccessorsAndMutators(): void
    {
        $user = new AdvancedOrmUser();
        $user->name = 'john doe';
        $user->password = 'secret123';
        $user->email = 'john@example.com';
        $user->save();

        assert($user->upper_name === 'JOHN DOE', 'Accessor upper_name should format name to uppercase');
        assert($user->getRawAttributes()['password'] === 'hashed_secret123', 'Mutator should hash password on set');
    }

    public function testJsonAttributeCasting(): void
    {
        $user = new AdvancedOrmUser();
        $user->name = 'Grace';
        $user->email = 'grace@example.com';
        $user->settings = json_encode(['theme' => 'dark', 'notifications' => true]);
        $user->save();

        $fetched = AdvancedOrmUser::find($user->id);
        assert(is_array($fetched->settings), 'settings attribute should cast to array');
        assert($fetched->settings['theme'] === 'dark');
    }

    public function testRelationshipHasAndWhereHas(): void
    {
        $u1 = AdvancedOrmUser::create(['name' => 'Author 1', 'email' => 'a1@example.com']);
        $u2 = AdvancedOrmUser::create(['name' => 'Author 2', 'email' => 'a2@example.com']);

        AdvancedOrmPost::create(['user_id' => $u1->id, 'title' => 'Post 1', 'published' => true]);
        AdvancedOrmPost::create(['user_id' => $u1->id, 'title' => 'Post 2', 'published' => false]);

        $authorsWithPosts = AdvancedOrmUser::has('posts')->get();
        assert(count($authorsWithPosts) === 1, 'Only Author 1 has posts');
        assert($authorsWithPosts->first()->id === $u1->id);

        $authorsWithoutPosts = AdvancedOrmUser::doesntHave('posts')->get();
        assert(count($authorsWithoutPosts) === 1, 'Only Author 2 has no posts');
        assert($authorsWithoutPosts->first()->id === $u2->id);
    }
}
