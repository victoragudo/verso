<?php

use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\Exceptions\PluginCannotBeEnabled;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Support\PluginMigrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    $this->plugins = PluginFixtures::use(dirname(__DIR__, 2).'/Fixtures/migrating-plugins');
});

it('runs the plugin migrations when the plugin is enabled', function () {
    expect(Schema::hasTable('book_club_votes'))->toBeFalse();

    app(EnablePlugin::class)->handle('book-club');

    expect(Schema::hasTable('book_club_votes'))->toBeTrue()
        ->and(DB::table('migrations')->where('migration', '2026_11_20_000000_create_book_club_votes_table')->exists())->toBeTrue()
        ->and($this->plugins->isEnabled('book-club'))->toBeTrue();
});

it('does not run the same migration twice', function () {
    app(EnablePlugin::class)->handle('book-club');
    app(EnablePlugin::class)->handle('book-club');

    expect(DB::table('migrations')->where('migration', '2026_11_20_000000_create_book_club_votes_table')->count())->toBe(1);
});

it('keeps the plugin disabled when a migration fails', function () {
    expect(fn () => app(EnablePlugin::class)->handle('faulty-ledger'))
        ->toThrow(PluginCannotBeEnabled::class, 'needs a currency column');

    expect($this->plugins->isEnabled('faulty-ledger'))->toBeFalse();
});

it('reports the migrations that ran and ignores plugins without migrations', function () {
    $migrator = app(PluginMigrator::class);

    expect($migrator->migrate($this->plugins->get('book-club')))->toBe(['2026_11_20_000000_create_book_club_votes_table']);

    $calendar = PluginFixtures::use()->get('not-a-provider');

    expect($migrator->path($calendar))->toBeNull()
        ->and($migrator->migrate($calendar))->toBe([]);
});

it('adds the migrations of enabled plugins to the migrator paths', function () {
    $this->plugins->register($this->plugins->get('book-club'));

    expect(app('migrator')->paths())->toContain(dirname(__DIR__, 2).'/Fixtures/migrating-plugins/book-club/database/migrations');
});

it('reads the migrations folder from config', function () {
    config(['verso.plugins.migrations_path' => 'storage/schema']);
    app()->forgetInstance(PluginMigrator::class);

    expect(app(PluginMigrator::class)->path(app(PluginManager::class)->get('book-club')))->toBeNull();
});

it('enables the plugin without migrating when migrate_on_enable is off', function () {
    config(['verso.plugins.migrate_on_enable' => false]);
    app()->forgetInstance(PluginMigrator::class);

    app(EnablePlugin::class)->handle('book-club');

    expect(Schema::hasTable('book_club_votes'))->toBeFalse()
        ->and($this->plugins->isEnabled('book-club'))->toBeTrue();
});

it('reads migrate_on_enable from config', function () {
    config(['verso.plugins.migrate_on_enable' => false]);
    app()->forgetInstance(PluginMigrator::class);

    expect(app(PluginMigrator::class)->runsOnEnable())->toBeFalse();
});
