<?php

declare(strict_types=1);

namespace Switch\Database\Query\Grammar;

use Switch\Database\Query\Grammar;

class MySqlGrammar extends Grammar
{
    protected function wrapValue(string $value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }
}
