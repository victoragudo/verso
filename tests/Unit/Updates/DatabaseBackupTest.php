<?php

use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Updater\DatabaseBackup;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\Support\UpdateSandbox;

beforeEach(function () {
    $this->sandbox = UpdateSandbox::create();
});

afterEach(function () {
    $this->sandbox->destroy();
});

function databaseBackup(string $connection, ?Closure $finder = null): DatabaseBackup
{
    return new DatabaseBackup(app(DatabaseManager::class), new Filesystem, $connection, ['mysqldump' => 'mysqldump', 'mysql' => 'mysql', 'pg_dump' => 'pg_dump', 'psql' => 'psql'], 60, $finder);
}

it('copies and restores a SQLite database file', function () {
    $backup = databaseBackup(UpdateSandbox::CONNECTION);

    expect($backup->isAvailable())->toBeTrue();

    $file = $backup->backup($this->sandbox->root.'/backup');
    DB::connection(UpdateSandbox::CONNECTION)->table('guestbook')->insert(['name' => 'Added during a broken update']);
    DB::connection(UpdateSandbox::CONNECTION)->statement('DROP TABLE IF EXISTS sessions');

    expect($this->sandbox->guestbook())->toContain('Added during a broken update');

    $backup->restore($file);

    expect($file)->toEndWith('/backup/database.sqlite')
        ->and($this->sandbox->guestbook())->toBe(['Marta Ruiz', 'Jonas Becker']);
});

it('cannot back up an in-memory SQLite database', function () {
    config(['database.connections.memory_site' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    $backup = databaseBackup('memory_site');

    expect($backup->unavailableReason())->toBe('The SQLite database is not a file on disk.')
        ->and($backup->sqliteFile())->toBeNull()
        ->and(fn () => $backup->backup($this->sandbox->root.'/backup'))->toThrow(UpdateFailed::class);
});

it('knows the SQLite file of the site', function () {
    expect(databaseBackup(UpdateSandbox::CONNECTION)->sqliteFile())->toBe($this->sandbox->site('database/database.sqlite'));
});

it('needs mysqldump and mysql for MySQL', function () {
    config(['database.connections.shop_mysql' => ['driver' => 'mysql', 'host' => 'db.internal', 'port' => '3306', 'database' => 'verso_shop', 'username' => 'verso', 'password' => 'harbor-2026']]);

    expect(databaseBackup('shop_mysql', fn () => null)->unavailableReason())->toBe('mysqldump or mysql was not found on this server.')
        ->and(databaseBackup('shop_mysql', fn (string $name) => "/usr/bin/{$name}")->isAvailable())->toBeTrue();
});

it('dumps and restores MySQL with the password in the environment', function () {
    config(['database.connections.shop_mysql' => ['driver' => 'mysql', 'host' => 'db.internal', 'port' => '3307', 'database' => 'verso_shop', 'username' => 'verso', 'password' => 'harbor-2026']]);
    Process::fake();
    $backup = databaseBackup('shop_mysql', fn (string $name) => "/usr/bin/{$name}");

    $file = $backup->backup($this->sandbox->root.'/backup');
    file_put_contents($file, 'CREATE TABLE orders (id int);');
    $backup->restore($file);

    Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), '/usr/bin/mysqldump')
        && str_contains(implode(' ', (array) $process->command), '--host=db.internal')
        && str_contains(implode(' ', (array) $process->command), '--port=3307')
        && str_contains(implode(' ', (array) $process->command), '--result-file='.$file)
        && $process->environment['MYSQL_PWD'] === 'harbor-2026'
        && ! str_contains(implode(' ', (array) $process->command), 'harbor-2026'));
    Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), '/usr/bin/mysql ')
        && $process->input === 'CREATE TABLE orders (id int);');
});

it('dumps and restores PostgreSQL with pg_dump and psql', function () {
    config(['database.connections.blog_pgsql' => ['driver' => 'pgsql', 'host' => 'pg.internal', 'port' => '5432', 'database' => 'verso_blog', 'username' => 'verso', 'password' => 'lighthouse']]);
    Process::fake();
    $backup = databaseBackup('blog_pgsql', fn (string $name) => "/usr/local/bin/{$name}");

    $file = $backup->backup($this->sandbox->root.'/backup');
    file_put_contents($file, '-- dump');
    $backup->restore($file);

    Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), '/usr/local/bin/pg_dump')
        && str_contains(implode(' ', (array) $process->command), '--clean')
        && $process->environment['PGPASSWORD'] === 'lighthouse');
    Process::assertRan(fn (PendingProcess $process) => str_contains(implode(' ', (array) $process->command), '/usr/local/bin/psql')
        && str_contains(implode(' ', (array) $process->command), '--file='.$file));
});

it('reports a failing dump with its error output', function () {
    config(['database.connections.shop_mysql' => ['driver' => 'mysql', 'host' => 'db.internal', 'database' => 'verso_shop', 'username' => 'verso', 'password' => 'secret']]);
    Process::fake(['*' => Process::result(errorOutput: 'Access denied for user verso', exitCode: 2)]);

    databaseBackup('shop_mysql', fn (string $name) => "/usr/bin/{$name}")->backup($this->sandbox->root.'/backup');
})->throws(UpdateFailed::class, 'mysqldump failed: Access denied for user verso');

it('refuses to restore a missing backup file', function () {
    databaseBackup(UpdateSandbox::CONNECTION)->restore($this->sandbox->root.'/missing.sqlite');
})->throws(UpdateFailed::class, 'backup file is missing');

it('does not support other drivers', function () {
    config(['database.connections.legacy_sqlsrv' => ['driver' => 'sqlsrv', 'database' => 'verso']]);

    expect(databaseBackup('legacy_sqlsrv')->unavailableReason())->toBe('Backups of sqlsrv databases are not supported.');
});
