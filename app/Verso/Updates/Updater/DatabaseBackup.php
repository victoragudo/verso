<?php

namespace App\Verso\Updates\Updater;

use App\Verso\I18n\Translate;
use App\Verso\Updates\Exceptions\UpdateFailed;
use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

// Backs up and restores the site database: a file copy for SQLite, mysqldump or pg_dump for the others.
class DatabaseBackup
{
    protected const SQLITE = 'sqlite';

    protected const MYSQL = 'mysql';

    protected const MARIADB = 'mariadb';

    protected const PGSQL = 'pgsql';

    /** @var Closure(string): ?string */
    protected Closure $finder;

    /**
     * @param  array<string, string>  $binaries
     * @param  (Closure(string): ?string)|null  $finder
     */
    public function __construct(
        protected DatabaseManager $db,
        protected Filesystem $files,
        protected string $connection,
        protected array $binaries,
        protected int $timeout,
        ?Closure $finder = null,
    ) {
        $this->finder = $finder ?? fn (string $name): ?string => (new ExecutableFinder)->find($name);
    }

    // Null when a backup can be made, or the reason why it cannot.
    public function unavailableReason(): ?string
    {
        $config = $this->config();
        $driver = (string) ($config['driver'] ?? '');

        return match ($driver) {
            self::SQLITE => $this->sqlitePath() === null ? Translate::text('The SQLite database is not a file on disk.') : null,
            self::MYSQL, self::MARIADB => $this->binary('mysqldump') === null || $this->binary('mysql') === null
                ? Translate::text('mysqldump or mysql was not found on this server.') : null,
            self::PGSQL => $this->binary('pg_dump') === null || $this->binary('psql') === null
                ? Translate::text('pg_dump or psql was not found on this server.') : null,
            default => Translate::text('Backups of :driver databases are not supported.', ['driver' => $driver]),
        };
    }

    public function isAvailable(): bool
    {
        return $this->unavailableReason() === null;
    }

    // Write a backup into the folder and return the backup file.
    public function backup(string $directory): string
    {
        $reason = $this->unavailableReason();

        if ($reason !== null) {
            throw UpdateFailed::because($reason);
        }

        $this->files->ensureDirectoryExists($directory, 0755, true);
        $config = $this->config();

        return match ((string) $config['driver']) {
            self::SQLITE => $this->backupSqlite($directory),
            self::PGSQL => $this->backupPostgres($directory, $config),
            default => $this->backupMysql($directory, $config),
        };
    }

    // Load a backup made by backup() back into the database.
    public function restore(string $file): void
    {
        if (! $this->files->exists($file)) {
            throw UpdateFailed::because(Translate::text('The database backup file is missing.'));
        }

        $config = $this->config();

        match ((string) ($config['driver'] ?? '')) {
            self::SQLITE => $this->restoreSqlite($file),
            self::PGSQL => $this->restorePostgres($file, $config),
            default => $this->restoreMysql($file, $config),
        };

        $this->db->purge($this->connection);
    }

    // VACUUM INTO writes a clean, consistent copy even while the database is open.
    protected function backupSqlite(string $directory): string
    {
        $file = $directory.'/database.sqlite';
        $this->files->delete($file);
        $this->db->connection($this->connection)->statement('VACUUM INTO ?', [$file]);

        return $file;
    }

    protected function restoreSqlite(string $file): void
    {
        $path = (string) $this->sqlitePath();
        $this->db->disconnect($this->connection);
        $this->files->delete([$path.'-wal', $path.'-shm']);

        if (! $this->files->copy($file, $path)) {
            throw UpdateFailed::because(Translate::text('The SQLite database could not be restored.'));
        }
    }

    /** @param array<string, mixed> $config */
    protected function backupMysql(string $directory, array $config): string
    {
        $file = $directory.'/database.sql';
        $this->runProcess([
            (string) $this->binary('mysqldump'),
            ...$this->mysqlOptions($config),
            '--single-transaction',
            '--routines',
            '--no-tablespaces',
            '--result-file='.$file,
            (string) $config['database'],
        ], ['MYSQL_PWD' => (string) ($config['password'] ?? '')], 'mysqldump');

        return $file;
    }

    /** @param array<string, mixed> $config */
    protected function restoreMysql(string $file, array $config): void
    {
        $this->runProcess(
            [(string) $this->binary('mysql'), ...$this->mysqlOptions($config), (string) $config['database']],
            ['MYSQL_PWD' => (string) ($config['password'] ?? '')],
            'mysql',
            $this->files->get($file),
        );
    }

    /** @param array<string, mixed> $config */
    protected function backupPostgres(string $directory, array $config): string
    {
        $file = $directory.'/database.sql';
        $this->runProcess([
            (string) $this->binary('pg_dump'),
            ...$this->postgresOptions($config),
            '--clean',
            '--if-exists',
            '--no-owner',
            '--file='.$file,
            (string) $config['database'],
        ], ['PGPASSWORD' => (string) ($config['password'] ?? '')], 'pg_dump');

        return $file;
    }

    /** @param array<string, mixed> $config */
    protected function restorePostgres(string $file, array $config): void
    {
        $this->runProcess([
            (string) $this->binary('psql'),
            ...$this->postgresOptions($config),
            '--set=ON_ERROR_STOP=1',
            '--file='.$file,
            (string) $config['database'],
        ], ['PGPASSWORD' => (string) ($config['password'] ?? '')], 'psql');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function mysqlOptions(array $config): array
    {
        $options = [
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '3306'),
            '--user='.($config['username'] ?? ''),
        ];

        if (! empty($config['unix_socket'])) {
            $options[] = '--socket='.$config['unix_socket'];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    protected function postgresOptions(array $config): array
    {
        return [
            '--host='.($config['host'] ?? '127.0.0.1'),
            '--port='.($config['port'] ?? '5432'),
            '--username='.($config['username'] ?? ''),
        ];
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    protected function runProcess(array $command, array $environment, string $program, ?string $input = null): void
    {
        $process = Process::timeout($this->timeout)->env($environment);

        if ($input !== null) {
            $process = $process->input($input);
        }

        $result = $process->run($command);

        if ($result->failed()) {
            $output = trim($result->errorOutput()) ?: trim($result->output());

            throw UpdateFailed::because(Translate::text(':program failed: :output', ['program' => $program, 'output' => mb_substr($output, 0, 500)]));
        }
    }

    // The configured program, as an absolute path or found in PATH.
    protected function binary(string $name): ?string
    {
        $value = (string) ($this->binaries[$name] ?? $name);

        if ($value === '') {
            return null;
        }

        if (str_contains($value, DIRECTORY_SEPARATOR)) {
            return is_executable($value) ? $value : null;
        }

        return ($this->finder)($value);
    }

    protected function sqlitePath(): ?string
    {
        $path = (string) ($this->config()['database'] ?? '');

        return $path !== '' && $path !== ':memory:' && ! str_contains($path, 'mode=memory') && is_file($path) ? $path : null;
    }

    // The full path of the SQLite file, when the site uses one.
    public function sqliteFile(): ?string
    {
        return ($this->config()['driver'] ?? null) === self::SQLITE ? $this->sqlitePath() : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function config(): array
    {
        return (array) config("database.connections.{$this->connection}", []);
    }
}
