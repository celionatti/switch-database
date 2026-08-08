<?php

declare(strict_types=1);

namespace Switch\Database\Query;

use Countable;
use IteratorAggregate;
use ArrayIterator;
use Switch\Database\ORM\Collection;

class Paginator implements Countable, IteratorAggregate
{
    public readonly int $total;
    public readonly int $perPage;
    public readonly int $currentPage;
    public readonly int $lastPage;

    /**
     * @param array|Collection $items
     */
    public function __construct(
        public readonly mixed $items,
        int $total,
        int $perPage = 15,
        int $currentPage = 1
    ) {
        $this->total = max(0, $total);
        $this->perPage = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->lastPage = (int) ceil($this->total / $this->perPage);
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    public function previousPage(): ?int
    {
        return $this->currentPage > 1 ? $this->currentPage - 1 : null;
    }

    public function count(): int
    {
        return is_countable($this->items) ? count($this->items) : 0;
    }

    public function getIterator(): ArrayIterator
    {
        if ($this->items instanceof Collection) {
            return new ArrayIterator($this->items->all());
        }

        return new ArrayIterator(is_array($this->items) ? $this->items : []);
    }

    public function toArray(): array
    {
        $itemsArray = $this->items instanceof Collection ? $this->items->toArray() : $this->items;

        return [
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->lastPage,
            'has_more' => $this->hasMorePages(),
            'data' => $itemsArray,
        ];
    }

    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}
