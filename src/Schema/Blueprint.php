<?php

declare(strict_types=1);

namespace Switch\Database\Schema;

class Blueprint
{
    /** @var array<int, ColumnDefinition> */
    private array $columns = [];

    public function __construct(private readonly string $table)
    {
    }

    public function id(string $column = 'id'): ColumnDefinition
    {
        $definition = (new ColumnDefinition($column, 'integer'))->primary()->autoIncrement();
        $this->columns[] = $definition;
        return $definition;
    }

    public function string(string $column, int $length = 255): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'varchar', length: $length);
        $this->columns[] = $definition;
        return $definition;
    }

    public function text(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'text');
        $this->columns[] = $definition;
        return $definition;
    }

    public function longText(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'longtext');
        $this->columns[] = $definition;
        return $definition;
    }

    public function integer(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'integer');
        $this->columns[] = $definition;
        return $definition;
    }

    public function bigInteger(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'bigint');
        $this->columns[] = $definition;
        return $definition;
    }

    public function tinyInteger(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'tinyint');
        $this->columns[] = $definition;
        return $definition;
    }

    public function float(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'float');
        $this->columns[] = $definition;
        return $definition;
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'decimal', precision: $precision, scale: $scale);
        $this->columns[] = $definition;
        return $definition;
    }

    public function boolean(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'boolean');
        $this->columns[] = $definition;
        return $definition;
    }

    public function date(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'date');
        $this->columns[] = $definition;
        return $definition;
    }

    public function dateTime(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'datetime');
        $this->columns[] = $definition;
        return $definition;
    }

    public function timestamp(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'timestamp');
        $this->columns[] = $definition;
        return $definition;
    }

    public function json(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'json');
        $this->columns[] = $definition;
        return $definition;
    }

    public function jsonb(string $column): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'jsonb');
        $this->columns[] = $definition;
        return $definition;
    }

    public function uuid(string $column = 'uuid'): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'uuid');
        $this->columns[] = $definition;
        return $definition;
    }

    public function enum(string $column, array $values): ColumnDefinition
    {
        $definition = new ColumnDefinition($column, 'enum', values: $values);
        $this->columns[] = $definition;
        return $definition;
    }

    public function timestamps(): void
    {
        $this->dateTime('created_at')->nullable();
        $this->dateTime('updated_at')->nullable();
    }

    public function softDeletes(): void
    {
        $this->dateTime('deleted_at')->nullable();
    }

    /**
     * @return ColumnDefinition[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getTableName(): string
    {
        return $this->table;
    }

    public function toSql(string $tableName): string
    {
        $columnsSql = array_map(fn (ColumnDefinition $col) => $col->toSql(), $this->columns);
        
        return sprintf(
            'CREATE TABLE %s (%s)',
            $tableName,
            implode(', ', $columnsSql)
        );
    }
}
