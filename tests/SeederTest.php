<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\ORM\Model;
use Switch\Database\Seeder\Seeder;
use Switch\Database\Seeder\SeederRunner;

class TestUserSeederModel extends Model
{
    protected string $table = 'test_seeder_users';
    protected array $fillable = ['name', 'email'];
    protected bool $timestamps = false;
}

class ChildSeeder extends Seeder
{
    public function run(): void
    {
        TestUserSeederModel::create(['name' => 'Alice', 'email' => 'alice@example.com']);
    }
}

class MainDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        TestUserSeederModel::create(['name' => 'Root Admin', 'email' => 'admin@example.com']);
        $this->call(ChildSeeder::class);
    }
}

class SeederTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = Connection::sqlite(':memory:');
        Model::setConnection($this->connection);

        $this->connection->statement('
            CREATE TABLE test_seeder_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL
            )
        ');
    }

    public function testSeederExecutesAndCallsChildSeeders(): void
    {
        $runner = new SeederRunner($this->connection);
        $runner->run(MainDatabaseSeeder::class);

        $users = TestUserSeederModel::all();
        $this->assertCount(2, $users);
        $this->assertEquals('Root Admin', $users[0]->name);
        $this->assertEquals('Alice', $users[1]->name);
    }
}
