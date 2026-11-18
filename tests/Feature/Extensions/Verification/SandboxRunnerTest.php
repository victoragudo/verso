<?php

use App\Verso\Extensions\Verification\Sandbox\SandboxResult;
use App\Verso\Extensions\Verification\Sandbox\SandboxRunner;
use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Plugins\Support\PluginMigrator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ExtensionSandbox;
use Tests\Support\VerificationFixtures;

beforeEach(function () {
    $this->sandbox = ExtensionSandbox::create();
    config(['verso.extensions.verification.sandbox.enabled' => true, 'verso.extensions.verification.sandbox.timeout' => 60]);
});

afterEach(function () {
    VerificationFixtures::cleanup();
    $this->sandbox->destroy();
});

it('boots a clean plugin, runs its migrations up and down and leaves nothing behind', function () {
    $result = app(SandboxRunner::class)->run(VerificationFixtures::path('harbor-tides'), 'harbor-tides');

    expect($result->status)->toBe(SandboxResult::PASSED)
        ->and($result->passed())->toBeTrue()
        ->and($result->errors)->toBe([])
        ->and($result->migrated)->toBe(['2026_12_01_000000_create_harbor_tide_times_table'])
        ->and($result->rolledBack)->toBe(['2026_12_01_000000_create_harbor_tide_times_table'])
        ->and($result->seconds)->toBeGreaterThan(0.0)
        ->and(File::directories($this->sandbox->root.'/storage/sandbox'))->toBe([])
        ->and(Schema::hasTable('harbor_tide_times'))->toBeFalse();
});

it('reports a migration that fails, with the file and line in the plugin', function () {
    $result = app(SandboxRunner::class)->run(VerificationFixtures::path('broken-ledger'), 'broken-ledger');

    expect($result->status)->toBe(SandboxResult::FAILED)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]['stage'])->toBe('migrate')
        ->and($result->errors[0]['message'])->toContain('no such table: donation_currencies')
        ->and($result->errors[0]['message'])->not->toContain($this->sandbox->root)
        ->and($result->errors[0]['file'])->toBe('database/migrations/2026_12_01_000000_create_donations_table.php')
        ->and($result->errors[0]['line'])->toBe(17);
});

it('reports a provider that fails to boot', function () {
    $result = app(SandboxRunner::class)->run(VerificationFixtures::path('stormy-boot'), 'stormy-boot');

    expect($result->errors)->toBe([[
        'stage' => 'boot',
        'message' => 'RuntimeException: The storm warning feed URL is missing.',
        'file' => 'src/StormyBootServiceProvider.php',
        'line' => 13,
    ]]);
});

it('lists the routes and content types the plugin adds, even when a migration fails', function () {
    $result = app(SandboxRunner::class)->run(VerificationFixtures::path('calendar-clash'), 'calendar-clash');

    expect(array_column($result->errors, 'stage'))->toBe(['migrate'])
        ->and($result->routes)->toEqualCanonicalizing([
            ['uri' => 'calendar/upcoming', 'name' => 'login', 'methods' => ['GET']],
            ['uri' => 'feed', 'name' => 'calendar.feed', 'methods' => ['GET']],
        ])
        ->and($result->registered['content_types'])->toBe(['post']);
});

it('skips the sandbox when it is turned off', function () {
    config(['verso.extensions.verification.sandbox.enabled' => false]);

    $runner = app(SandboxRunner::class);

    expect($runner->isEnabled())->toBeFalse()
        ->and($runner->run(VerificationFixtures::path('harbor-tides'), 'harbor-tides')->status)->toBe(SandboxResult::SKIPPED);
});

it('stops a plugin that takes too long', function () {
    config(['verso.extensions.verification.sandbox.timeout' => 1]);
    $path = VerificationFixtures::make('slow-tides', [
        'src/SlowTidesServiceProvider.php' => "<?php\n\nnamespace Verso\\Plugins\\SlowTides;\n\nuse App\\Verso\\Plugins\\PluginServiceProvider;\n\nclass SlowTidesServiceProvider extends PluginServiceProvider\n{\n    public function boot(): void\n    {\n        sleep(5);\n    }\n}\n",
    ]);

    expect(app(SandboxRunner::class)->run($path, 'slow-tides')->status)->toBe(SandboxResult::TIMEOUT);
});

it('reports a process that ends without a result', function () {
    config(['verso.extensions.verification.sandbox.php_binary' => '/usr/bin/true']);

    expect(app(SandboxRunner::class)->run(VerificationFixtures::path('harbor-tides'), 'harbor-tides')->status)->toBe(SandboxResult::CRASHED);
});

it('refuses to run the sandbox command outside the sandbox', function () {
    $this->artisan('verso:sandbox-plugin', ['path' => VerificationFixtures::path('harbor-tides'), '--database' => database_path('database.sqlite')])
        ->expectsOutputToContain('This command only runs inside the verification sandbox.')
        ->assertFailed();
});

it('reads the sandbox JSON safely', function () {
    $result = SandboxResult::fromArray([
        'migrated' => ['2026_12_01_000000_create_tide_charts'],
        'routes' => [['uri' => 'tides', 'name' => null, 'methods' => ['GET']], 'broken'],
        'registered' => ['blocks' => ['tide-chart']],
        'errors' => [['stage' => 'boot', 'message' => 'Missing key.'], 'broken'],
    ], 1.234, 'log line');

    expect($result->status)->toBe(SandboxResult::FAILED)
        ->and($result->routes)->toBe([['uri' => 'tides', 'name' => null, 'methods' => ['GET']]])
        ->and($result->errors)->toBe([['stage' => 'boot', 'message' => 'Missing key.', 'file' => null, 'line' => null]])
        ->and($result->toArray())->toMatchArray(['status' => 'failed', 'seconds' => 1.23, 'output' => 'log line', 'registered' => ['blocks' => ['tide-chart']]]);
});

it('rolls back the migrations of a plugin', function () {
    $plugin = app(PluginDiscovery::class)->load(VerificationFixtures::path('harbor-tides'));
    $migrator = app(PluginMigrator::class);

    expect($migrator->migrate($plugin))->toBe(['2026_12_01_000000_create_harbor_tide_times_table'])
        ->and(Schema::hasTable('harbor_tide_times'))->toBeTrue()
        ->and($migrator->rollback($plugin))->toBe(['2026_12_01_000000_create_harbor_tide_times_table'])
        ->and(Schema::hasTable('harbor_tide_times'))->toBeFalse()
        ->and($migrator->rollback($plugin))->toBe([]);
});
