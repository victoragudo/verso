<?php

use App\Verso\Plugins\PluginDiscovery;
use Tests\Support\PluginFixtures;

it('finds every plugin folder with a manifest, sorted by slug', function () {
    $plugins = (new PluginDiscovery([PluginFixtures::path()], 'verso.json', '0.1.0'))->discover();

    expect(array_keys($plugins))->toBe(['broken-json', 'event-calendar', 'future-gallery', 'missing-provider', 'not-a-provider']);
});

it('keeps errors of broken manifests instead of throwing', function () {
    $plugins = (new PluginDiscovery([PluginFixtures::path()], 'verso.json', '0.1.0'))->discover();

    expect($plugins['broken-json']->isValid())->toBeFalse()
        ->and($plugins['broken-json']->errors[0])->toContain('not valid JSON')
        ->and($plugins['event-calendar']->isValid())->toBeTrue();
});

it('marks plugins that need another Verso version', function () {
    $plugins = (new PluginDiscovery([PluginFixtures::path()], 'verso.json', '0.1.0'))->discover();

    expect($plugins['future-gallery']->compatible)->toBeFalse()
        ->and($plugins['event-calendar']->compatible)->toBeTrue();
});

it('ignores missing folders and folders without a manifest', function () {
    $empty = storage_path('framework/testing/plugins-empty/notes');
    @mkdir($empty, 0755, true);

    $discovery = new PluginDiscovery([dirname($empty), '/srv/does-not-exist'], 'verso.json', '0.1.0');

    expect($discovery->discover())->toBe([]);
});

it('lets the first path win when two plugins share a slug', function () {
    $copy = storage_path('framework/testing/plugins-copy/event-calendar');
    @mkdir($copy, 0755, true);
    file_put_contents($copy.'/verso.json', json_encode([
        'name' => 'Event Calendar Copy', 'slug' => 'event-calendar', 'version' => '9.0.0', 'provider' => 'Copy\\Provider',
    ]));

    $plugins = (new PluginDiscovery([PluginFixtures::path(), dirname($copy)], 'verso.json', '0.1.0'))->discover();

    expect($plugins['event-calendar']->manifest->name)->toBe('Event Calendar');
});

it('uses the manifest file name from the constructor', function () {
    $plugins = (new PluginDiscovery([PluginFixtures::path()], 'plugin.json', '0.1.0'))->discover();

    expect($plugins)->toBe([]);
});

it('looks in the plugins folder by default', function () {
    expect(config('verso.plugins.paths'))->toBe([base_path('plugins')])
        ->and(config('verso.plugins.manifest'))->toBe('verso.json');
});
