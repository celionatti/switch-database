<?php

declare(strict_types=1);

namespace Switch\Database\Migration;

use Switch\Database\Connection\Connection;

class Migrator
{
    private MigrationRepository $repository;
    private MigrationRunner $runner;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $migrationDirectory
    ) {
        $this->repository = new MigrationRepository($this->connection);
        $this->runner = new MigrationRunner($this->connection, $this->repository);
    }

    /**
     * Run all pending migrations from the directory.
     *
     * @return array<string> List of executed migration names
     */
    public function run(): array
    {
        $migrations = $this->loadMigrationFiles();
        return $this->runner->run($migrations);
    }

    /**
     * Rollback the last batch of migrations.
     *
     * @return array<string> List of rolled-back migration names
     */
    public function rollback(): array
    {
        $migrations = $this->loadMigrationFiles();
        return $this->runner->rollback($migrations);
    }

    /**
     * Load migration classes from PHP files in the migrations directory.
     *
     * @return array<string, Migration>
     */
    private function loadMigrationFiles(): array
    {
        if (!is_dir($this->migrationDirectory)) {
            return [];
        }

        $files = glob($this->migrationDirectory . '/*.php') ?: [];
        sort($files);

        $migrations = [];

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $classesBefore = get_declared_classes();
            require_once $file;
            $classesAfter = get_declared_classes();

            $newClasses = array_diff($classesAfter, $classesBefore);

            foreach ($newClasses as $class) {
                if (is_subclass_of($class, Migration::class)) {
                    $migrations[$name] = new $class();
                    break;
                }
            }
        }

        return $migrations;
    }
}
