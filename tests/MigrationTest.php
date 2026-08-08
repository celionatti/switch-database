<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\Migration\Migration;
use Switch\Database\Migration\MigrationRepository;
use Switch\Database\Migration\MigrationRunner;
use Switch\Database\Schema\Blueprint;
use Switch\Database\Schema\SchemaBuilder;

class CreateArticlesTable extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('articles');
    }
}

class MigrationTest extends TestCase
{
    private Connection $db;
    private MigrationRepository $repository;
    private MigrationRunner $runner;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite(':memory:');
        $this->repository = new MigrationRepository($this->db);
        $this->runner = new MigrationRunner($this->db, $this->repository);
    }

    public function testMigrationRunAndRollback(): void
    {
        $migrations = [
            '2026_01_01_000000_create_articles_table' => new CreateArticlesTable(),
        ];

        $ran = $this->runner->run($migrations);
        $this->assertEquals(['2026_01_01_000000_create_articles_table'], $ran);

        $schema = new SchemaBuilder($this->db);
        $this->assertTrue($schema->hasTable('articles'));

        $rolledBack = $this->runner->rollback();
        $this->assertEquals(['2026_01_01_000000_create_articles_table'], $rolledBack);
        $this->assertFalse($schema->hasTable('articles'));
    }
}
