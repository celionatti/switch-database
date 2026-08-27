<?php

declare(strict_types=1);

namespace Switch\Database\Connection;

use PDO;
use PDOException;
use PDOStatement;
use Switch\Database\Query\QueryBuilder;
use Closure;
use RuntimeException;

class Connection
{
    private ?PDO $pdo = null;
    
    /**
     * @var array<int, callable>
     */
    private static array $queryListeners = [];

    /**
     * Register a callback to listen for executed queries.
     *
     * @param callable(string, array, float, string): void $callback
     */
    public static function listen(callable $callback): void
    {
        self::$queryListeners[] = $callback;
    }

    /**
     * Clear all registered query listeners.
     */
    public static function resetListeners(): void
    {
        self::$queryListeners = [];
    }

    public function getConfig(): ConnectionConfig
    {
        return $this->config;
    }

    public function getDriverName(): string
    {
        return $this->config->driver;
    }
    
    public function __construct(
        private readonly ConnectionConfig $config
    ) {
    }

    /**
     * Create a Connection instance from a configuration array.
     *
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        return new self(ConnectionConfig::fromArray($config));
    }

    /**
     * Create a quick SQLite connection.
     *
     * @param string $path SQLite database path or ':memory:'
     * @return self
     */
    public static function sqlite(string $path = ':memory:'): self
    {
        return new self(new ConnectionConfig(
            driver: 'sqlite',
            database: $path
        ));
    }

    /**
     * Create a PostgreSQL connection.
     */
    public static function postgres(
        string $database,
        string $host = '127.0.0.1',
        int $port = 5432,
        string $username = 'postgres',
        string $password = '',
        array $options = []
    ): self {
        return new self(new ConnectionConfig(
            driver: 'pgsql',
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            options: $options
        ));
    }

    /**
     * Create a MySQL connection.
     */
    public static function mysql(
        string $database,
        string $host = '127.0.0.1',
        int $port = 3306,
        string $username = 'root',
        string $password = '',
        array $options = []
    ): self {
        return new self(new ConnectionConfig(
            driver: 'mysql',
            host: $host,
            port: $port,
            database: $database,
            username: $username,
            password: $password,
            options: $options
        ));
    }

    /**
     * Get the appropriate SQL Grammar instance for this connection's driver.
     */
    public function getGrammar(): \Switch\Database\Query\Grammar
    {
        return match (strtolower($this->config->driver)) {
            'pgsql', 'postgres', 'postgresql' => new \Switch\Database\Query\Grammar\PostgresGrammar(),
            'mysql', 'mariadb' => new \Switch\Database\Query\Grammar\MySqlGrammar(),
            default => new \Switch\Database\Query\Grammar\SqliteGrammar(),
        };
    }

    /**
     * Get the underlying PDO instance, creating it lazily if necessary.
     *
     * @return PDO
     * @throws PDOException
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->createPdoInstance();
        }

        return $this->pdo;
    }

    /**
     * Create the PDO instance based on the configuration.
     *
     * @return PDO
     */
    private function createPdoInstance(): PDO
    {
        $options = $this->config->options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        return new PDO(
            $this->config->toDsn(),
            $this->config->username,
            $this->config->password,
            $options
        );
    }

    /**
     * Execute a query and return the statement.
     *
     * @param string $sql
     * @param array<int|string, mixed> $bindings
     * @return PDOStatement
     */
    public function query(string $sql, array $bindings = []): PDOStatement
    {
        if (empty(self::$queryListeners)) {
            $statement = $this->getPdo()->prepare($sql);
            $statement->execute($bindings);
            return $statement;
        }

        $start = microtime(true);
        $statement = $this->getPdo()->prepare($sql);
        $statement->execute($bindings);
        $timeMs = (microtime(true) - $start) * 1000;

        foreach (self::$queryListeners as $listener) {
            $listener($sql, $bindings, $timeMs, $this->config->driver ?? 'default');
        }

        return $statement;
    }

    /**
     * Execute a select query and return all results as an associative array.
     *
     * @param string $sql
     * @param array<int|string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->query($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute an insert statement and return the last insert ID.
     *
     * @param string $sql
     * @param array<int|string, mixed> $bindings
     * @return int|string
     */
    public function insert(string $sql, array $bindings = []): int|string
    {
        $this->query($sql, $bindings);
        
        return $this->getPdo()->lastInsertId();
    }

    /**
     * Execute an update statement and return the number of affected rows.
     *
     * @param string $sql
     * @param array<int|string, mixed> $bindings
     * @return int
     */
    public function update(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    /**
     * Execute a delete statement and return the number of affected rows.
     *
     * @param string $sql
     * @param array<int|string, mixed> $bindings
     * @return int
     */
    public function delete(string $sql, array $bindings = []): int
    {
        return $this->query($sql, $bindings)->rowCount();
    }

    /**
     * Execute a general statement and return success boolean.
     *
     * @param string $sql
     * @param array<int|string, mixed> $bindings
     * @return bool
     */
    public function statement(string $sql, array $bindings = []): bool
    {
        if (empty(self::$queryListeners)) {
            return $this->getPdo()->prepare($sql)->execute($bindings);
        }

        $start = microtime(true);
        $result = $this->getPdo()->prepare($sql)->execute($bindings);
        $timeMs = (microtime(true) - $start) * 1000;

        foreach (self::$queryListeners as $listener) {
            $listener($sql, $bindings, $timeMs, $this->config->driver ?? 'default');
        }

        return $result;
    }

    /**
     * Execute a Closure within a database transaction.
     *
     * @param callable $callback
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->getPdo();
        
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     * @return QueryBuilder
     */
    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $this->getGrammar(), $table);
    }
}
