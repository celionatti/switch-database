<?php

declare(strict_types=1);

namespace Switch\Database\ORM\Relation;

use Switch\Database\ORM\Collection;
use Switch\Database\ORM\Model;

class BelongsTo extends Relation
{
    public function __construct(
        protected readonly string $relatedClass,
        protected readonly string $foreignKey,
        protected readonly string $ownerKey,
        Model $parent
    ) {
        parent::__construct($parent);
    }

    public function getLocalKey(): string
    {
        return $this->ownerKey;
    }

    public function get(): ?Model
    {
        $foreignValue = $this->parent->getAttribute($this->foreignKey);
        if ($foreignValue === null) {
            return null;
        }

        /** @var class-string<Model> $related */
        $related = $this->relatedClass;
        return $related::where($this->ownerKey, $foreignValue)->firstModel();
    }

    public function getEager(array $models): Collection
    {
        $keys = [];
        foreach ($models as $model) {
            $val = $model->getAttribute($this->foreignKey);
            if ($val !== null) {
                $keys[] = $val;
            }
        }

        if (empty($keys)) {
            return new Collection();
        }

        /** @var class-string<Model> $related */
        $related = $this->relatedClass;
        return $related::whereIn($this->ownerKey, array_unique($keys))->getModels();
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            if ($result instanceof Model) {
                $ownerVal = $result->getAttribute($this->ownerKey);
                $dictionary[$ownerVal] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            $model->setRelation($relation, $dictionary[$key] ?? null);
        }

        return $models;
    }
}
