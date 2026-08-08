<?php

declare(strict_types=1);

namespace Switch\Database\ORM;

use Switch\Database\ORM\Exception\ModelNotFoundException;
use Switch\Database\ORM\Relation\Relation;
use Switch\Database\Query\Paginator;
use Switch\Database\Query\QueryBuilder;

class ModelQueryBuilder
{
    /**
     * @var array<int, string>
     */
    private array $eagerLoad = [];

    private bool $withTrashed = false;
    private bool $onlyTrashed = false;

    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        private readonly QueryBuilder $query,
        private readonly string $modelClass
    ) {
    }

    public function with(string|array $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();
        $this->eagerLoad = array_merge($this->eagerLoad, $relations);
        return $this;
    }

    public function withTrashed(): self
    {
        $this->withTrashed = true;
        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->onlyTrashed = true;
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->query->where($column, '=', $operatorOrValue);
        } else {
            $this->query->where($column, $operatorOrValue, $value);
        }
        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $this->query->orWhere($column, '=', $operatorOrValue);
        } else {
            $this->query->orWhere($column, $operatorOrValue, $value);
        }
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->query->whereNull($column);
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->query->whereNotNull($column);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->query->limit($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->query->offset($offset);
        return $this;
    }

    public function get(): Collection
    {
        $model = new ($this->modelClass)();
        if ($model->usesSoftDeletes()) {
            if ($this->onlyTrashed) {
                $this->query->whereNotNull('deleted_at');
            } elseif (!$this->withTrashed) {
                $this->query->whereNull('deleted_at');
            }
        }

        $rows = $this->query->get();
        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->modelClass::newModelFromRow($row);
        }

        $collection = new Collection($models);

        if (!empty($this->eagerLoad) && !empty($models)) {
            $this->eagerLoadRelations($models, $this->eagerLoad);
        }

        return $collection;
    }

    public function getModels(): Collection
    {
        return $this->get();
    }

    public function first(): ?Model
    {
        $collection = $this->limit(1)->get();
        return $collection->first();
    }

    public function firstOrFail(): Model
    {
        $model = $this->first();
        if ($model === null) {
            throw new ModelNotFoundException($this->modelClass);
        }

        return $model;
    }

    public function find(int|string $id): ?Model
    {
        $model = new ($this->modelClass)();
        return $this->where($model->getKeyName(), $id)->first();
    }

    public function findOrFail(int|string $id): Model
    {
        $model = $this->find($id);
        if ($model === null) {
            throw new ModelNotFoundException($this->modelClass, [$id]);
        }

        return $model;
    }

    public function count(string $column = '*'): int
    {
        return $this->query->count($column);
    }

    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $total = $this->count();
        $offset = ($page - 1) * $perPage;

        $items = $this->limit($perPage)->offset($offset)->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->limit($count)->offset(($page - 1) * $count)->get();
            $countResults = count($results);

            if ($countResults === 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults === $count);

        return true;
    }

    /**
     * @param array<int, Model> $models
     * @param array<int, string> $relations
     */
    private function eagerLoadRelations(array $models, array $relations): void
    {
        foreach ($relations as $relationName) {
            $sampleModel = $models[0];
            if (method_exists($sampleModel, $relationName)) {
                /** @var Relation $relation */
                $relation = $sampleModel->$relationName();
                $results = $relation->getEager($models);
                $relation->match($models, $results, $relationName);
            }
        }
    }
}
