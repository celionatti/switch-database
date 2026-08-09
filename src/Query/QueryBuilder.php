<?php

declare(strict_types=1);

namespace Switch\Database\Query;

use Switch\Database\Connection\Connection;

class QueryBuilder
{
    public string $from = '';
    public array $columns = [];
    public bool $distinct = false;
    /** @var JoinClause[] */
    public array $joins = [];
    public array $wheres = [];
    public array $groups = [];
    public array $havings = [];
    public array $orders = [];
    public ?int $limit = null;
    public ?int $offset = null;
    public ?array $aggregate = null;
    public array $bindings = [
        'select' => [],
        'join' => [],
        'where' => [],
        'having' => [],
        'order' => [],
    ];

    public function __construct(
        protected Connection $connection,
        protected Grammar $grammar,
        ?string $table = null
    ) {
        if ($table !== null) {
            $this->from($table);
        }
    }

    public function table(string $table): self
    {
        $this->from = $table;
        return $this;
    }

    public function from(string $table): self
    {
        return $this->table($table);
    }

    public function select(string|array ...$columns): self
    {
        $this->columns = [];
        $columns = is_array($columns[0] ?? null) ? $columns[0] : $columns;
        
        foreach ($columns as $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    public function addSelect(string|array ...$columns): self
    {
        $columns = is_array($columns[0] ?? null) ? $columns[0] : $columns;
        
        foreach ($columns as $column) {
            $this->columns[] = $column;
        }

        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    public function where(string $column, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (func_num_args() === 2 || $value === null) {
            $value = $operatorOrValue;
            $operatorOrValue = '=';
        }

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operatorOrValue,
            'boolean' => 'and',
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function orWhere(string $column, mixed $operatorOrValue = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operatorOrValue;
            $operatorOrValue = '=';
        }

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operatorOrValue,
            'boolean' => 'or',
        ];

        $this->addBinding($value, 'where');

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and',
        ];

        foreach ($values as $value) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'NotIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'and',
        ];

        foreach ($values as $value) {
            $this->addBinding($value, 'where');
        }

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'Null',
            'column' => $column,
            'boolean' => 'and',
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'NotNull',
            'column' => $column,
            'boolean' => 'and',
        ];

