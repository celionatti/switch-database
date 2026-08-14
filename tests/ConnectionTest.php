<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\Connection\ConnectionConfig;
use Switch\Database\Connection\ConnectionManager;

class ConnectionTest extends TestCase
{
    public function testSqliteMemoryConnection(): void
    {
        $connection = Connection::sqlite(':memory:');
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertInstanceOf(\PDO::class, $connection->getPdo());
    }

    public function testSelectAndInsertQueries(): void
    {
        $db = Connection::sqlite(':memory:');
        $db->statement('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $id = $db->insert('INSERT INTO users (name) VALUES (?)', ['Alice']);
        $this->assertEquals(1, $id);

        $rows = $db->select('SELECT * FROM users WHERE id = ?', [1]);
        $this->assertCount(1, $rows);
        $this->assertEquals('Alice', $rows[0]['name']);
    }

    public function testTransactionCommit(): void
    {
        $db = Connection::sqlite(':memory:');
        $db->statement('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $db->transaction(function (Connection $conn) {
            $conn->insert('INSERT INTO items (name) VALUES (?)', ['Item A']);
            $conn->insert('INSERT INTO items (name) VALUES (?)', ['Item B']);
        });

        $rows = $db->select('SELECT COUNT(*) as cnt FROM items');
        $this->assertEquals(2, $rows[0]['cnt']);
    }

    public function testTransactionRollbackOnException(): void
    {
        $db = Connection::sqlite(':memory:');
        $db->statement('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        try {
            $db->transaction(function (Connection $conn) {
                $conn->insert('INSERT INTO items (name) VALUES (?)', ['Item A']);
                throw new \Exception('Trigger rollback');
            });
        } catch (\Throwable) {
            // Expected
        }

        $rows = $db->select('SELECT COUNT(*) as cnt FROM items');
        $this->assertEquals(0, $rows[0]['cnt']);
    }

    public function testConnectionManager(): void
    {
        $manager = new ConnectionManager();
        $manager->addConnection('default', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $conn = $manager->connection('default');
        $this->assertInstanceOf(Connection::class, $conn);
        $this->assertSame($conn, $manager->connection());
    }

    public function testConnectionManagerSingletonAndDbFacade(): void
    {
        $instance = ConnectionManager::getInstance();
        $this->assertInstanceOf(ConnectionManager::class, $instance);
        $this->assertSame($instance, ConnectionManager::getInstance());

        $instance->addConnection('default', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        \Switch\Database\DB::setConnection($instance->connection('default'));
        \Switch\Database\DB::select('SELECT 1 as val');

        $this->assertInstanceOf(\PDO::class, \Switch\Database\DB::getPdo());
        $this->assertInstanceOf(\Switch\Database\Query\QueryBuilder::class, \Switch\Database\DB::table('users'));
    }
}
