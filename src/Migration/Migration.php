<?php

declare(strict_types=1);

namespace Switch\Database\Migration;

use Switch\Database\Schema\SchemaBuilder;

abstract class Migration
{
    abstract public function up(SchemaBuilder $schema): void;

    abstract public function down(SchemaBuilder $schema): void;
}
