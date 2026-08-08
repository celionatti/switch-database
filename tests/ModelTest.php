<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\ORM\Exception\ModelNotFoundException;
use Switch\Database\ORM\Model;
use Switch\Database\ORM\Relation\BelongsTo;
use Switch\Database\ORM\Relation\HasMany;
use Switch\Database\Schema\Blueprint;
use Switch\Database\Schema\SchemaBuilder;

class UserModel extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'is_admin'];
    protected array $casts = ['is_admin' => 'bool'];

    public function posts(): HasMany
    {
        return $this->hasMany(PostModel::class, 'user_id');
    }
}

class PostModel extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'content'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}

class ArticleModel extends Model
{
    protected string $table = 'articles';
    protected array $fillable = ['title', 'deleted_at'];
    protected bool $softDeletes = true;
}

class ModelTest extends TestCase
{
    protected function setUp(): void
    {
        $db = Connection::sqlite(':memory:');
        Model::setConnection($db);

        $schema = new SchemaBuilder($db);
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('title');
            $table->text('content');
            $table->timestamps();
        });

        $schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function testModelCreateFindUpdateDelete(): void
    {
        $user = UserModel::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'is_admin' => 1
        ]);

        $this->assertEquals(1, $user->getKey());
        $this->assertEquals('John Doe', $user->name);
        $this->assertTrue($user->is_admin);

        $found = UserModel::find(1);
        $this->assertNotNull($found);
        $this->assertEquals('John Doe', $found->name);

        $found->name = 'John Updated';
        $found->save();

        $updated = UserModel::find(1);
        $this->assertEquals('John Updated', $updated->name);

        $this->assertTrue($updated->delete());
        $this->assertNull(UserModel::find(1));
    }

    public function testFindOrFailAndFirstOrFail(): void
    {
        UserModel::create(['name' => 'Alice', 'email' => 'alice@example.com']);

        $user = UserModel::findOrFail(1);
        $this->assertEquals('Alice', $user->name);

        $first = UserModel::firstOrFail(['email' => 'alice@example.com']);
        $this->assertEquals('Alice', $first->name);

        $this->expectException(ModelNotFoundException::class);
        UserModel::findOrFail(999);
    }

    public function testFirstOrCreateAndFirstOrNewAndUpdateOrCreate(): void
    {
        // firstOrCreate creates new record
        $u1 = UserModel::firstOrCreate(['email' => 'bob@example.com'], ['name' => 'Bob']);
        $this->assertEquals('Bob', $u1->name);

        // firstOrCreate returns existing record
        $u2 = UserModel::firstOrCreate(['email' => 'bob@example.com'], ['name' => 'Different']);
        $this->assertEquals('Bob', $u2->name);

        // firstOrNew returns unsaved instance
        $u3 = UserModel::firstOrNew(['email' => 'charlie@example.com'], ['name' => 'Charlie']);
        $this->assertEquals('Charlie', $u3->name);
        $this->assertNull(UserModel::where('email', 'charlie@example.com')->first());

        // updateOrCreate updates existing
        $u4 = UserModel::updateOrCreate(['email' => 'bob@example.com'], ['name' => 'Bob Senior']);
        $this->assertEquals('Bob Senior', $u4->name);
        $this->assertEquals('Bob Senior', UserModel::find($u1->getKey())->name);
    }

    public function testEagerLoadingSolvesNPlusOne(): void
    {
        $u1 = UserModel::create(['name' => 'User 1', 'email' => 'u1@example.com']);
        $u2 = UserModel::create(['name' => 'User 2', 'email' => 'u2@example.com']);

        PostModel::create(['user_id' => $u1->getKey(), 'title' => 'P1', 'content' => 'C1']);
        PostModel::create(['user_id' => $u1->getKey(), 'title' => 'P2', 'content' => 'C2']);
        PostModel::create(['user_id' => $u2->getKey(), 'title' => 'P3', 'content' => 'C3']);

        // Eager load posts with 'with' method in 1 query
        $users = UserModel::with('posts')->get();
        $this->assertCount(2, $users);

        $user1 = $users->first();
        $this->assertTrue($user1->relationLoaded('posts'));
        $this->assertCount(2, $user1->posts);
    }

    public function testSoftDeletes(): void
    {
        $article = ArticleModel::create(['title' => 'Soft Delete Article']);
        $id = $article->getKey();

        // Soft delete
        $article->delete();

        // Excluded from normal query
        $this->assertNull(ArticleModel::find($id));

        // Found in withTrashed
        $trashed = ArticleModel::withTrashed()->find($id);
        $this->assertNotNull($trashed);

        // Restore
        $trashed->restore();
        $this->assertNotNull(ArticleModel::find($id));

        // Force delete
        $article->forceDelete();
        $this->assertNull(ArticleModel::withTrashed()->find($id));
    }

    public function testPaginationAndChunking(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            UserModel::create(['name' => "User {$i}", 'email' => "user{$i}@example.com"]);
        }

        // Test Pagination
        $paginator = UserModel::paginate(3, 1); // page 1, 3 per page
        $this->assertEquals(10, $paginator->total);
        $this->assertEquals(4, $paginator->lastPage);
        $this->assertTrue($paginator->hasMorePages());
        $this->assertCount(3, $paginator->items);

        $arrayData = $paginator->toArray();
        $this->assertEquals(10, $arrayData['total']);
        $this->assertEquals(4, $arrayData['last_page']);
        $this->assertCount(3, $arrayData['data']);

        // Test Chunking
        $chunkCount = 0;
        $totalProcessed = 0;
        UserModel::chunk(4, function ($models, $page) use (&$chunkCount, &$totalProcessed) {
            $chunkCount++;
            $totalProcessed += count($models);
        });

        $this->assertEquals(3, $chunkCount); // 4 + 4 + 2
        $this->assertEquals(10, $totalProcessed);
    }
}
