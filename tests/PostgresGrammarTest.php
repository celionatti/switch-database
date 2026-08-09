<?php

declare(strict_types=1);

namespace Switch\Database\Tests;

use Switch\Database\Connection\Connection;
use Switch\Database\Query\Grammar\MySqlGrammar;
use Switch\Database\Query\Grammar\PostgresGrammar;
use Switch\Database\Query\Grammar\SqliteGrammar;
use Switch\Database\Query\QueryBuilder;

class PostgresGrammarTest
{
    protected function setUp(): void {}
    protected function tearDown(): void {}

    public function testPostgresGrammarCompilesILike(): void
    {
        $connection = Connection::sqlite();
        $grammar = new PostgresGrammar();
        $builder = new QueryBuilder($connection, $grammar, 'users');

        $builder->whereILike('email', '%@example.com');
        $sql = $builder->toSql();

        assert(str_contains($sql, '"email" ILIKE ?'), "Expected ILIKE in SQL, got: {$sql}");
    }

    public function testPostgresGrammarCompilesInsertGetIdWithReturning(): void
    {
        $grammar = new PostgresGrammar();
        $connection = Connection::sqlite();
        $builder = new QueryBuilder($connection, $grammar, 'users');

        $sql = $grammar->compileInsertGetId($builder, ['name' => 'Alice', 'email' => 'alice@example.com'], 'id');

        assert(str_contains($sql, 'RETURNING "id"'), "Expected RETURNING \"id\" in SQL, got: {$sql}");
    }

    public function testDriverGrammarAutoSelection(): void
    {
        $pgConn = Connection::postgres('test_db');
        assert($pgConn->getGrammar() instanceof PostgresGrammar);

        $myConn = Connection::mysql('test_db');
        assert($myConn->getGrammar() instanceof MySqlGrammar);

        $sqConn = Connection::sqlite();
        assert($sqConn->getGrammar() instanceof SqliteGrammar);
    }
}
