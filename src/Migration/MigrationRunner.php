<?php

declare(strict_types=1);

namespace Switch\Database\Migration;

use Switch\Database\Connection\Connection;
use Switch\Database\Schema\SchemaBuilder;

class MigrationRunner
{
    private SchemaBuilder $schemaBuilder;

    /**
     * @var array<string, Migration>
     */
    private array $registeredMigrations = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly MigrationRepository $repository
    ) {
        $this->schemaBuilder = new SchemaBuilder($connection);
    }

    /**
     * @param array<string, Migration> $migrations Array of migration name to Migration object
     * @return array<string> List of executed migrations
     */
    public function run(array $migrations): array
    {
        $this->registeredMigrations = array_merge($this->registeredMigrations, $migrations);

        if (!$this->repository->repositoryExists()) {
            $this->repository->createRepository();
        }

        $ran = $this->repository->getRan();
        $pending = array_diff(array_keys($migrations), $ran);

        if (empty($pending)) {
            return [];
        }

        $batch = $this->repository->getNextBatchNumber();
        $executed = [];

        foreach ($pending as $name) {
            $migration = $migrations[$name];
            
            $migration->up($this->schemaBuilder);
            $this->repository->log($name, $batch);
            
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * @param array<string, Migration> $migrations Array of migration name to Migration object
     * @return array<string> List of rolled back migrations
     */
    public function rollback(array $migrations = []): array
    {
        $migrations = array_merge($this->registeredMigrations, $migrations);

        if (!$this->repository->repositoryExists()) {
            return [];
        }

        $lastBatch = $this->repository->getLastBatch();

        if (empty($lastBatch)) {
            return [];
        }

        $rolledBack = [];

        foreach ($lastBatch as $name) {
            if (isset($migrations[$name])) {
                $migration = $migrations[$name];
                
                $migration->down($this->schemaBuilder);
                $this->repository->delete($name);
                
                $rolledBack[] = $name;
            }
        }

        return $rolledBack;
    }

    /**
     * @param array<string, Migration> $migrations Array of migration name to Migration object
     * @return array<string> List of reset migrations
     */
    public function reset(array $migrations = []): array
    {
        $migrations = array_merge($this->registeredMigrations, $migrations);

        if (!$this->repository->repositoryExists()) {
            return [];
        }

        $ran = $this->repository->getRan();
        $ran = array_reverse($ran);

        if (empty($ran)) {
            return [];
        }

        $rolledBack = [];

        foreach ($ran as $name) {
            if (isset($migrations[$name])) {
                $migration = $migrations[$name];
                
                $migration->down($this->schemaBuilder);
                $this->repository->delete($name);
                
                $rolledBack[] = $name;
            }
        }

        return $rolledBack;
    }
}
