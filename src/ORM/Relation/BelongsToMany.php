<?php

declare(strict_types=1);

namespace Switch\Database\ORM\Relation;

use Switch\Database\ORM\Collection;
use Switch\Database\ORM\Model;

class BelongsToMany extends Relation
{
    public function __construct(
        private readonly string $relatedClass,
        private readonly string $pivotTable,
        private readonly string $foreignPivotKey,
        private readonly string $relatedPivotKey,
        private readonly string $localKey,
        private readonly string $relatedKey,
        Model $parent
    ) {
        parent::__construct($parent);
    }

    public function get(): Collection
    {
        $localValue = $this->parent->getAttribute($this->localKey);
        if ($localValue === null) {
            return new Collection();
        }

        /** @var class-string<Model> $related */
        $related = $this->relatedClass;
        $instance = new $related();
        $relatedTable = $instance->getTable();

        $rows = $related::query()
            ->join($this->pivotTable, "{$relatedTable}.{$this->relatedKey}", '=', "{$this->pivotTable}.{$this->relatedPivotKey}")
            ->where("{$this->pivotTable}.{$this->foreignPivotKey}", $localValue)
            ->select("{$relatedTable}.*")
            ->get();

        return $related::hydrate($rows);
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
        $instance = new $related();
        $relatedTable = $instance->getTable();

        $rows = $related::query()
            ->join($this->pivotTable, "{$relatedTable}.{$this->relatedKey}", '=', "{$this->pivotTable}.{$this->relatedPivotKey}")
            ->whereIn("{$this->pivotTable}.{$this->foreignPivotKey}", array_unique($keys))
            ->select("{$relatedTable}.*", "{$this->pivotTable}.{$this->foreignPivotKey} as _pivot_foreign_key")
            ->get();

        return $related::hydrate($rows);
    }

    public function match(array $models, Collection $results, string $relation): array
    {
        $dictionary = [];
        foreach ($results as $result) {
            if ($result instanceof Model) {
                $pivotKey = $result->getAttribute('_pivot_foreign_key');
                $dictionary[$pivotKey][] = $result;
            }
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $items = $dictionary[$key] ?? [];
            $model->setRelation($relation, new Collection($items));
        }

        return $models;
    }
}
