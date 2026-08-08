<?php

declare(strict_types=1);

namespace Switch\Database\Query;

class JoinClause
{
    /** @var array<int, array{first: string, operator: string, second: string, boolean: string}> */
    protected array $clauses = [];

    public function __construct(
        protected readonly string $type,
        protected readonly string $table
    ) {}

    public function on(string $first, string $operator, string $second): self
    {
        $this->clauses[] = [
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => 'and',
        ];

        return $this;
    }

    public function orOn(string $first, string $operator, string $second): self
    {
        $this->clauses[] = [
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
            'boolean' => 'or',
        ];

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getClauses(): array
    {
        return $this->clauses;
    }
}
