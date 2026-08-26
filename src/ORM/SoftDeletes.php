<?php

declare(strict_types=1);

namespace Switch\Database\ORM;

trait SoftDeletes
{
    /**
     * Boot the soft deleting trait for a model.
     */
    public function initializeSoftDeletes(): void
    {
        $this->softDeletes = true;
    }
}
