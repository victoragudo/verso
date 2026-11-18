<?php

use App\Verso\Install\Database\DatabaseConnectionTester;
use App\Verso\Install\Database\DatabaseCreator;
use App\Verso\Install\Database\PdoConnection;
use App\Verso\Install\Pipeline\InstallProgress;
use Illuminate\Filesystem\Filesystem;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakePdoConnector;
use Tests\Support\InstallFixtures;

beforeEach(function () {
    $this->paths = InstallFixtures::fresh();
    $this->sqlite = $this->paths['folder'].'/site.sqlite';
});

// Session data for a wizard where the given steps are done.
function wizardDone(array $steps, string $sqlite = 'database/database.sqlite'): array
{
    $answers = InstallFixtures::answers($sqlite);

    return ['verso_install' => [
        'steps' => array_intersect_key($answers, array_flip($steps)),
        'done' => $steps,
    ]];
}

// Use a fake database server for MySQL and PostgreSQL tests.
function fakeServer(Closure $answer): FakePdoConnector
{
    $connector = new FakePdoConnector($answer);
    app()->bind(DatabaseConnectionTester::class, fn () => new DatabaseConnectionTester($connector, (array) config('verso.install.database.min_versions'), (array) config('verso.install.database.drivers'), ['users', 'entries'], base_path(), 2));
    app()->bind(DatabaseCreator::class, fn () => new DatabaseCreator($connector, app(DatabaseConnectionTester::class), new Filesystem, base_path()));

    return $connector;
}

// Runs the callback with another default connection, like a machine with other DB_* values.
// The test database comes back after, even when an assertion fails, so later tests keep working.
function withDefaultConnection(string $connection, Closure $callback): void
{
    $original = config('database.default');
    config(['database.default' => $connection]);

    try {
        $callback();
    } finally {
        config(['database.default' => $original]);
    }
}

function mysqlForm(array $overrides = []): array
{
    return $overrides + [
        'driver' => 'mysql', 'host' => 'db.lisbonfood.internal', 'port' => 3306, 'database' => 'lisbon_food',
        'username' => 'lisbon_app', 'password' => 'Pastel-de-nata-77', 'prefix' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
    ];
}

