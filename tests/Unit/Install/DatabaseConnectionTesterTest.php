<?php

use App\Verso\Install\Database\ConnectionProblem;
use App\Verso\Install\Database\DatabaseConnectionTester;
use App\Verso\Install\Database\DatabaseSettings;
use App\Verso\Install\Database\PdoConnection;
use Tests\Support\FakePdoConnector;

function tester(Closure $answer): array
{
    $connector = new FakePdoConnector($answer);

    return [new DatabaseConnectionTester(
        connector: $connector,
        minVersions: ['mysql' => '8.0.0', 'mariadb' => '10.6.0', 'pgsql' => '14.0'],
        drivers: (array) config('verso.install.database.drivers'),
        versoTables: ['users', 'entries', 'settings'],
        basePath: base_path(),
        timeout: 3,
    ), $connector];
}

function mysqlSettings(array $overrides = []): DatabaseSettings
{
    return DatabaseSettings::fromArray($overrides + [
        'driver' => 'mysql', 'host' => 'db.harborlane.internal', 'port' => 3306, 'database' => 'bakery',
        'username' => 'bakery_app', 'password' => 'Rye&Spelt-2026', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
    ]);
}

function pgsqlSettings(array $overrides = []): DatabaseSettings
{
    return DatabaseSettings::fromArray($overrides + [
        'driver' => 'pgsql', 'host' => 'pg.harborlane.internal', 'port' => 5432, 'database' => 'bakery',
        'username' => 'bakery_app', 'password' => 'Rye&Spelt-2026', 'sslmode' => 'prefer',
    ]);
}

it('connects to an empty MySQL 8.4 server with the timeout from config', function () {
    [$tester, $connector] = tester(fn () => FakePdoConnector::server('8.4.3'));

    $report = $tester->test(mysqlSettings());

    expect($report->ok)->toBeTrue()
        ->and($report->serverKind)->toBe('mysql')
        ->and($report->serverVersion)->toBe('8.4.3')
        ->and($report->message)->toContain('The database is empty')
        ->and($connector->calls[0]->timeout)->toBe(3)
        ->and($connector->calls[0]->dsn)->toBe('mysql:host=db.harborlane.internal;port=3306;dbname=bakery;charset=utf8mb4');
});

it('maps MySQL errors to clear problems', function (string $message, ConnectionProblem $problem) {
    [$tester] = tester(fn () => FakePdoConnector::error($message));

    $report = $tester->test(mysqlSettings(['database' => '']));

    expect($report->ok)->toBeFalse()
        ->and($report->problem)->toBe($problem)
        ->and($report->message)->not->toContain('SQLSTATE');
})->with([
    'refused' => ['SQLSTATE[HY000] [2002] Connection refused', ConnectionProblem::HostUnreachable],
    'unknown host' => ['SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for nowhere failed', ConnectionProblem::HostUnreachable],
    'access denied' => ["SQLSTATE[HY000] [1045] Access denied for user 'bakery_app'@'10.0.0.4' (using password: YES)", ConnectionProblem::AuthFailed],
    'other' => ['SQLSTATE[HY000] [1203] User bakery_app already has more than max_user_connections', ConnectionProblem::Unknown],
]);

it('maps PostgreSQL errors to clear problems', function (string $message, ConnectionProblem $problem) {
    [$tester] = tester(fn (PdoConnection $connection) => str_contains($connection->dsn, 'dbname=postgres')
        ? FakePdoConnector::error('no maintenance access')
        : FakePdoConnector::error($message));

    expect($tester->test(pgsqlSettings())->problem)->toBe($problem);
})->with([
    'refused' => ['SQLSTATE[08006] [7] connection to server at "pg.harborlane.internal" (10.0.0.9), port 5432 failed: Connection refused', ConnectionProblem::HostUnreachable],
    'bad host' => ['SQLSTATE[08006] [7] could not translate host name "pg.nowhere" to address', ConnectionProblem::HostUnreachable],
    'password' => ['SQLSTATE[08006] [7] connection to server failed: FATAL:  password authentication failed for user "bakery_app"', ConnectionProblem::AuthFailed],
    'database' => ['SQLSTATE[08006] [7] connection to server failed: FATAL:  database "bakery" does not exist', ConnectionProblem::UnknownDatabase],
]);

it('offers to create a missing MySQL database when the user may', function () {
    [$tester] = tester(fn (PdoConnection $connection) => str_contains($connection->dsn, 'dbname=')
        ? FakePdoConnector::error("SQLSTATE[HY000] [1049] Unknown database 'bakery'")
        : FakePdoConnector::server('8.4.3', extra: ['show grants' => ['GRANT ALL PRIVILEGES ON *.* TO `bakery_app`@`%`']]));

    $report = $tester->test(mysqlSettings());

    expect($report->problem)->toBe(ConnectionProblem::UnknownDatabase)
        ->and($report->canCreateDatabase)->toBeTrue()
        ->and($report->message)->toBe('The database "bakery" does not exist.');
});

