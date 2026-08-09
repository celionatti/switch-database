<?php

declare(strict_types=1);

namespace Switch\Database\Query\Grammar;

use Switch\Database\Query\Grammar;
use Switch\Database\Query\QueryBuilder;

class PostgresGrammar extends Grammar
{
    /**
     * PostgreSQL supports ILIKE for case-insensitive pattern matching.
     */
    protected function whereILike(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' ILIKE ?';
    }

    /**
     * PostgreSQL returns auto-increment IDs via RETURNING clause.
     */
    public function compileInsertGetId(QueryBuilder $query, array $values, string $sequence = 'id'): string
    {
        return $this->compileInsert($query, $values) . ' RETURNING ' . $this->wrap($sequence);
    }
}