it('starts at the welcome step and picks the browser language', function () {
    $this->get('/install')->assertRedirect('/install/welcome');

    $this->get('/install/welcome', ['Accept-Language' => 'es-ES,es;q=0.9'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('install/wizard')
            ->where('step', 'welcome')
            ->where('values.language', 'es')
            ->where('steps.0.key', 'welcome')
            ->has('languages.es'));
});

it('validates the language', function () {
    $this->post('/install/welcome', ['language' => 'klingon'])->assertSessionHasErrors('language');
    $this->post('/install/welcome', ['language' => 'pt-BR'])->assertRedirect('/install/requirements');
});

it('does not let anyone skip steps', function () {
    $this->get('/install/site')->assertRedirect('/install/welcome');
    $this->post('/install/site', [])->assertRedirect('/install/welcome');
    $this->get('/install/teleport')->assertNotFound();
});

it('shows the server checks with fixes and blocks on failures', function () {
    config(['verso.install.php_version' => '99.0.0']);

    $this->withSession(wizardDone(['welcome']))->get('/install/requirements')
        ->assertInertia(fn (Assert $page) => $page
            ->where('ready', false)
            ->where('requirements.0.passed', false)
            ->where('requirements.0.fix', 'Ask your host to switch this site to PHP 99.0.0 or newer.'));

    $this->withSession(wizardDone(['welcome']))->post('/install/requirements')->assertSessionHasErrors('requirements');
});

it('passes the server checks', function () {
    $this->withSession(wizardDone(['welcome']))->post('/install/requirements')->assertRedirect('/install/database');
});

it('lists drivers with their state and suggests SQLite', function () {
    // CI runs the suite on MySQL and Postgres too, so the test sets the machine default itself.
    withDefaultConnection('sqlite', fn () => $this->withSession(wizardDone(['welcome', 'requirements']))->get('/install/database')
        ->assertInertia(fn (Assert $page) => $page
            ->where('values.driver', 'sqlite')
            ->where('values.database', 'database/database.sqlite')
            ->where('drivers.0.value', 'sqlite')
            ->where('drivers.0.available', true)
            ->where('drivers.1.port', 3306)
            ->where('drivers.3.port', 5432)
            ->where('detected', null)));
});

it('saves a SQLite database in a writable folder', function () {
    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', ['driver' => 'sqlite', 'database' => $this->sqlite])
        ->assertRedirect('/install/site')
        ->assertSessionHas('verso_install.steps.database.database', $this->sqlite);
});

it('validates server database fields', function () {
    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', ['driver' => 'mysql', 'host' => 'bad host!', 'port' => 70000, 'database' => 'drop table;', 'username' => ''])
        ->assertSessionHasErrors(['host', 'port', 'database', 'username', 'charset', 'collation']);

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', ['driver' => 'pgsql', 'host' => 'pg.internal', 'port' => 5432, 'database' => 'food', 'username' => 'app', 'sslmode' => 'sometimes'])
        ->assertSessionHasErrors(['sslmode']);

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', ['driver' => 'oracle'])
        ->assertSessionHasErrors(['driver']);
});

it('refuses a database that does not answer', function () {
    fakeServer(fn () => FakePdoConnector::error("SQLSTATE[HY000] [1045] Access denied for user 'lisbon_app'"));

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', mysqlForm())
        ->assertSessionHasErrors(['connection' => 'Access denied for user \'lisbon_app\' Check the username and password.']);
});

it('creates a missing database when asked', function () {
    $created = false;
    fakeServer(function (PdoConnection $connection) use (&$created) {
        if (str_contains($connection->dsn, 'dbname=') && ! $created) {
            return FakePdoConnector::error("SQLSTATE[HY000] [1049] Unknown database 'lisbon_food'");
        }

        return new class($created) extends PDO
        {
            public function __construct(private bool &$created)
            {
                parent::__construct('sqlite::memory:');
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                return parent::prepare('select 1 where 0 = ?');
            }

            public function exec(string $statement): int|false
            {
                $this->created = str_starts_with($statement, 'create database `lisbon_food`');

                return 1;
            }

            public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
            {
                return parent::query(str_contains($query, 'version') ? "select '8.4.3'" : 'select null where 0');
            }
        };
    });

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', mysqlForm(['create_database' => true]))
        ->assertRedirect('/install/site');

    expect($created)->toBeTrue();
});

it('asks for a prefix when Verso tables already exist', function () {
    fakeServer(fn () => FakePdoConnector::server('8.4.3', ['users', 'entries'], ['select 1 from `users` limit 1' => ['1']]));

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', mysqlForm())
        ->assertSessionHasErrors('prefix');

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', mysqlForm(['prefix' => 'lf_']))
        ->assertRedirect('/install/site');
});

it('accepts the SQLite file of a fresh project, also when an older version migrated it', function () {
    touch($this->sqlite);

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', ['driver' => 'sqlite', 'database' => $this->sqlite, 'prefix' => ''])
        ->assertRedirect('/install/site');

    $pdo = new PDO('sqlite:'.$this->sqlite);
    $pdo->exec('create table users (id integer primary key, email text)');
    $pdo->exec('create table entries (id integer primary key)');

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', ['driver' => 'sqlite', 'database' => $this->sqlite, 'prefix' => ''])
        ->assertRedirect('/install/site');

    $pdo->exec("insert into users (email) values ('ines@lisbonfood.example')");

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->post('/install/database', ['driver' => 'sqlite', 'database' => $this->sqlite, 'prefix' => ''])
        ->assertSessionHasErrors('prefix');
});

it('never sends a saved password back and keeps it when the field stays empty', function () {
    fakeServer(fn () => FakePdoConnector::server('8.4.3'));
    $session = wizardDone(['welcome', 'requirements']);

    $this->withSession($session)->post('/install/database', mysqlForm())->assertRedirect('/install/site');
    $saved = session('verso_install');

    $this->withSession(['verso_install' => $saved])->get('/install/database')
        ->assertInertia(fn (Assert $page) => $page
            ->missing('values.password')
            ->where('savedSecrets', ['password'])
            ->where('values.host', 'db.lisbonfood.internal'));

    $this->withSession(['verso_install' => $saved])
        ->post('/install/database', mysqlForm(['password' => '', 'keep' => ['password'], 'port' => 3307]))
        ->assertSessionHas('verso_install.steps.database.password', 'Pastel-de-nata-77')
        ->assertSessionHas('verso_install.steps.database.port', 3307);
});

it('offers working DB_* settings from the environment in one click', function () {
    fakeServer(fn () => FakePdoConnector::server('8.4.3'));
    // Every DB_* value is set here, so the test does not depend on the DB_* variables of the machine.
    config([
        'database.connections.mysql.host' => 'mysql',
        'database.connections.mysql.port' => '3306',
        'database.connections.mysql.database' => 'verso',
        'database.connections.mysql.username' => 'verso',
        'database.connections.mysql.password' => 'from-docker-secret',
    ]);

    withDefaultConnection('mysql', function () {
        $this->withSession(wizardDone(['welcome', 'requirements']))->get('/install/database')
            ->assertInertia(fn (Assert $page) => $page
                ->where('values.driver', 'mysql')
                ->where('values.host', 'mysql')
                ->where('detected.settings.host', 'mysql')
                ->where('detected.settings.has_password', true)
                ->missing('detected.settings.password'));

        $this->withSession(wizardDone(['welcome', 'requirements']))
            ->post('/install/database', ['use_detected' => true])
            ->assertRedirect('/install/site')
            ->assertSessionHas('verso_install.steps.database.password', 'from-docker-secret');
    });
});

it('validates the site and admin account', function (array $input, array $errors) {
    $valid = InstallFixtures::answers($this->sqlite)['site'];
    $valid['admin_password_confirmation'] = $valid['admin_password'];

    $this->withSession(wizardDone(['welcome', 'requirements', 'database']))
        ->post('/install/site', array_merge($valid, $input))
        ->assertSessionHasErrors($errors);
})->with([
    'short password' => [['admin_password' => 'bread', 'admin_password_confirmation' => 'bread'], ['admin_password']],
    'no numbers' => [['admin_password' => 'sardinesandvinho', 'admin_password_confirmation' => 'sardinesandvinho'], ['admin_password']],
    'confirmation' => [['admin_password_confirmation' => 'something-else-9'], ['admin_password']],
    'email' => [['admin_email' => 'ines-at-lisbon'], ['admin_email']],
    'url' => [['site_url' => 'ftp://lisbonfood.example'], ['site_url']],
    'timezone' => [['timezone' => 'Europe/Atlantis'], ['timezone']],
    'locale' => [['locale' => 'xx'], ['locale']],
    'name' => [['site_name' => ''], ['site_name']],
]);

it('saves the site step without the confirmation and detects the address', function () {
    $this->withSession(wizardDone(['welcome', 'requirements', 'database']))->get('/install/site')
        ->assertInertia(fn (Assert $page) => $page->where('values.site_url', rtrim(url('/'), '/'))->has('timezones'));

    $site = InstallFixtures::answers($this->sqlite)['site'];

    $this->withSession(wizardDone(['welcome', 'requirements', 'database']))
        ->post('/install/site', $site + ['admin_password_confirmation' => $site['admin_password']])
        ->assertRedirect('/install/services')
        ->assertSessionMissing('verso_install.steps.site.admin_password_confirmation')
        ->assertSessionHas('verso_install.steps.site.admin_email', 'ines@lisbonfood.example');
});

it('validates the services', function (array $input, array $errors) {
    $this->withSession(wizardDone(['welcome', 'requirements', 'database', 'site']))
        ->post('/install/services', $input + ['mailer' => 'log', 'cache_driver' => 'file', 'session_driver' => 'file', 'queue_driver' => 'sync', 'storage' => 'local'])
        ->assertSessionHasErrors($errors);
})->with([
    'smtp without host' => [['mailer' => 'smtp'], ['mail_host', 'mail_port', 'mail_from_address']],
    'unknown cache' => [['cache_driver' => 'memcached'], ['cache_driver']],
    's3 without keys' => [['storage' => 's3'], ['s3_key', 's3_secret', 's3_region', 's3_bucket']],
    'bad endpoint' => [['storage' => 's3', 's3_endpoint' => 'not a url'], ['s3_endpoint']],
]);

it('refuses Redis when PHP has no Redis client', function () {
    if (extension_loaded('redis') || class_exists('Predis\Client')) {
        $this->markTestSkipped('This PHP has a Redis client.');
    }

    $this->withSession(wizardDone(['welcome', 'requirements', 'database', 'site']))
        ->post('/install/services', ['mailer' => 'log', 'cache_driver' => 'redis', 'session_driver' => 'file', 'queue_driver' => 'sync', 'storage' => 'local', 'redis_host' => '127.0.0.1', 'redis_port' => 6379])
        ->assertSessionHasErrors('cache_driver');
});

it('saves the default services (skip)', function () {
    $this->withSession(wizardDone(['welcome', 'requirements', 'database', 'site']))
        ->post('/install/services', ['mailer' => 'log', 'cache_driver' => 'file', 'session_driver' => 'file', 'queue_driver' => 'sync', 'storage' => 'local'])
        ->assertRedirect('/install/content');
});

it('lists themes and plugins and validates the content step', function () {
    $session = wizardDone(['welcome', 'requirements', 'database', 'site', 'services']);

    $this->withSession($session)->get('/install/content')
        ->assertInertia(fn (Assert $page) => $page
            ->where('themes.0.slug', 'ashgrove')
            ->where('themes.0.recommended', true)
            ->where('values.theme', 'ashgrove')
            ->where('plugins', fn ($plugins) => collect($plugins)->pluck('slug')->contains('reading-time')));

    $this->withSession($session)->post('/install/content', ['demo' => true, 'theme' => 'neon-unicorn', 'plugins' => ['crypto-miner']])
        ->assertSessionHasErrors(['theme', 'plugins.0']);

    $this->withSession($session)->post('/install/content', ['demo' => true, 'theme' => 'default', 'plugins' => ['reading-time']])
        ->assertRedirect('/install/review');
});

it('shows a masked review and starts the install', function () {
    $session = wizardDone(['welcome', 'requirements', 'database', 'site', 'services', 'content'], $this->sqlite);

    $this->withSession($session)->get('/install/review')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.Site.Name', 'Lisbon Food Notes')
            ->where('summary.Admin account.Password', '••••••••')
            ->where('summary.Content.Plugins', 'reading-time')
            ->where('editSteps.Admin account', 'site')
            ->where('editSteps.Services', 'services'))
        ->assertDontSee('sardines-and-vinho-2026');

    $this->withSession($session)->post('/install/review')->assertRedirect('/install/install');

    expect(app(InstallProgress::class)->exists())->toBeTrue();
});

