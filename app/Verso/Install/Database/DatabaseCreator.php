<?php

namespace App\Verso\Install\Database;

use Illuminate\Filesystem\Filesystem;
use PDO;
use PDOException;
use RuntimeException;

// Creates the database when it is missing: the SQLite file, or CREATE DATABASE on the server.
class DatabaseCreator
{
    protected const NAME = '/^[A-Za-z0-9_][A-Za-z0-9_\-]{0,63}$/';

    public function __construct(
        protected PdoConnector $connector,
        protected DatabaseConnectionTester $tester,
        protected Filesystem $files,
        protected string $basePath,
    ) {}

    // Returns true when something was created, false when it was already there.
    public function create(DatabaseSettings $settings): bool
    {
        if ($settings->isSqlite()) {
            return $this->createSqlite($settings);
        }

        if (preg_match(self::NAME, $settings->database) !== 1) {
            throw new RuntimeException(__('The database name may only use letters, numbers, dashes and underscores.'));
        }

        try {
            $pdo = $this->connector->connect($this->tester->connection($settings, ''));

            if ($this->exists($pdo, $settings)) {
                return false;
            }

            $pdo->exec($this->createStatement($settings));
        } catch (PDOException $exception) {
            throw new RuntimeException(__('Could not create the database: :error', ['error' => $exception->getMessage()]), 0, $exception);
        }

        return true;
    }

    protected function createSqlite(DatabaseSettings $settings): bool
    {
        $path = $settings->sqlitePath($this->basePath);

        if ($path === ':memory:' || $this->files->exists($path)) {
            return false;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, '');

        return true;
    }

    protected function exists(PDO $pdo, DatabaseSettings $settings): bool
    {
        $sql = $settings->driver === 'pgsql'
            ? 'select 1 from pg_database where datname = ?'
            : 'select 1 from information_schema.schemata where schema_name = ?';
        $statement = $pdo->prepare($sql);
        $statement->execute([$settings->database]);

        return $statement->fetchColumn() !== false;
    }

    protected function createStatement(DatabaseSettings $settings): string
    {
        if ($settings->driver === 'pgsql') {
            return 'create database "'.$settings->database.'" encoding \'UTF8\'';
        }

        $sql = 'create database `'.$settings->database.'`';
        $sql .= $settings->charset !== '' ? ' character set '.preg_replace('/[^A-Za-z0-9_]/', '', $settings->charset) : '';

        return $sql.($settings->collation !== '' ? ' collate '.preg_replace('/[^A-Za-z0-9_]/', '', $settings->collation) : '');
    }
}
