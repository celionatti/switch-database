<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\Migration\Migrator;
use Switch\Database\Schema\SchemaBuilder;

class MigratorTest extends TestCase
{
    private string $tempMigDir;
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite(':memory:');
        $this->tempMigDir = sys_get_temp_dir() . '/switch_migrations_' . uniqid();
        mkdir($this->tempMigDir, 0777, true);

        // Create sample migration file
        $code = <<<'PHP'
<?php

use Switch\Database\Migration\Migration;
use Switch\Database\Schema\Blueprint;
use Switch\Database\Schema\SchemaBuilder;

class CreateTestUsersTable extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('test_users');
    }
}
PHP;
        file_put_contents($this->tempMigDir . '/2026_01_01_000000_create_test_users_table.php', $code);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempMigDir)) {
            array_map('unlink', glob($this->tempMigDir . '/*.*') ?: []);
            rmdir($this->tempMigDir);
        }
    }

    public function testMigratorRunAndRollback(): void
    {
        $migrator = new Migrator($this->db, $this->tempMigDir);
        $executed = $migrator->run();

        $this->assertCount(1, $executed);
        $schema = new SchemaBuilder($this->db);
        $this->assertTrue($schema->hasTable('test_users'));

        $rolledBack = $migrator->rollback();
        $this->assertCount(1, $rolledBack);
        $this->assertFalse($schema->hasTable('test_users'));
    }
}
