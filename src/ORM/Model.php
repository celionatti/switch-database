<?php

declare(strict_types=1);

namespace Switch\Database\ORM;

use DateTime;
use RuntimeException;
use Switch\Database\Connection\Connection;
use Switch\Database\ORM\Exception\ModelNotFoundException;
use Switch\Database\ORM\Relation\BelongsTo;
use Switch\Database\ORM\Relation\BelongsToMany;
use Switch\Database\ORM\Relation\HasMany;
use Switch\Database\ORM\Relation\HasOne;
use Switch\Database\ORM\Relation\Relation;
use Switch\Database\Query\Paginator;
use Switch\Database\Query\QueryBuilder;

abstract class Model
{
    protected static ?Connection $connection = null;

    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $original = [];
    protected array $fillable = [];
    protected array $guarded = ['id'];
    protected array $casts = [];
    protected bool $timestamps = true;
    protected bool $softDeletes = false;
    protected bool $exists = false;
    protected array $relations = [];

    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const DELETED_AT = 'deleted_at';

    public static function setConnection(Connection $connection): void
    {
        self::$connection = $connection;
    }

    public static function getConnection(): Connection
    {
        if (self::$connection === null) {
            throw new RuntimeException('Database connection not set for Model.');
        }

        return self::$connection;
    }

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public function getTable(): string
    {
        if ($this->table === '') {
            $class = (new \ReflectionClass($this))->getShortName();
            $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
            $this->table = $snake . 's';
        }

        return $this->table;
    }

    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    public function getKey(): mixed
    {
        return $this->getAttribute($this->primaryKey);
    }

    public function usesSoftDeletes(): bool
    {
        return $this->softDeletes;
    }

    public static function query(): QueryBuilder
    {
        $model = new static();
        return self::getConnection()->table($model->getTable());
    }

    public static function modelQuery(): ModelQueryBuilder
    {
        return new ModelQueryBuilder(static::query(), static::class);
    }

    public static function with(string|array $relations): ModelQueryBuilder
    {
        return static::modelQuery()->with($relations);
    }

    public static function hydrate(array $rows): Collection
    {
        $models = [];
        foreach ($rows as $row) {
            $models[] = static::newModelFromRow($row);
        }
        return new Collection($models);
    }

    public static function newModelFromRow(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;

        return $model;
    }

    public static function find(int|string $id): ?static
    {
        return static::modelQuery()->find($id);
    }

    public static function findOrFail(int|string $id): static
    {
        return static::modelQuery()->findOrFail($id);
    }

    public static function firstOrFail(array $attributes = []): static
    {
        $query = static::modelQuery();
        foreach ($attributes as $key => $val) {
            $query->where($key, $val);
        }
        return $query->firstOrFail();
    }

    public static function firstOrCreate(array $attributes, array $values = []): static
    {
        $query = static::modelQuery();
        foreach ($attributes as $key => $val) {
            $query->where($key, $val);
        }
        $model = $query->first();
        if ($model !== null) {
            return $model;
        }

        return static::create(array_merge($attributes, $values));
    }