it('tests a connection from the button and returns JSON', function () {
    fakeServer(fn () => FakePdoConnector::server('17.2', [], []));

    $this->withSession(wizardDone(['welcome', 'requirements']))
        ->postJson('/install/test/database', ['driver' => 'pgsql', 'host' => 'pg.lisbonfood.internal', 'port' => 5432, 'database' => 'food', 'username' => 'app', 'password' => 'Pastel-de-nata-77', 'sslmode' => 'require'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('server_kind', 'pgsql')
        ->assertJsonMissingPath('password');
});

it('rate limits the test buttons', function () {
    config(['verso.install.test_attempts_per_minute' => 2]);
    $form = ['driver' => 'sqlite', 'database' => $this->sqlite];

    $this->postJson('/install/test/database', $form)->assertOk();
    $this->postJson('/install/test/database', $form)->assertOk();
    $this->postJson('/install/test/database', $form)->assertTooManyRequests();
});

it('sends a test email with the chosen mailer', function () {
    config(['mail.default' => 'array']);

    $this->postJson('/install/test/mail', ['mailer' => 'log', 'test_email' => 'ines@lisbonfood.example'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('message', 'A test email was sent to ines@lisbonfood.example.');
});

it('reports a failing SMTP server without the password', function () {
    $this->postJson('/install/test/mail', [
        'mailer' => 'smtp', 'mail_host' => '127.0.0.1', 'mail_port' => 1, 'mail_encryption' => 'tls',
        'mail_username' => 'ines', 'mail_password' => 'Smtp-Secret-55', 'mail_from_address' => 'news@lisbonfood.example', 'test_email' => 'ines@lisbonfood.example',
    ])
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertDontSee('Smtp-Secret-55');
});

it('explains missing packages for Redis and S3', function () {
    if (extension_loaded('redis') || class_exists('Predis\Client') || class_exists('League\Flysystem\AwsS3V3\AwsS3V3Adapter')) {
        $this->markTestSkipped('This PHP has the optional packages.');
    }

    $this->postJson('/install/test/redis', ['redis_host' => '127.0.0.1', 'redis_port' => 6379])->assertJsonPath('ok', false)->assertJsonPath('message', fn (string $message) => str_contains($message, 'phpredis'));
    $this->postJson('/install/test/storage', ['storage' => 's3', 's3_key' => 'AKIA', 's3_secret' => 'secret', 's3_region' => 'eu-west-1', 's3_bucket' => 'lisbon'])->assertJsonPath('message', fn (string $message) => str_contains($message, 'flysystem-aws-s3-v3'));
});

it('refuses to run before the review', function () {
    $this->postJson('/install/run')->assertStatus(409);
});

it('closes the whole wizard once installed', function () {
    file_put_contents($this->paths['lock'], 'Installed');

    $this->get('/install/welcome')->assertNotFound();
    $this->post('/install/run')->assertNotFound();
    $this->postJson('/install/test/database', [])->assertNotFound();
});
