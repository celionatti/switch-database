<?php

declare(strict_types=1);

namespace Switch\Database\ORM;

use Switch\Foundation\Collection\Collection as BaseCollection;

/**
 * Database Eloquent-style Model Collection.
 *
 * @template TKey of array-key
 * @template TModel of Model
 * @extends BaseCollection<TKey, TModel>
 */
class Collection extends BaseCollection
{
    /**
     * Find a model in the collection by its primary key.
     */
    public function find(mixed $key, mixed $default = null): ?Model
    {
        if ($key instanceof Model) {
            $key = $key->getKey();
        }

        if (is_array($key)) {
            if ($this->isEmpty()) {
                return null;
            }

            return $this->whereIn($this->first()->getKeyName(), $key);
        }

        return $this->first(function ($model) use ($key) {
            return $model instanceof Model && $model->getKey() == $key;
        }, $default);
    }

    /**
     * Get an array with the values of a given column / model keys.
     */
    public function modelKeys(): array
    {
        return array_map(function ($model) {
            return $model instanceof Model ? $model->getKey() : null;
        }, $this->items);
    }

    /**
     * Load a set of relationships onto the collection.
     */
    public function load(string|array ...$relations): static
    {
        if ($this->isNotEmpty()) {
            $first = $this->first();
            if ($first instanceof Model) {
                $query = $first->newQuery()->with(...$relations);
                $query->eagerLoadRelations($this->items);
            }
        }

        return $this;
    }

    /**
     * Load a set of relationships onto the collection if not already loaded.
     */
    public function loadMissing(string|array ...$relations): static
    {
        return $this->load(...$relations);
    }

    /**
     * Reload a fresh model instance from the database for all the entities.
     */
    public function fresh(array|string ...$with): static
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $model = $this->first();
        if (!$model instanceof Model) {
            return new static();
        }

        $freshModels = $model->newQuery()
            ->whereIn($model->getKeyName(), $this->modelKeys())
            ->with(...$with)
            ->get();

        return new static($freshModels);
    }
}
