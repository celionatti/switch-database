<?php

declare(strict_types=1);

namespace Switch\Database\Migration;

use Switch\Database\Connection\Connection;

class MigrationRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function createRepository(): void
    {
        $this->connection->statement(
            "CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL
            )"
        );
    }

    public function repositoryExists(): bool
    {
        $pdo = $this->connection->getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $result = $this->connection->select(
                "SELECT name FROM sqlite_master WHERE type='table' AND name='migrations'"
            );
            return !empty($result);
        }

        $result = $this->connection->select(
            "SELECT table_name FROM information_schema.tables WHERE table_name = 'migrations'"
        );
        return !empty($result);
    }

    public function log(string $migration, int $batch): void
    {
        $this->connection->statement(
            "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
            [$migration, $batch]
        );
    }

    public function delete(string $migration): void
    {
        $this->connection->statement(
            "DELETE FROM migrations WHERE migration = ?",
            [$migration]
        );
    }

    public function getRan(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        $results = $this->connection->select(
            "SELECT migration FROM migrations ORDER BY batch ASC, migration ASC"
        );

        return array_column($results, 'migration');
    }

    public function getLastBatchNumber(): int
    {
        if (!$this->repositoryExists()) {
            return 0;
        }

        $result = $this->connection->select(
            "SELECT MAX(batch) as max_batch FROM migrations"
        );

        return (int) ($result[0]['max_batch'] ?? 0);
    }

    public function getLastBatch(): array
    {
        if (!$this->repositoryExists()) {
            return [];
        }

        $batch = $this->getLastBatchNumber();

        if ($batch === 0) {
            return [];
        }

        $results = $this->connection->select(
            "SELECT migration FROM migrations WHERE batch = ? ORDER BY migration DESC",
            [$batch]
        );

        return array_column($results, 'migration');
    }

    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }
}
