<?php

declare(strict_types=1);

namespace Switch\Database\ORM\Relation;

use Switch\Database\ORM\Collection;
use Switch\Database\ORM\Model;

abstract class Relation
{
    public function __construct(
        protected readonly Model $parent
    ) {
    }

    abstract public function get(): mixed;

    /**
     * Eager load relationship for an array of parent models in a single query.
     *
     * @param array<int, Model> $models
     * @return Collection
     */
    abstract public function getEager(array $models): Collection;

    /**
     * Match eager loaded relation results back to parent models.
     *
     * @param array<int, Model> $models
     * @param Collection $results
     * @param string $relation
     * @return array<int, Model>
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    public function getRelated(): string
    {
        return $this->relatedClass;
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    public function getLocalKey(): string
    {
        return $this->localKey;
    }
}
