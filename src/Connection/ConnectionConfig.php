<?php

declare(strict_types=1);

namespace Switch\Database\Connection;

use InvalidArgumentException;

final readonly class ConnectionConfig
{
    public function __construct(
        public string $driver,
        public string $host = '127.0.0.1',
        public int $port = 3306,
        public string $database = '',
        public string $username = '',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = []
    ) {
    }

    /**
     * Create a new ConnectionConfig instance from an array of configuration options.
     *
     * @param array<string, mixed> $config
     * @return self
     */
    public static function fromArray(array $config): self
    {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('Database driver must be specified.');
        }

        return new self(
            driver: $config['driver'],
            host: $config['host'] ?? '127.0.0.1',
            port: (int) ($config['port'] ?? self::getDefaultPort($config['driver'])),
            database: $config['database'] ?? '',
            username: $config['username'] ?? '',
            password: $config['password'] ?? '',
            charset: $config['charset'] ?? 'utf8mb4',
            options: $config['options'] ?? []
        );
    }

    /**
     * Get the default port for the given driver.
     *
     * @param string $driver
     * @return int
     */
    private static function getDefaultPort(string $driver): int
    {
        return match ($driver) {
            'pgsql' => 5432,
            'sqlsrv' => 1433,
            default => 3306,
        };
    }

    /**
     * Build the DSN string for the PDO connection.
     *
     * @return string
     */
    public function toDsn(): string
    {
        return match ($this->driver) {
            'sqlite' => $this->database === ':memory:' 
                ? 'sqlite::memory:' 
                : "sqlite:{$this->database}",
            'pgsql' => "pgsql:host={$this->host};port={$this->port};dbname={$this->database}",
            'mysql' => "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}",
            'sqlsrv' => "sqlsrv:Server={$this->host},{$this->port};Database={$this->database}",
            default => throw new InvalidArgumentException("Unsupported database driver: {$this->driver}"),
        };
    }
}
