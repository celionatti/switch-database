<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\Schema\Blueprint;
use Switch\Database\Schema\SchemaBuilder;

class SchemaTest extends TestCase
{
    private Connection $db;
    private SchemaBuilder $schema;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite(':memory:');
        $this->schema = new SchemaBuilder($this->db);
    }

    public function testCreateTableAndHasTable(): void
    {
        $this->schema->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->assertTrue($this->schema->hasTable('products'));
        $this->assertTrue($this->schema->hasColumn('products', 'name'));
        $this->assertTrue($this->schema->hasColumn('products', 'created_at'));
    }

    public function testDropTable(): void
    {
        $this->schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        $this->assertTrue($this->schema->hasTable('categories'));
        $this->schema->drop('categories');
        $this->assertFalse($this->schema->hasTable('categories'));
    }
}
