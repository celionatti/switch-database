<?php

declare(strict_types=1);

namespace Switch\Database\Schema;

use Closure;
use Switch\Database\Connection\Connection;

class SchemaBuilder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $blueprint->toSql($table);
        $this->connection->statement($sql);
    }

    public function drop(string $table): void
    {
        $this->connection->statement("DROP TABLE {$table}");
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->statement("DROP TABLE IF EXISTS {$table}");
    }

    public function rename(string $from, string $to): void
    {
        $this->connection->statement("ALTER TABLE {$from} RENAME TO {$to}");
    }

    public function hasTable(string $table): bool
    {
        $pdo = $this->connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $result = $this->connection->select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name = ?",
                [$table]
            );
            return !empty($result);
        }

        $result = $this->connection->select(
            "SELECT table_name FROM information_schema.tables WHERE table_name = ?",
            [$table]
        );
        return !empty($result);
    }

    public function hasColumn(string $table, string $column): bool
    {
        $pdo = $this->connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $result = $this->connection->select("PRAGMA table_info({$table})");
            foreach ($result as $row) {
                if ($row['name'] === $column) {
                    return true;
                }
            }
            return false;
        }

        $result = $this->connection->select(
            "SELECT column_name FROM information_schema.columns WHERE table_name = ? AND column_name = ?",
            [$table, $column]
        );
        return !empty($result);
    }
}
