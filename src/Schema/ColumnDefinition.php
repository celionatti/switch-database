<?php

declare(strict_types=1);

namespace Switch\Database\Schema;

class ColumnDefinition
{
    public bool $nullable = false;
    public mixed $default = null;
    public bool $unsigned = false;
    public bool $primary = false;
    public bool $unique = false;
    public bool $index = false;
    public bool $autoIncrement = false;
    public ?string $after = null;
    public ?string $comment = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly array $values = []
    ) {
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;
        return $this;
    }

    public function index(): self
    {
        $this->index = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    public function comment(string $text): self
    {
        $this->comment = $text;
        return $this;
    }

    public function toSql(): string
    {
        if ($this->primary && $this->autoIncrement) {
            return "{$this->name} INTEGER PRIMARY KEY AUTOINCREMENT";
        }

        $sql = "{$this->name} " . $this->getSqlType();

        if ($this->unsigned && in_array(strtoupper($this->type), ['INTEGER', 'BIGINT', 'TINYINT'])) {
            $sql .= ' UNSIGNED';
        }

        if ($this->primary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($this->autoIncrement) {
            $sql .= ' AUTOINCREMENT';
        }

        if ($this->nullable) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        if ($this->default !== null) {
            $sql .= ' DEFAULT ' . $this->formatDefaultValue();
        }

        if ($this->unique) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    private function getSqlType(): string
    {
        $type = strtoupper($this->type);

        return match ($type) {
            'INTEGER', 'BIGINT', 'TINYINT', 'BOOLEAN' => 'INTEGER',
            'VARCHAR' => $this->length ? "VARCHAR({$this->length})" : 'VARCHAR',
            'TEXT', 'LONGTEXT', 'JSON', 'ENUM' => 'TEXT',
            'FLOAT' => 'REAL',
            'DECIMAL' => ($this->precision && $this->scale) ? "NUMERIC({$this->precision}, {$this->scale})" : 'NUMERIC',
            'DATE', 'DATETIME', 'TIMESTAMP' => 'TEXT',
            default => $type,
        };
    }

    private function formatDefaultValue(): string
    {
        if (is_bool($this->default)) {
            return $this->default ? '1' : '0';
        }

        if (is_null($this->default)) {
            return 'NULL';
        }

        if (is_int($this->default) || is_float($this->default)) {
            return (string) $this->default;
        }

        return "'" . addslashes((string) $this->default) . "'";
    }
}