        return $this;
    }

    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $this->wheres[] = [
            'type' => 'Between',
            'column' => $column,
            'boolean' => 'and',
        ];

        $this->addBinding([$min, $max], 'where');

        return $this;
    }

    public function whereLike(string $column, string $pattern): self
    {
        $this->wheres[] = [
            'type' => 'Like',
            'column' => $column,
            'boolean' => 'and',
        ];

        $this->addBinding($pattern, 'where');

        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'boolean' => 'and',
        ];

        if (!empty($bindings)) {
            $this->addBinding($bindings, 'where');
        }

        return $this;
    }

    public function whereExists(QueryBuilder $query): self
    {
        $this->wheres[] = [
            'type' => 'Exists',
            'query' => $query,
            'boolean' => 'and',
        ];

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    public function whereNotExists(QueryBuilder $query): self
    {
        $this->wheres[] = [
            'type' => 'NotExists',
            'query' => $query,
            'boolean' => 'and',
        ];

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    public function whereILike(string $column, string $pattern): self
    {
        $this->wheres[] = [
            'type' => 'ILike',
            'column' => $column,
            'boolean' => 'and',
        ];

        $this->addBinding($pattern, 'where');

        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second): self
    {
        $join = new JoinClause('inner', $table);
        $join->on($first, $operator, $second);
        $this->joins[] = $join;
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $join = new JoinClause('left', $table);
        $join->on($first, $operator, $second);
        $this->joins[] = $join;
        return $this;
    }

    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        $join = new JoinClause('right', $table);
        $join->on($first, $operator, $second);
        $this->joins[] = $join;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $column;
        }
        return $this;
    }

    public function having(string $column, string $operator, mixed $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'boolean' => 'and',
        ];
        $this->addBinding($value, 'having');
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    public function first(): ?array
    {
        $this->limit(1);
        $result = $this->get();
        return !empty($result) ? reset($result) : null;
    }

    public function value(string $column): mixed
    {
        $result = $this->first();
        return $result ? ($result[$column] ?? null) : null;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        $results = $this->get();
        $plucked = [];
        
        foreach ($results as $result) {
            if ($key !== null && isset($result[$key])) {
                $plucked[$result[$key]] = $result[$column];
            } else {
                $plucked[] = $result[$column];
            }
        }
        
        return $plucked;
    }

    public function exists(): bool
    {
        $this->limit(1);
        $result = $this->connection->select($this->toSql(), $this->getBindings());
        return !empty($result);
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate(__FUNCTION__, [$column]);
    }

    public function sum(string $column): float
    {
        return (float) $this->aggregate(__FUNCTION__, [$column]);
    }

    public function avg(string $column): float
    {
        return (float) $this->aggregate(__FUNCTION__, [$column]);
    }

    public function min(string $column): mixed
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    public function max(string $column): mixed
    {
        return $this->aggregate(__FUNCTION__, [$column]);
    }

    protected function aggregate(string $function, array $columns): mixed
    {
        $this->aggregate = ['function' => strtoupper($function), 'column' => $columns[0]];
        
        $result = $this->connection->select($this->toSql(), $this->getBindings());
        
        $this->aggregate = null;

        if (empty($result)) {
            return null;
        }

        $row = (array) reset($result);
        return reset($row);
    }

    public function insert(array $values): int
    {
        $sql = $this->grammar->compileInsert($this, $values);
        $flatValues = is_array(reset($values)) ? array_merge(...array_map('array_values', $values)) : array_values($values);
        $bindings = $this->cleanBindings($flatValues);

        return (int) $this->connection->insert($sql, $bindings);
    }

    public function insertGetId(array $values, string $sequence = 'id'): int|string
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);
        $flatValues = is_array(reset($values)) ? array_merge(...array_map('array_values', $values)) : array_values($values);
        $bindings = $this->cleanBindings($flatValues);

        if ($this->grammar instanceof \Switch\Database\Query\Grammar\PostgresGrammar) {
            $result = $this->connection->select($sql, $bindings);
            return reset($result)[$sequence] ?? 0;
        }

        return $this->connection->insert($sql, $bindings);
    }

    public function __call(string $method, array $arguments): mixed
    {
        if (str_starts_with($method, 'where')) {
            $finder = substr($method, 5); // e.g. "Status" or "StatusAndRole"
            $parts = explode('And', $finder);

            foreach ($parts as $index => $part) {
                // Convert CamelCase column name to snake_case
                $column = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $part));
                $value = $arguments[$index] ?? null;
                $this->where($column, '=', $value);
            }

            return $this;
        }

        if (str_starts_with($method, 'findBy')) {
            $column = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', substr($method, 6)));
            return $this->where($column, '=', $arguments[0] ?? null)->first();
        }

        throw new \BadMethodCallException("Method {$method} does not exist on QueryBuilder.");
    }

    public function update(array $values): int
    {
        $sql = $this->grammar->compileUpdate($this, $values);
        $bindings = array_merge($this->cleanBindings(array_values($values)), $this->getBindings());

        return $this->connection->update($sql, $bindings);
    }

    public function delete(): int
    {
        $sql = $this->grammar->compileDelete($this);
        return $this->connection->delete($sql, $this->getBindings());
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

    public function toSql(): string
    {
        return $this->grammar->compileSelect($this);
    }

    public function getBindings(): array
    {
        return array_values(array_merge(
            $this->bindings['join'],
            $this->bindings['where'],
            $this->bindings['having']
        ));
    }

    public function addBinding(mixed $value, string $type = 'where'): self
    {
        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }
        return $this;
    }

    public function raw(string $expression): Expression
    {
        return new Expression($expression);
    }

    protected function cleanBindings(array $bindings): array
    {
        return array_values(array_filter($bindings, function ($binding) {
            return !($binding instanceof Expression);
        }));
    }
}
