<?php

use App\Verso\Plugins\Support\PluginConfig;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    $this->cacheFolder = storage_path('framework/testing/config-cache-'.uniqid());
    File::ensureDirectoryExists($this->cacheFolder);
});

afterEach(function () {
    app()->instance('config_loaded_from_cache', false);
    File::deleteDirectory($this->cacheFolder);
});

// Makes the running app believe its config comes from bootstrap/cache/config.php.
function pretendConfigIsCached(): void
{
    app()->instance('config_loaded_from_cache', true);
}

it('fills the config of a plugin enabled after the config was cached', function () {
    config()->offsetUnset('analytics');
    pretendConfigIsCached();

    expect(app(PluginConfig::class)->isCached())->toBeTrue();

    PluginFixtures::boot('analytics');

    expect(config('analytics.endpoint'))->toBe('_verso/collect')
        ->and(config('analytics.bots'))->toContain('bot');
});

it('keeps cached values over the plugin file', function () {
    config(['analytics' => ['endpoint' => 'stats/hit', 'rate_limit' => 30]]);
    pretendConfigIsCached();

    PluginFixtures::boot('analytics');

    expect(config('analytics.endpoint'))->toBe('stats/hit')
        ->and(config('analytics.rate_limit'))->toBe(30)
        ->and(config('analytics.top_limit'))->toBeNull();
});

it('lets app values win over plugin defaults without a cache', function () {
    config(['shop' => ['per_page' => 30]]);

    PluginFixtures::boot('shop');

    expect(config('shop.per_page'))->toBe(30)
        ->and(config('shop.routes.cart'))->toBe('cart');
});

it('loads the config of every plugin in the console, enabled or not', function () {
    config()->offsetUnset('newsletter');
    config()->offsetUnset('memberships');

    $config = app(PluginConfig::class);
    $config->mergeAll(PluginFixtures::use(base_path('plugins'))->all());

    expect(config('newsletter.confirm_hours'))->not->toBeNull()
        ->and(config('memberships'))->toBeArray()->not->toBeEmpty();
});

it('lists config files by key', function () {
    $plugin = PluginFixtures::use(base_path('plugins'))->get('shop');

    expect(array_keys(app(PluginConfig::class)->files($plugin)))->toBe(['shop']);
});

it('does not load every plugin config again once the config is cached', function () {
    config()->offsetUnset('memberships');
    pretendConfigIsCached();

    app(PluginConfig::class)->mergeAll(PluginFixtures::use(base_path('plugins'))->all());

    expect(config('memberships'))->toBeNull();
});

it('keeps plugin config working with php artisan config:cache', function () {
    $database = $this->cacheFolder.'/verso.sqlite';
    touch($database);

    $env = [
        'APP_ENV' => 'local',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $database,
        'DB_URL' => '',
        'CACHE_STORE' => 'array',
        'APP_CONFIG_CACHE' => $this->cacheFolder.'/config.php',
        'ANALYTICS_TOP_LIMIT' => '17',
        'SHOP_PER_PAGE' => '9',
    ];

    $artisan = function (string ...$arguments) use ($env): string {
        $process = new Process([PHP_BINARY, base_path('artisan'), ...$arguments], base_path(), $env, null, 120);
        $process->mustRun();

        return trim($process->getOutput());
    };

    $artisan('migrate', '--force');
    $artisan('tinker', '--execute', "app(App\\Verso\\Plugins\\Actions\\EnablePlugin::class)->handle('analytics');");
    $artisan('config:cache');

    expect(file_exists($this->cacheFolder.'/config.php'))->toBeTrue();

    $cached = require $this->cacheFolder.'/config.php';

    expect($cached['analytics']['top_limit'])->toBe(17)
        ->and($cached['shop']['per_page'])->toBe(9);

    $read = fn (string $code): string => $artisan('tinker', '--execute', $code);

    expect($read("echo config('analytics.top_limit');"))->toBe('17')
        ->and($read("echo config('analytics.endpoint');"))->toBe('_verso/collect');

    $artisan('tinker', '--execute', "app(App\\Verso\\Plugins\\Actions\\EnablePlugin::class)->handle('shop');");

    expect($read("echo config('shop.per_page');"))->toBe('9')
        ->and($read("echo config('shop.routes.checkout');"))->toBe('checkout');
});
