<?php

declare(strict_types=1);

namespace Switch\Database\Seeder;

use Switch\Database\Connection\Connection;

class SeederRunner
{
    public function __construct(
        private readonly ?Connection $connection = null
    ) {
    }

    /**
     * Run a specific seeder class.
     *
     * @param class-string<Seeder> $class
     */
    public function run(string $class): void
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Seeder class [{$class}] does not exist.");
        }

        $seeder = new $class($this->connection);
        if (!$seeder instanceof Seeder) {
            throw new \InvalidArgumentException("Class [{$class}] must extend " . Seeder::class);
        }

        $seeder->run();
    }
}
