<?php

declare(strict_types=1);

namespace Switch\Database;

use Closure;
use PDO;
use Switch\Database\Connection\Connection;
use Switch\Database\Connection\ConnectionManager;
use Switch\Database\ORM\Model;
use Switch\Database\Query\QueryBuilder;

class DB
{
    private static ?Connection $connection = null;

    /**
     * Set the active default database connection.
     */
    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
        Model::setConnection($connection);
    }

    /**
     * Get the active database connection.
     */
    public static function connection(?string $name = null): Connection
    {
        if ($name === null && self::$connection !== null) {
            return self::$connection;
        }

        if (Model::hasConnection() && $name === null) {
            return Model::getConnection();
        }

        return ConnectionManager::getInstance()->connection($name);
    }

    /**
     * Get underlying PDO instance.
     */
    public static function getPdo(): PDO
    {
        return self::connection()->getPdo();
    }

    /**
     * Begin a fluent query on a database table.
     */
    public static function table(string $table): QueryBuilder
    {
        return self::connection()->table($table);
    }

    /**
     * Execute a raw SELECT query with bindings.
     *
     * @param array<string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public static function select(string $query, array $bindings = []): array
    {
        return self::connection()->select($query, $bindings);
    }

    /**
     * Execute a raw INSERT statement.
     *
     * @param array<string, mixed> $bindings
     */
    public static function insert(string $query, array $bindings = []): int|string|bool
    {
        return self::connection()->insert($query, $bindings);
    }

    /**
     * Execute a raw UPDATE statement.
     *
     * @param array<string, mixed> $bindings
     */
    public static function update(string $query, array $bindings = []): int
    {
        return self::connection()->update($query, $bindings);
    }

    /**
     * Execute a raw DELETE statement.
     *
     * @param array<string, mixed> $bindings
     */
    public static function delete(string $query, array $bindings = []): int
    {
        return self::connection()->delete($query, $bindings);
    }

    /**
     * Execute a Closure within a database transaction.
     *
     * @template T
     * @param Closure(Connection): T $callback
     * @return T
     */
    public static function transaction(Closure $callback): mixed
    {
        return self::connection()->transaction($callback);
    }
}
