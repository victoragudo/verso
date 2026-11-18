<?php

namespace App\Verso\Install\Database;

// The database the user chose: driver, server, credentials and options.
final readonly class DatabaseSettings
{
    public function __construct(
        public string $driver,
        public string $database,
        public string $host = '',
        public ?int $port = null,
        public string $username = '',
        public string $password = '',
        public string $prefix = '',
        public string $sslmode = '',
        public string $charset = '',
        public string $collation = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $port = $data['port'] ?? null;

        return new self(
            driver: (string) ($data['driver'] ?? 'sqlite'),
            database: trim((string) ($data['database'] ?? '')),
            host: trim((string) ($data['host'] ?? '')),
            port: is_numeric($port) ? (int) $port : null,
            username: (string) ($data['username'] ?? ''),
            password: (string) ($data['password'] ?? ''),
            prefix: (string) ($data['prefix'] ?? ''),
            sslmode: (string) ($data['sslmode'] ?? ''),
            charset: (string) ($data['charset'] ?? ''),
            collation: (string) ($data['collation'] ?? ''),
        );
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'database' => $this->database,
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => $this->password,
            'prefix' => $this->prefix,
            'sslmode' => $this->sslmode,
            'charset' => $this->charset,
            'collation' => $this->collation,
        ];
    }

    // The same values without the password, safe to show or log.
    /**
     * @return array<string, string|int|bool|null>
     */
    public function masked(): array
    {
        $values = $this->toArray();
        unset($values['password']);
        $values['has_password'] = $this->password !== '';

        return $values;
    }

    public function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    public function isMysqlFamily(): bool
    {
        return in_array($this->driver, ['mysql', 'mariadb'], true);
    }

    public function withPassword(string $password): self
    {
        return self::fromArray(['password' => $password] + $this->toArray());
    }

    public function withDatabase(string $database): self
    {
        return self::fromArray(['database' => $database] + $this->toArray());
    }

    // The SQLite file as an absolute path. Relative paths start at the project folder.
    public function sqlitePath(string $basePath): string
    {
        if ($this->database === ':memory:' || str_starts_with($this->database, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $this->database) === 1) {
            return $this->database;
        }

        return rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$this->database;
    }

    // A PDO DSN. Without a database name it reaches the server only (to create the database).
    public function dsn(string $basePath, ?string $database = null): string
    {
        $name = $database ?? $this->database;

        return match ($this->driver) {
            'sqlite' => 'sqlite:'.$this->sqlitePath($basePath),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s%s',
                $this->host,
                $this->port ?? 5432,
                $name === '' ? 'postgres' : $name,
                $this->sslmode !== '' ? ';sslmode='.$this->sslmode : '',
            ),
            default => sprintf(
                'mysql:host=%s;port=%d%s%s',
                $this->host,
                $this->port ?? 3306,
                $name === '' ? '' : ';dbname='.$name,
                $this->charset !== '' ? ';charset='.$this->charset : '',
            ),
        };
    }

    /**
     * Values for the Laravel connection config of this driver.
     *
     * @return array<string, string|int|null>
     */
    public function connectionConfig(string $basePath): array
    {
        if ($this->isSqlite()) {
            return ['driver' => 'sqlite', 'url' => null, 'database' => $this->sqlitePath($basePath), 'prefix' => $this->prefix];
        }

        $config = [
            'driver' => $this->driver,
            'url' => null,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'prefix' => $this->prefix,
        ];

        if ($this->driver === 'pgsql') {
            return $config + ['sslmode' => $this->sslmode ?: 'prefer'];
        }

        return $config + array_filter(['charset' => $this->charset, 'collation' => $this->collation]);
    }

    /**
     * The DB_* lines for the .env file.
     *
     * @return array<string, string|int|null>
     */
    public function envValues(): array
    {
        if ($this->isSqlite()) {
            return ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database, 'DB_PREFIX' => $this->prefix];
        }

        $values = [
            'DB_CONNECTION' => $this->driver,
            'DB_HOST' => $this->host,
            'DB_PORT' => $this->port,
            'DB_DATABASE' => $this->database,
            'DB_USERNAME' => $this->username,
            'DB_PASSWORD' => $this->password,
            'DB_PREFIX' => $this->prefix,
        ];

        if ($this->driver === 'pgsql') {
            return $values + ['DB_SSLMODE' => $this->sslmode ?: 'prefer'];
        }

        return $values + ['DB_CHARSET' => $this->charset, 'DB_COLLATION' => $this->collation];
    }
}
