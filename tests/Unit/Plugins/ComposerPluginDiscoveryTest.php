<?php

use App\Verso\Plugins\PluginDiscovery;
use Tests\Support\PluginFixtures;

function composerInstalledFixture(): string
{
    return dirname(__DIR__, 2).'/Fixtures/composer/vendor/composer/installed.json';
}

function composerDiscovery(array $paths = []): PluginDiscovery
{
    return new PluginDiscovery($paths, 'verso.json', '0.1.0', composerInstalledFixture());
}

it('finds only the packages that declare extra.verso', function () {
    $packages = composerDiscovery()->composerPackages();

    expect(array_column($packages, 'name'))->toBe(['harbor/podcast-player', 'harbor/newsletter-signup', 'harbor/broken-banner']);
});

it('resolves the install path next to the composer folder', function () {
    $packages = composerDiscovery()->composerPackages();

    expect($packages[0]['path'])->toBe(dirname(composerInstalledFixture(), 2).'/harbor/podcast-player');
});

it('reads the manifest from extra.verso and fills slug and version from the package', function () {
    $plugins = composerDiscovery()->discover();
    $podcast = $plugins['podcast-player'];

    expect($podcast->isValid())->toBeTrue()
        ->and($podcast->manifest->name)->toBe('Podcast Player')
        ->and($podcast->manifest->version)->toBe('1.4.2')
        ->and($podcast->manifest->provider)->toBe('Harbor\\PodcastPlayer\\PodcastPlayerServiceProvider')
        ->and($podcast->compatible)->toBeTrue();
});

it('reads the manifest file inside the package when extra.verso is true', function () {
    $newsletter = composerDiscovery()->discover()['newsletter-signup'];

    expect($newsletter->isValid())->toBeTrue()
        ->and($newsletter->manifest->version)->toBe('0.9.0')
        ->and($newsletter->manifest->description)->toBe('Adds a newsletter signup box to the theme.');
});

it('keeps errors of a broken package manifest instead of throwing', function () {
    $banner = composerDiscovery()->discover()['broken-banner'];

    expect($banner->isValid())->toBeFalse()
        ->and(implode(' ', $banner->errors))->toContain('provider');
});

it('lists plugin folders and Composer packages together, sorted by slug', function () {
    $plugins = composerDiscovery([PluginFixtures::path()])->discover();

    expect(array_keys($plugins))->toBe([
        'broken-banner', 'broken-json', 'event-calendar', 'future-gallery', 'missing-provider', 'newsletter-signup', 'not-a-provider', 'podcast-player',
    ]);
});

it('lets a plugin folder win over a Composer package with the same slug', function () {
    $folder = storage_path('framework/testing/plugins-override/podcast-player');
    @mkdir($folder, 0755, true);
    file_put_contents($folder.'/verso.json', json_encode([
        'name' => 'Podcast Player Local Copy', 'slug' => 'podcast-player', 'version' => '2.0.0', 'provider' => 'Local\\PodcastProvider',
    ]));

    $plugins = composerDiscovery([dirname($folder)])->discover();

    expect($plugins['podcast-player']->manifest->name)->toBe('Podcast Player Local Copy');
});

it('finds nothing when the installed file is missing or turned off', function () {
    expect((new PluginDiscovery([], 'verso.json', '0.1.0', '/srv/missing/installed.json'))->discover())->toBe([])
        ->and((new PluginDiscovery([], 'verso.json', '0.1.0', null))->discover())->toBe([]);
});

it('reads the Composer 1 format, a plain list of packages', function () {
    $file = storage_path('framework/testing/composer-one/vendor/composer/installed.json');
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, json_encode([[
        'name' => 'harbor/podcast-player',
        'version' => '1.0.0',
        'install-path' => dirname(composerInstalledFixture(), 2).'/harbor/podcast-player',
        'extra' => ['verso' => ['name' => 'Podcast Player', 'provider' => 'Harbor\\PodcastPlayer\\PodcastPlayerServiceProvider']],
    ]]));

    $plugins = (new PluginDiscovery([], 'verso.json', '0.1.0', $file))->discover();

    expect(array_keys($plugins))->toBe(['podcast-player'])
        ->and($plugins['podcast-player']->manifest->version)->toBe('1.0.0');
});

it('ignores an installed file that is not valid JSON', function () {
    $file = storage_path('framework/testing/composer-broken/installed.json');
    @mkdir(dirname($file), 0755, true);
    file_put_contents($file, '{"packages": [');

    expect((new PluginDiscovery([], 'verso.json', '0.1.0', $file))->composerPackages())->toBe([]);
});

it('reads the vendor installed file by default', function () {
    expect(config('verso.plugins.composer_installed'))->toBe(base_path('vendor/composer/installed.json'));
});
