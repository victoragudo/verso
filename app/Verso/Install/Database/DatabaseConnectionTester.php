<?php

namespace App\Verso\Install\Database;

use App\Verso\I18n\Translate;
use PDO;
use PDOException;

// Tests a database connection with a short timeout and explains failures in plain words.
class DatabaseConnectionTester
{
    // MySQL client and server error numbers.
    protected const MYSQL_UNREACHABLE = [2002, 2003, 2005, 2006, 2013];

    protected const MYSQL_AUTH = [1044, 1045, 1698];

    protected const MYSQL_UNKNOWN_DATABASE = 1049;

    // Words in PostgreSQL connection errors (they all share SQLSTATE 08006).
    protected const PGSQL_UNKNOWN_DATABASE = '/database ".*" does not exist/i';

    protected const PGSQL_AUTH = '/password authentication failed|no password supplied|role ".*" does not exist|authentication failed/i';

    protected const PGSQL_UNREACHABLE = '/could not translate host name|connection refused|timeout expired|no route to host|could not connect|network is unreachable|server closed the connection|Operation timed out/i';

    /**
     * @param  array<string, string>  $minVersions
     * @param  array<string, array{label: string, extension: string, port: int|null}>  $drivers
     * @param  list<string>  $versoTables
     */
    public function __construct(
        protected PdoConnector $connector,
        protected array $minVersions,
        protected array $drivers,
        protected array $versoTables,
        protected string $basePath,
        protected int $timeout,
    ) {}

    public function test(DatabaseSettings $settings): ConnectionReport
    {
        $extension = $this->drivers[$settings->driver]['extension'] ?? null;

        if ($extension === null || ! extension_loaded($extension)) {
            return ConnectionReport::failed(ConnectionProblem::DriverMissing, __('The :extension PHP extension is not loaded.', ['extension' => (string) $extension]));
        }

        return $settings->isSqlite() ? $this->testSqlite($settings) : $this->testServer($settings);
    }

    // True when the PDO extension of a driver is loaded.
    public function driverAvailable(string $driver): bool
    {
        $extension = $this->drivers[$driver]['extension'] ?? null;

        return $extension !== null && extension_loaded($extension);
    }

    protected function testSqlite(DatabaseSettings $settings): ConnectionReport
    {
        $path = $settings->sqlitePath($this->basePath);

        if (! is_file($path)) {
            $folder = dirname($path);

            return is_dir($folder) && is_writable($folder)
                ? new ConnectionReport(ok: true, message: __('The database file will be created at :path.', ['path' => $settings->database]), serverKind: 'sqlite', fileMissing: true)
                : ConnectionReport::failed(ConnectionProblem::SqliteNotWritable, __('The folder :folder does not exist or is not writable.', ['folder' => $folder]));
        }

        if (! is_writable($path)) {
            return ConnectionReport::failed(ConnectionProblem::SqliteNotWritable, __('The file :path is not writable.', ['path' => $settings->database]));
        }

        try {
            $pdo = $this->open($settings);
            $version = $this->scalar($pdo, 'select sqlite_version()');
            $tables = $this->tables($pdo, $settings);
        } catch (PDOException $exception) {
            return ConnectionReport::failed(ConnectionProblem::Unknown, $this->clean($exception->getMessage()));
        }

        return $this->success($settings, $version, 'sqlite', $tables);
    }

    protected function testServer(DatabaseSettings $settings): ConnectionReport
    {
        try {
            $pdo = $this->open($settings);
        } catch (PDOException $exception) {
            $problem = $this->problemFrom($settings, $exception);

            if ($problem === ConnectionProblem::UnknownDatabase) {
                return $this->unknownDatabase($settings);
            }

            return ConnectionReport::failed($problem, $this->clean($exception->getMessage()));
        }

        try {
            [$version, $kind] = $this->serverVersion($pdo, $settings);

            if (! $this->versionSupported($kind, $version)) {
                return ConnectionReport::failed(
                    ConnectionProblem::VersionTooOld,
                    __(':server :version is too old. Verso needs :server :minimum or newer.', ['server' => $this->kindLabel($kind), 'version' => $version, 'minimum' => $this->minVersions[$kind]]),
                    serverVersion: $version,
                    serverKind: $kind,
                );
            }

            $tables = $this->tables($pdo, $settings);
        } catch (PDOException $exception) {
            return ConnectionReport::failed(ConnectionProblem::Unknown, $this->clean($exception->getMessage()));
        }

        return $this->success($settings, $version, $kind, $tables);
    }

    // The server works but the database is missing: check if this user may create it.
    protected function unknownDatabase(DatabaseSettings $settings): ConnectionReport
    {
        $message = __('The database ":name" does not exist.', ['name' => $settings->database]);

        try {
            $pdo = $this->connector->connect($this->connection($settings, ''));
            [$version, $kind] = $this->serverVersion($pdo, $settings);
        } catch (PDOException) {
            return ConnectionReport::failed(ConnectionProblem::UnknownDatabase, $message);
        }

        return ConnectionReport::failed(ConnectionProblem::UnknownDatabase, $message, $this->mayCreate($pdo, $settings), $version, $kind);
    }

