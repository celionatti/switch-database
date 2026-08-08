<?php

declare(strict_types=1);

namespace Switch\Database\ORM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class Collection implements Countable, IteratorAggregate, ArrayAccess
{
    /**
     * @param array<int|string, mixed> $items
     */
    public function __construct(protected array $items = [])
    {
    }

    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?object
    {
        return $this->items[array_key_first($this->items)] ?? null;
    }

    public function last(): ?object
    {
        return $this->items[array_key_last($this->items)] ?? null;
    }

    public function map(callable $callback): self
    {
        return new self(array_map($callback, $this->items));
    }

    public function filter(callable $callback): self
    {
        return new self(array_filter($this->items, $callback));
    }

    public function pluck(string $key, ?string $valueKey = null): array
    {
        $results = [];

        foreach ($this->items as $item) {
            $itemValue = is_object($item) ? $item->$key : $item[$key];

            if ($valueKey === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = is_object($item) ? $item->$valueKey : $item[$valueKey];
                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function toArray(): array
    {
        return array_map(function ($item) {
            return method_exists($item, 'toArray') ? $item->toArray() : $item;
        }, $this->items);
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options) ?: '';
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }
}