it('does not offer to create a database without the grant', function () {
    [$tester] = tester(fn (PdoConnection $connection) => str_contains($connection->dsn, 'dbname=')
        ? FakePdoConnector::error("SQLSTATE[HY000] [1049] Unknown database 'bakery'")
        : FakePdoConnector::server('8.4.3', extra: ['show grants' => ['GRANT SELECT ON `shop`.* TO `bakery_app`@`%`']]));

    expect($tester->test(mysqlSettings())->canCreateDatabase)->toBeFalse();
});

it('checks the PostgreSQL createdb right', function () {
    [$tester] = tester(fn (PdoConnection $connection) => str_contains($connection->dsn, 'dbname=postgres')
        ? FakePdoConnector::server('17.2 (Debian 17.2-1.pgdg120+1)', extra: ['select rolcreatedb or rolsuper from pg_roles where rolname = current_user' => ['1']])
        : FakePdoConnector::error('SQLSTATE[08006] [7] FATAL:  database "bakery" does not exist'));

    $report = $tester->test(pgsqlSettings());

    expect($report->canCreateDatabase)->toBeTrue()
        ->and($report->serverVersion)->toBe('17.2');
});

it('refuses servers that are too old', function (DatabaseSettings $settings, string $version, string $text) {
    [$tester] = tester(fn () => FakePdoConnector::server($version));

    $report = $tester->test($settings);

    expect($report->problem)->toBe(ConnectionProblem::VersionTooOld)
        ->and($report->message)->toContain($text);
})->with([
    'mysql 5.7' => fn () => [mysqlSettings(), '5.7.44-log', 'MySQL 5.7.44 is too old. Verso needs MySQL 8.0.0'],
    'mariadb 10.4' => fn () => [mysqlSettings(['driver' => 'mariadb']), '10.4.32-MariaDB-1:10.4.32', 'MariaDB 10.4.32 is too old'],
    'postgres 13' => fn () => [pgsqlSettings(), '13.16', 'PostgreSQL 13.16 is too old'],
]);

it('accepts MariaDB 10.11 even when the user picked MySQL', function () {
    [$tester] = tester(fn () => FakePdoConnector::server('10.11.8-MariaDB-ubu2204'));

    $report = $tester->test(mysqlSettings());

    expect($report->ok)->toBeTrue()
        ->and($report->serverKind)->toBe('mariadb');
});

it('warns about Verso tables and respects the table prefix', function () {
    [$tester] = tester(fn () => FakePdoConnector::server('8.4.3', ['users', 'entries', 'settings', 'wp_posts']));

    $clash = $tester->test(mysqlSettings());
    $prefixed = $tester->test(mysqlSettings(['prefix' => 'vs_']));

    expect($clash->ok)->toBeTrue()
        ->and($clash->hasVersoTables())->toBeTrue()
        ->and($clash->versoTables)->toBe(['users', 'entries', 'settings'])
        ->and($prefixed->hasVersoTables())->toBeFalse()
        ->and($prefixed->tableCount)->toBe(4)
        ->and($prefixed->message)->toContain('4 other tables');
});

it('accepts a SQLite file that does not exist yet in a writable folder', function () {
    [$tester, $connector] = tester(fn () => FakePdoConnector::error('never called'));

    $report = $tester->test(new DatabaseSettings('sqlite', 'storage/framework/testing/new-site.sqlite'));

    expect($report->ok)->toBeTrue()
        ->and($report->fileMissing)->toBeTrue()
        ->and($connector->calls)->toBe([]);
});

it('refuses a SQLite file in a missing folder', function () {
    [$tester] = tester(fn () => FakePdoConnector::error('never called'));

    expect($tester->test(new DatabaseSettings('sqlite', 'no/such/folder/site.sqlite'))->problem)->toBe(ConnectionProblem::SqliteNotWritable);
});

it('reports a missing PDO extension', function () {
    config(['verso.install.database.drivers.mysql.extension' => 'pdo_imaginary']);
    [$tester] = tester(fn () => FakePdoConnector::server('8.4.3'));
    $tester = new DatabaseConnectionTester(new FakePdoConnector(fn () => FakePdoConnector::server('8.4.3')), [], (array) config('verso.install.database.drivers'), [], base_path(), 3);

    expect($tester->test(mysqlSettings())->problem)->toBe(ConnectionProblem::DriverMissing)
        ->and($tester->driverAvailable('mysql'))->toBeFalse()
        ->and($tester->driverAvailable('sqlite'))->toBeTrue();
});

it('never puts the password in the report', function () {
    [$tester] = tester(fn () => FakePdoConnector::error("SQLSTATE[HY000] [1045] Access denied for user 'bakery_app'"));

    expect(json_encode($tester->test(mysqlSettings())->toArray()))->not->toContain('Rye&Spelt-2026');
});