    protected function mayCreate(PDO $pdo, DatabaseSettings $settings): bool
    {
        try {
            if ($settings->driver === 'pgsql') {
                return in_array($this->scalar($pdo, 'select rolcreatedb or rolsuper from pg_roles where rolname = current_user'), ['1', 't', 'true'], true);
            }

            $grants = implode("\n", $this->column($pdo, 'show grants'));

            return preg_match('/GRANT (ALL PRIVILEGES|.*\bCREATE\b).* ON (\*\.\*|`?'.preg_quote($settings->database, '/').'`?\.\*)/i', $grants) === 1;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function serverVersion(PDO $pdo, DatabaseSettings $settings): array
    {
        if ($settings->driver === 'pgsql') {
            $raw = $this->scalar($pdo, 'show server_version');

            return [$this->numericVersion($raw), 'pgsql'];
        }

        $raw = $this->scalar($pdo, 'select version()');
        $kind = stripos($raw, 'mariadb') !== false ? 'mariadb' : 'mysql';

        return [$this->numericVersion($raw), $kind];
    }

    protected function versionSupported(string $kind, string $version): bool
    {
        $minimum = $this->minVersions[$kind] ?? null;

        return $minimum === null || version_compare($version, $minimum, '>=');
    }

    /**
     * Every table in the database, without the sqlite internal ones.
     *
     * @return list<string>
     */
    protected function tables(PDO $pdo, DatabaseSettings $settings): array
    {
        $sql = match ($settings->driver) {
            'sqlite' => "select name from sqlite_master where type = 'table' and name not like 'sqlite_%'",
            'pgsql' => 'select tablename from pg_tables where schemaname = current_schema()',
            default => 'show tables',
        };

        return $this->column($pdo, $sql);
    }

    /**
     * @param  list<string>  $tables
     */
    protected function success(DatabaseSettings $settings, string $version, string $kind, array $tables): ConnectionReport
    {
        $found = array_values(array_filter(
            array_map(fn (string $table): string => $settings->prefix.$table, $this->versoTables),
            fn (string $table): bool => in_array($table, $tables, true),
        ));

        $message = match (true) {
            $found !== [] => __('Connected, but this database already holds Verso tables (:tables). Use a table prefix or an empty database.', ['tables' => implode(', ', $found)]),
            $tables !== [] => Translate::icu('Connected to {server} {version}. The database holds {count, plural, one {# other table} other {# other tables}}; Verso will add its own.', ['server' => $this->kindLabel($kind), 'version' => $version, 'count' => count($tables)]),
            default => __('Connected to :server :version. The database is empty.', ['server' => $this->kindLabel($kind), 'version' => $version]),
        };

        return new ConnectionReport(
            ok: true,
            message: $message,
            serverVersion: $version,
            serverKind: $kind,
            tableCount: count($tables),
            versoTables: $found,
        );
    }

    // Maps a PDO error to a problem the user understands.
    protected function problemFrom(DatabaseSettings $settings, PDOException $exception): ConnectionProblem
    {
        $message = $exception->getMessage();

        if ($settings->driver === 'pgsql') {
            return match (true) {
                preg_match(self::PGSQL_UNKNOWN_DATABASE, $message) === 1 => ConnectionProblem::UnknownDatabase,
                preg_match(self::PGSQL_AUTH, $message) === 1 => ConnectionProblem::AuthFailed,
                preg_match(self::PGSQL_UNREACHABLE, $message) === 1 => ConnectionProblem::HostUnreachable,
                default => ConnectionProblem::Unknown,
            };
        }

        $code = preg_match('/\[(\d{4})\]/', $message, $match) === 1 ? (int) $match[1] : (int) $exception->getCode();

        return match (true) {
            $code === self::MYSQL_UNKNOWN_DATABASE => ConnectionProblem::UnknownDatabase,
            in_array($code, self::MYSQL_AUTH, true) => ConnectionProblem::AuthFailed,
            in_array($code, self::MYSQL_UNREACHABLE, true) => ConnectionProblem::HostUnreachable,
            default => ConnectionProblem::Unknown,
        };
    }

    protected function open(DatabaseSettings $settings): PDO
    {
        return $this->connector->connect($this->connection($settings));
    }

    public function connection(DatabaseSettings $settings, ?string $database = null): PdoConnection
    {
        return new PdoConnection($settings->dsn($this->basePath, $database), $settings->username, $settings->password, $this->timeout);
    }

    protected function scalar(PDO $pdo, string $sql): string
    {
        $statement = $pdo->query($sql);
        $value = $statement === false ? '' : $statement->fetchColumn();

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return list<string>
     */
    protected function column(PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);

        return $statement === false ? [] : array_values(array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $statement->fetchAll(PDO::FETCH_COLUMN)));
    }

    protected function numericVersion(string $raw): string
    {
        return preg_match('/\d+(\.\d+){0,2}/', $raw, $match) === 1 ? $match[0] : $raw;
    }

    protected function kindLabel(string $kind): string
    {
        return $this->drivers[$kind]['label'] ?? $kind;
    }

    // Drop the "SQLSTATE[...] [code]" noise from PDO messages.
    protected function clean(string $message): string
    {
        return trim((string) preg_replace('/^SQLSTATE\[[^\]]*\]\s*(\[[^\]]*\]\s*)?/', '', $message));
    }
}
