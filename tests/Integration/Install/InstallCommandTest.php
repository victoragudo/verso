<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Settings\Settings;
use App\Verso\Install\Wizard\WizardOptions;
use Dotenv\Dotenv;
use Tests\Support\InstallFixtures;

beforeEach(function () {
    $this->paths = InstallFixtures::fresh();
    $this->sqlite = $this->paths['folder'].'/garden.sqlite';
});

afterEach(function () {
    config(['database.connections.sqlite.database' => ':memory:']);
    app('db')->purge('sqlite');
});

function installFlags(string $sqlite, array $overrides = []): array
{
    return array_merge([
        '--no-interaction' => true,
        '--db-driver' => 'sqlite',
        '--db-database' => $sqlite,
        '--site-name' => 'Northwind Garden Club',
        '--site-url' => 'https://northwindgardens.example',
        '--timezone' => 'Europe/London',
        '--admin-name' => 'Agnes Holloway',
        '--admin-email' => 'agnes@northwindgardens.example',
        '--admin-password' => 'dahlias-in-september-12',
        '--plugins' => 'reading-time',
    ], $overrides);
}

it('installs without questions from flags (Docker and CI)', function () {
    $this->artisan('verso:install', installFlags($this->sqlite, ['--demo' => true]))
        ->expectsOutputToContain('Create the tables')
        ->expectsOutputToContain('sign in as agnes@northwindgardens.example')
        ->assertSuccessful();

    $settings = app(Settings::class);
    $settings->forgetCache();
    $env = Dotenv::parse((string) file_get_contents($this->paths['env']));

    expect(User::firstWhere('email', 'agnes@northwindgardens.example')->role)->toBe(Role::Admin)
        ->and($settings->get('site.name'))->toBe('Northwind Garden Club')
        ->and($settings->get('site.timezone'))->toBe('Europe/London')
        ->and($settings->get('plugins.enabled'))->toBe(['reading-time'])
        ->and(Entry::count())->toBeGreaterThan(0)
        ->and($settings->get('theme.active'))->toBe('ashgrove')
        ->and(User::where('email', 'pablo@verso.test')->value('bio'))->not->toBeNull()
        ->and($env['APP_URL'])->toBe('https://northwindgardens.example')
        ->and(file_exists($this->paths['lock']))->toBeTrue()
        ->and(file_exists($this->paths['state']))->toBeFalse();
});

it('reads missing values from VERSO_INSTALL_* settings', function () {
    config([
        'verso.install.cli.site_name' => 'Harbor Lane Bakery',
        'verso.install.cli.admin_email' => 'marta@harborlane.example',
        'verso.install.cli.admin_password' => 'Sourdough-every-day-7',
    ]);

    $this->artisan('verso:install', ['--no-interaction' => true, '--db-driver' => 'sqlite', '--db-database' => $this->sqlite, '--site-url' => 'https://harborlane.example'])
        ->assertSuccessful();

    expect(User::where('email', 'marta@harborlane.example')->exists())->toBeTrue();
});

it('shows validation errors and installs nothing', function () {
    $this->artisan('verso:install', installFlags($this->sqlite, ['--admin-email' => 'agnes-at-northwind', '--admin-password' => 'short']))
        ->expectsOutputToContain('admin email')
        ->assertFailed();

    expect(file_exists($this->sqlite))->toBeFalse()
        ->and(file_exists($this->paths['lock']))->toBeFalse();
});

it('fails clearly when the database cannot be reached', function () {
    $this->artisan('verso:install', installFlags($this->sqlite, [
        '--db-driver' => 'pgsql', '--db-host' => '127.0.0.1', '--db-port' => '1', '--db-database' => 'garden', '--db-username' => 'garden', '--db-password' => 'Tulip-Bulbs-42', '--db-sslmode' => 'disable',
    ]))
        ->expectsOutputToContain('Check the host and port')
        ->doesntExpectOutputToContain('Tulip-Bulbs-42')
        ->assertFailed();
});

it('stops when the server checks fail', function () {
    config(['verso.install.php_version' => '99.0.0']);

    $this->artisan('verso:install', installFlags($this->sqlite))
        ->expectsOutputToContain('Ask your host to switch this site to PHP 99.0.0')
        ->assertFailed();
});

it('refuses to run again unless forced', function () {
    file_put_contents($this->paths['lock'], 'Installed');

    $this->artisan('verso:install', installFlags($this->sqlite))
        ->expectsOutputToContain('Use --force')
        ->assertFailed();

    $this->artisan('verso:install', installFlags($this->sqlite, ['--force' => true]))->assertSuccessful();
});

it('never runs again on an installed production site', function () {
    file_put_contents($this->paths['lock'], 'Installed');
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('verso:install', installFlags($this->sqlite, ['--force' => true]))
        ->expectsOutputToContain('production site')
        ->assertFailed();

    app()->detectEnvironment(fn () => 'testing');
});

it('asks every question in interactive mode', function () {
    $this->artisan('verso:install')
        ->expectsChoice('Which database do you want to use?', 'sqlite', ['sqlite' => 'SQLite', 'mysql' => 'MySQL', 'mariadb' => 'MariaDB', 'pgsql' => 'PostgreSQL'])
        ->expectsQuestion('SQLite database file', $this->sqlite)
        ->expectsQuestion('Site name', 'Northwind Garden Club')
        ->expectsQuestion('Tagline (optional)', 'Seeds, soil and good company')
        ->expectsQuestion('Site address', 'https://northwindgardens.example')
        ->expectsChoice('Site language', 'en', app(WizardOptions::class)->languages())
        ->expectsQuestion('Time zone', 'Europe/London')
        ->expectsQuestion('Admin name', 'Agnes Holloway')
        ->expectsQuestion('Admin email', 'agnes@northwindgardens.example')
        ->expectsQuestion('Admin password', 'dahlias-in-september-12')
        ->expectsConfirmation('Set up mail, cache, sessions and queue now?', 'no')
        ->expectsConfirmation('Add demo content (posts, pages, photos and menus)?', 'no')
        ->expectsChoice('Theme', 'ashgrove', array_column(app(WizardOptions::class)->themes(), 'name', 'slug'))
        ->expectsChoice('Plugins to turn on', ['reading-time'], array_column(app(WizardOptions::class)->plugins(), 'name', 'slug'))
        ->expectsConfirmation('Install Verso now?', 'yes')
        ->assertSuccessful();

    $settings = app(Settings::class);
    $settings->forgetCache();

    expect($settings->get('site.tagline'))->toBe('Seeds, soil and good company')
        ->and($settings->get('theme.active'))->toBe('ashgrove')
        ->and(User::where('email', 'agnes@northwindgardens.example')->exists())->toBeTrue();
});

it('stops when the user does not confirm', function () {
    $this->artisan('verso:install', ['--db-driver' => 'sqlite', '--db-database' => $this->sqlite, '--site-name' => 'Northwind Garden Club', '--site-url' => 'https://northwindgardens.example', '--locale' => 'en', '--timezone' => 'UTC', '--admin-name' => 'Agnes Holloway', '--admin-email' => 'agnes@northwindgardens.example', '--admin-password' => 'dahlias-in-september-12', '--mail' => 'log', '--theme' => 'default', '--plugins' => '', '--tagline' => 'Seeds'])
        ->expectsConfirmation('Add demo content (posts, pages, photos and menus)?', 'no')
        ->expectsConfirmation('Install Verso now?', 'no')
        ->assertFailed();

    expect(file_exists($this->sqlite))->toBeFalse();
});
