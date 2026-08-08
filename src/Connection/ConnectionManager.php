<?php

declare(strict_types=1);

namespace Switch\Database\Connection;

use InvalidArgumentException;
use RuntimeException;

class ConnectionManager
{
    /**
     * The configured database connections.
     *
     * @var array<string, ConnectionConfig>
     */
    private array $configs = [];

    /**
     * The active database connection instances.
     *
     * @var array<string, Connection>
     */
    private array $connections = [];

    /**
     * The name of the default connection.
     *
     * @var string
     */
    private string $defaultConnection = 'default';

    /**
     * Add a connection configuration.
     *
     * @param string $name
     * @param array<string, mixed>|ConnectionConfig $config
     * @return void
     */
    public function addConnection(string $name, array|ConnectionConfig $config): void
    {
        if (is_array($config)) {
            $config = ConnectionConfig::fromArray($config);
        }

        $this->configs[$name] = $config;
    }

    /**
     * Get a database connection instance.
     *
     * @param string|null $name
     * @return Connection
     * @throws InvalidArgumentException
     */
    public function connection(?string $name = null): Connection
    {
        $name ??= $this->getDefaultConnection();

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->makeConnection($name);
    }

    /**
     * Make a new database connection instance.
     *
     * @param string $name
     * @return Connection
     * @throws InvalidArgumentException
     */
    private function makeConnection(string $name): Connection
    {
        if (!isset($this->configs[$name])) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }

        return new Connection($this->configs[$name]);
    }

    /**
     * Set the default connection name.
     *
     * @param string $name
     * @return void
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     * @throws RuntimeException
     */
    public function getDefaultConnection(): string
    {
        if (empty($this->configs)) {
            throw new RuntimeException('No database connections have been configured.');
        }

        if (!isset($this->configs[$this->defaultConnection]) && !empty($this->configs)) {
            // If default is not set but we have configs, use the first one as default
            return array_key_first($this->configs);
        }

        return $this->defaultConnection;
    }
}
