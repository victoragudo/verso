<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Plugins\PluginManager;

beforeEach(function () {
    config([
        'verso.plugins.paths' => [],
        'verso.plugins.composer_installed' => base_path('tests/Fixtures/composer/vendor/composer/installed.json'),
    ]);
    app()->forgetInstance(PluginDiscovery::class);
    app()->forgetInstance(PluginManager::class);
    $this->plugins = app(PluginManager::class);
});

it('lists Composer plugins in the manager', function () {
    $slugs = array_map(fn ($plugin) => $plugin->slug, $this->plugins->all());

    expect($slugs)->toBe(['broken-banner', 'newsletter-signup', 'podcast-player']);
});

it('enables and boots a plugin installed as a Composer package', function () {
    $plugin = $this->plugins->enable('podcast-player');

    expect($plugin->enabled)->toBeTrue()
        ->and(app(Settings::class)->get('plugins.enabled'))->toBe(['podcast-player']);

    $this->plugins->registerEnabled();

    $data = app(FilterRegistry::class)->apply(ApiEntryFilter::class, ['title' => 'Episode 12: Lighthouses of Galicia']);

    expect($data['podcast'])->toBe('harbor');
});

it('shows Composer plugins on the admin plugins page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get(route('admin.plugins.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('plugins.2.slug', 'podcast-player')->where('plugins.2.name', 'Podcast Player'));
});
