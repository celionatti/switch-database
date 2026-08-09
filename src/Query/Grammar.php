<?php

declare(strict_types=1);

namespace Switch\Database\Query;

class Grammar
{
    public function compileSelect(QueryBuilder $query): string
    {
        $components = [
            $this->compileAggregate($query->aggregate),
            $this->compileColumns($query),
            $this->compileFrom($query),
            $this->compileJoins($query),
            $this->compileWheres($query),
            $this->compileGroups($query),
            $this->compileHavings($query),
            $this->compileOrders($query),
            $this->compileLimit($query),
            $this->compileOffset($query),
        ];

        return trim(implode(' ', array_filter($components)));
    }

    public function compileInsert(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "INSERT INTO {$table} DEFAULT VALUES";
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = array_keys(reset($values));
        $columnsString = implode(', ', array_map([$this, 'wrap'], $columns));

        $parameters = [];
        foreach ($values as $record) {
            $recordParameters = [];
            foreach ($record as $value) {
                $recordParameters[] = $value instanceof Expression ? $value->getValue() : '?';
            }
            $parameters[] = '(' . implode(', ', $recordParameters) . ')';
        }

        $parametersString = implode(', ', $parameters);

        return "INSERT INTO {$table} ({$columnsString}) VALUES {$parametersString}";
    }

    public function compileInsertGetId(QueryBuilder $query, array $values, string $sequence = 'id'): string
    {
        return $this->compileInsert($query, $values);
    }

    public function compileUpdate(QueryBuilder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = [];
        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key) . ' = ' . ($value instanceof Expression ? $value->getValue() : '?');
        }

        $columnsString = implode(', ', $columns);
        $wheres = $this->compileWheres($query);

        return trim("UPDATE {$table} SET {$columnsString} {$wheres}");
    }

    public function compileDelete(QueryBuilder $query): string
    {
        $table = $this->wrapTable($query->from);
        $wheres = $this->compileWheres($query);

        return trim("DELETE FROM {$table} {$wheres}");
    }

    protected function compileAggregate(?array $aggregate): string
    {
        if (!$aggregate) {
            return '';
        }

        $column = $this->columnize([$aggregate['column']]);

        return "SELECT {$aggregate['function']}({$column}) AS aggregate";
    }

    protected function compileColumns(QueryBuilder $query): string
    {
        if ($query->aggregate) {
            return '';
        }

        $select = $query->distinct ? 'SELECT DISTINCT ' : 'SELECT ';

        if (empty($query->columns)) {
            return $select . '*';
        }

        return $select . $this->columnize($query->columns);
    }

    protected function compileFrom(QueryBuilder $query): string
    {
        if (!$query->from) {
            return '';
        }

        return 'FROM ' . $this->wrapTable($query->from);
    }

    protected function compileJoins(QueryBuilder $query): string
    {
        if (empty($query->joins)) {
            return '';
        }

        $joins = [];

        foreach ($query->joins as $join) {
            $table = $this->wrapTable($join->getTable());
            $clauses = [];

            foreach ($join->getClauses() as $index => $clause) {
                $boolean = $index > 0 ? strtoupper($clause['boolean']) . ' ' : 'ON ';
                $clauses[] = $boolean . $this->wrap($clause['first']) . ' ' . $clause['operator'] . ' ' . $this->wrap($clause['second']);
            }

            $type = strtoupper($join->getType());
            $joinClauses = implode(' ', $clauses);

            $joins[] = "{$type} JOIN {$table} {$joinClauses}";
        }

        return implode(' ', $joins);
    }

    protected function compileWheres(QueryBuilder $query): string
    {
        if (empty($query->wheres)) {
            return '';
        }

        $sql = [];

        foreach ($query->wheres as $where) {
            $sql[] = $where['boolean'] . ' ' . $this->{"where{$where['type']}"}($query, $where);
        }

        if (!empty($sql)) {
            return 'WHERE ' . preg_replace('/and |or /i', '', implode(' ', $sql), 1);
        }

        return '';
    }

    protected function whereBasic(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' ' . $where['operator'] . ' ?';
    }

    protected function whereIn(QueryBuilder $query, array $where): string
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
        return $this->wrap($where['column']) . ' IN (' . $placeholders . ')';
    }

    protected function whereNotIn(QueryBuilder $query, array $where): string
    {
        if (empty($where['values'])) {
            return '1 = 1';
        }
        $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
        return $this->wrap($where['column']) . ' NOT IN (' . $placeholders . ')';
    }

    protected function whereNull(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' IS NULL';
    }

    protected function whereNotNull(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' IS NOT NULL';
    }

    protected function whereBetween(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' BETWEEN ? AND ?';
    }

    protected function whereLike(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' LIKE ?';
    }

    protected function whereILike(QueryBuilder $query, array $where): string
    {
        return $this->wrap($where['column']) . ' LIKE ?';
    }

    protected function whereExists(QueryBuilder $query, array $where): string
    {
        return 'EXISTS (' . $where['query']->toSql() . ')';
    }

    protected function whereNotExists(QueryBuilder $query, array $where): string
    {
        return 'NOT EXISTS (' . $where['query']->toSql() . ')';
    }

    protected function whereRaw(QueryBuilder $query, array $where): string
    {
        return $where['sql'];
    }

    protected function compileGroups(QueryBuilder $query): string
    {
        if (empty($query->groups)) {
            return '';
        }

        return 'GROUP BY ' . $this->columnize($query->groups);
    }

    protected function compileHavings(QueryBuilder $query): string
    {
        if (empty($query->havings)) {
            return '';
        }

        $sql = [];

        foreach ($query->havings as $having) {
            $sql[] = $having['boolean'] . ' ' . $this->wrap($having['column']) . ' ' . $having['operator'] . ' ?';
        }

        return 'HAVING ' . preg_replace('/and |or /i', '', implode(' ', $sql), 1);
    }

    protected function compileOrders(QueryBuilder $query): string
    {
        if (empty($query->orders)) {
            return '';
        }

        $orders = [];

        foreach ($query->orders as $order) {
            $orders[] = $this->wrap($order['column']) . ' ' . strtoupper($order['direction']);
        }

        return 'ORDER BY ' . implode(', ', $orders);
    }

    protected function compileLimit(QueryBuilder $query): string
    {
        if ($query->limit === null) {
            return '';
        }

        return 'LIMIT ' . (int) $query->limit;
    }

    protected function compileOffset(QueryBuilder $query): string
    {
        if ($query->offset === null) {
            return '';
        }

        return 'OFFSET ' . (int) $query->offset;
    }

    public function wrapTable(string|Expression $table): string
    {
        if ($table instanceof Expression) {
            return $table->getValue();
        }

        return $this->wrap($table);
    }

    public function wrap(string|Expression $value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        if (str_contains($value, ' as ')) {
            [$column, $alias] = explode(' as ', $value);
            return $this->wrap(trim($column)) . ' AS ' . $this->wrapValue(trim($alias));
        }

        if (str_contains($value, '.')) {
            return implode('.', array_map([$this, 'wrapValue'], explode('.', $value)));
        }

        return $this->wrapValue($value);
    }

    protected function wrapValue(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }

    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }
}
