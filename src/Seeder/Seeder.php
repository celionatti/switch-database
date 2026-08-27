<?php

declare(strict_types=1);

namespace Switch\Database\Seeder;

use Switch\Database\Connection\Connection;
use Switch\Database\ORM\Model;

abstract class Seeder
{
    protected ?Connection $connection = null;

    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection ?? Model::getConnection();
    }

    /**
     * Run the database seeders.
     */
    abstract public function run(): void;

    /**
     * Run one or more additional seeders.
     *
     * @param class-string<Seeder>|array<int, class-string<Seeder>> $class
     */
    public function call(string|array $class): void
    {
        $classes = is_array($class) ? $class : [$class];

        foreach ($classes as $seederClass) {
            if (!class_exists($seederClass)) {
                throw new \InvalidArgumentException("Seeder class [{$seederClass}] does not exist.");
            }

            /** @var Seeder $seeder */
            $seeder = new $seederClass($this->connection);
            $seeder->run();
        }
    }
}