    public static function firstOrNew(array $attributes, array $values = []): static
    {
        $query = static::modelQuery();
        foreach ($attributes as $key => $val) {
            $query->where($key, $val);
        }
        $model = $query->first();
        if ($model !== null) {
            return $model;
        }

        return new static(array_merge($attributes, $values));
    }

    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $model = static::firstOrNew($attributes);
        $model->fill($values);
        $model->save();
        return $model;
    }

    public static function firstUpdate(array $attributes, array $values = []): static
    {
        return static::updateOrCreate($attributes, $values);
    }

    public static function all(): Collection
    {
        return static::modelQuery()->get();
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public static function where(string $column, mixed $operatorOrValue = null, mixed $value = null): ModelQueryBuilder
    {
        return static::modelQuery()->where($column, $operatorOrValue, $value);
    }

    public static function whereIn(string $column, array $values): ModelQueryBuilder
    {
        return static::modelQuery()->whereIn($column, $values);
    }

    public static function withTrashed(): ModelQueryBuilder
    {
        return static::modelQuery()->withTrashed();
    }

    public static function onlyTrashed(): ModelQueryBuilder
    {
        return static::modelQuery()->onlyTrashed();
    }

    public static function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        return static::modelQuery()->paginate($perPage, $page);
    }

    public static function chunk(int $count, callable $callback): bool
    {
        return static::modelQuery()->chunk($count, $callback);
    }

    public static function firstWhere(string $column, mixed $operatorOrValue = null, mixed $value = null): ?static
    {
        return static::modelQuery()->firstWhere(...func_get_args());
    }

    public function __call(string $method, array $arguments): mixed
    {
        return static::modelQuery()->$method(...$arguments);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return static::modelQuery()->$method(...$arguments);
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }

        return $this;
    }

    protected function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded, true)) {
            return false;
        }

        if (empty($this->fillable)) {
            return true;
        }

        return in_array($key, $this->fillable, true);
    }

    public function save(): bool
    {
        $query = self::query();

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!$this->exists) {
                $this->attributes[static::CREATED_AT] = $now;
            }
            $this->attributes[static::UPDATED_AT] = $now;
        }

        if ($this->exists) {
            $dirty = $this->getDirty();
            if (empty($dirty)) {
                return true;
            }

            $affected = $query->where($this->primaryKey, '=', $this->getKey())->update($dirty);
            $this->original = $this->attributes;
            return $affected > 0;
        }

        $id = $query->insert($this->attributes);
        if ($id) {
            $this->attributes[$this->primaryKey] = $id;
            $this->exists = true;
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        if ($this->softDeletes) {
            $this->setAttribute(static::DELETED_AT, date('Y-m-d H:i:s'));
            return $this->save();
        }

        $affected = self::query()->where($this->primaryKey, '=', $this->getKey())->delete();
        if ($affected > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    public function restore(): bool
    {
        if (!$this->softDeletes || !$this->exists) {
            return false;
        }

        $this->setAttribute(static::DELETED_AT, null);
        return $this->save();
    }

    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $affected = self::query()->where($this->primaryKey, '=', $this->getKey())->delete();
        if ($affected > 0) {
            $this->exists = false;
            return true;
        }

        return false;
    }

    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function getRawAttributes(): array
    {
        return $this->attributes;
    }

    public function isDirty(?string $attribute = null): bool
    {
        $dirty = $this->getDirty();
        if ($attribute === null) {
            return !empty($dirty);
        }

        return array_key_exists($attribute, $dirty);
    }

    public function getAttribute(string $key): mixed
    {
        $studlyKey = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));
        $accessor = 'get' . $studlyKey . 'Attribute';

        if (method_exists($this, $accessor)) {
            $value = $this->attributes[$key] ?? null;
            return $this->$accessor($value);
        }

        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }

        $value = $this->attributes[$key];

        if (isset($this->casts[$key])) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $studlyKey = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));
        $mutator = 'set' . $studlyKey . 'Attribute';

        if (method_exists($this, $mutator)) {
            $this->$mutator($value);
            return $this;
        }

        $this->attributes[$key] = $value;
        return $this;
    }

    public function setRelation(string $relation, mixed $value): self
    {
        $this->relations[$relation] = $value;
        return $this;
    }

    public function getRelation(string $relation): mixed
    {
        return $this->relations[$relation] ?? null;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->relations);
    }

    private function castAttribute(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $castType = strtolower($this->casts[$key]);

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'bool', 'boolean' => (bool) $value,
            'float', 'double' => (float) $value,
            'string' => (string) $value,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => is_string($value) ? new DateTime($value) : $value,
            default => $value,
        };
    }

    public function toArray(): array
    {
        $array = [];
        foreach ($this->attributes as $key => $value) {
            $array[$key] = $this->getAttribute($key);
        }

        foreach ($this->relations as $relationName => $relationValue) {
            if ($relationValue instanceof Collection) {
                $array[$relationName] = $relationValue->toArray();
            } elseif ($relationValue instanceof Model) {
                $array[$relationName] = $relationValue->toArray();
            } else {
                $array[$relationName] = $relationValue;
            }
        }

        return $array;
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __get(string $key): mixed
    {
        $studlyKey = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $key)));
        $accessor = 'get' . $studlyKey . 'Attribute';

        if (method_exists($this, $accessor) || array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        if ($this->relationLoaded($key)) {
            return $this->getRelation($key);
        }

        if (method_exists($this, $key)) {
            $relation = $this->$key();
            if ($relation instanceof Relation) {
                $result = $relation->get();
                $this->setRelation($key, $result);
                return $result;
            }
        }

        return null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]) || $this->relationLoaded($key);
    }

    protected function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = new $related();
        $foreignKey ??= $this->getTable() . '_id';
        $localKey ??= $this->primaryKey;

        return new HasOne($related, $foreignKey, $localKey, $this);
    }

    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey ??= strtolower((new \ReflectionClass($this))->getShortName()) . '_id';
        $localKey ??= $this->primaryKey;

        return new HasMany($related, $foreignKey, $localKey, $this);
    }

    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $instance = new $related();
        $foreignKey ??= strtolower((new \ReflectionClass($instance))->getShortName()) . '_id';
        $ownerKey ??= $instance->getKeyName();

        return new BelongsTo($related, $foreignKey, $ownerKey, $this);
    }

    protected function belongsToMany(
        string $related,
        ?string $pivotTable = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $localKey = null,
        ?string $relatedKey = null
    ): BelongsToMany {
        $instance = new $related();
        $parentClass = strtolower((new \ReflectionClass($this))->getShortName());
        $relatedClass = strtolower((new \ReflectionClass($instance))->getShortName());

        $tables = [$parentClass, $relatedClass];
        sort($tables);
        $pivotTable ??= implode('_', $tables);

        $foreignPivotKey ??= $parentClass . '_id';
        $relatedPivotKey ??= $relatedClass . '_id';

        $localKey ??= $this->primaryKey;
        $relatedKey ??= $instance->getKeyName();

        return new BelongsToMany($related, $pivotTable, $foreignPivotKey, $relatedPivotKey, $localKey, $relatedKey, $this);
    }
}
