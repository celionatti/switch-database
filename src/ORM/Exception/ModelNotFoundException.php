<?php

declare(strict_types=1);

namespace Switch\Database\ORM\Exception;

use Exception;

class ModelNotFoundException extends Exception
{
    /**
     * @param string $model FQCN of model
     * @param array<mixed> $ids
     */
    public function __construct(
        private readonly string $model,
        private readonly array $ids = []
    ) {
        $idString = !empty($ids) ? ' [' . implode(', ', array_map('strval', $ids)) . ']' : '';
        parent::__construct("No query results for model [{$model}]{$idString}.");
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
