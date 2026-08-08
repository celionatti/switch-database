<?php

declare(strict_types=1);

namespace Switch\Database\ORM\Relation;

use Switch\Database\ORM\Collection;
use Switch\Database\ORM\Model;

class HasOne extends Relation
{
    public function __construct(
        private readonly string $relatedClass,
        private readonly string $foreignKey,
        private readonly string $localKey,
        Model $parent
    ) {
        parent::__construct($parent);
    }

    public function get(): ?Model
    {
        $localValue = $this->parent->getAttribute($this->localKey);
        if ($localValue === null) {
            return null;
        }

        /** @var class-string<Model> $related */
        $related = $this->relatedClass;
        return $related::where($this->foreignKey, $localValue)->firstModel();
    }

    public function getEager(array $models): Collection
    {
        $keys = [];
        foreach ($models as $model) {
            $val = $model->getAttribute($this->localKey);
            if ($val !== null) {
                $keys[] = $val;
            }
        }

        if (empty($keys)) {
            return new Collection();
        }

        /** @var class-string<Model> $related */
        $related = $this->relatedClass;
        return $related::whereIn($this->foreignKey, array_unique($keys))->getModels();
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            if ($result instanceof Model) {
                $foreignVal = $result->getAttribute($this->foreignKey);
                $dictionary[$foreignVal] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }

        return $models;
    }
}
