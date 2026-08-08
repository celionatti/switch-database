<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;

class QueryBuilderTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite(':memory:');
        $this->db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, status TEXT, age INTEGER)');
        $this->db->statement('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, title TEXT)');

        $this->db->table('users')->insert(['name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'active', 'age' => 25]);
        $this->db->table('users')->insert(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'inactive', 'age' => 30]);
        $this->db->table('users')->insert(['name' => 'Charlie', 'email' => 'charlie@example.com', 'status' => 'active', 'age' => 35]);

        $this->db->table('posts')->insert(['user_id' => 1, 'title' => 'First Post']);
    }

    public function testSelectAndWhere(): void
    {
        $users = $this->db->table('users')->where('status', 'active')->get();
        $this->assertCount(2, $users);
    }

    public function testWhereInAndWhereBetween(): void
    {
        $users = $this->db->table('users')->whereIn('id', [1, 3])->get();
        $this->assertCount(2, $users);

        $usersBetween = $this->db->table('users')->whereBetween('age', 28, 40)->get();
        $this->assertCount(2, $usersBetween);
    }

    public function testFirstAndValueAndPluck(): void
    {
        $user = $this->db->table('users')->where('id', 1)->first();
        $this->assertEquals('Alice', $user['name']);

        $name = $this->db->table('users')->where('id', 1)->value('name');
        $this->assertEquals('Alice', $name);

        $names = $this->db->table('users')->pluck('name');
        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $names);
    }

    public function testAggregates(): void
    {
        $this->assertEquals(3, $this->db->table('users')->count());
        $this->assertEquals(90, $this->db->table('users')->sum('age'));
        $this->assertEquals(30, $this->db->table('users')->avg('age'));
        $this->assertEquals(25, $this->db->table('users')->min('age'));
        $this->assertEquals(35, $this->db->table('users')->max('age'));
    }

    public function testJoins(): void
    {
        $results = $this->db->table('users')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->select('users.name', 'posts.title')
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Alice', $results[0]['name']);
        $this->assertEquals('First Post', $results[0]['title']);
    }

    public function testUpdateAndDelete(): void
    {
        $affected = $this->db->table('users')->where('id', 2)->update(['status' => 'active']);
        $this->assertEquals(1, $affected);

        $user = $this->db->table('users')->where('id', 2)->first();
        $this->assertEquals('active', $user['status']);

        $deleted = $this->db->table('users')->where('id', 2)->delete();
        $this->assertEquals(1, $deleted);
        $this->assertEquals(2, $this->db->table('users')->count());
    }

    public function testToSql(): void
    {
        $sql = $this->db->table('users')->where('status', 'active')->orderBy('age', 'DESC')->toSql();
        $this->assertStringContainsString('SELECT * FROM "users" WHERE "status" = ? ORDER BY "age" DESC', $sql);
    }
}
