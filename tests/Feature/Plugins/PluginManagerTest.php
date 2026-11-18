<?php

use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Settings\Settings;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\Exceptions\PluginCannotBeEnabled;
use App\Verso\Plugins\Exceptions\PluginNotFound;
use App\Verso\Plugins\FilterRegistry;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\PluginManager;
use Illuminate\Support\Facades\Log;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    $this->plugins = PluginFixtures::use();
    $this->settings = app(Settings::class);
});

it('lists every plugin with its enabled flag', function () {
    $this->settings->set('plugins.enabled', ['event-calendar']);

    $plugins = collect($this->plugins->all())->keyBy('slug');

    expect($plugins->keys()->all())->toBe(['broken-json', 'event-calendar', 'future-gallery', 'missing-provider', 'not-a-provider'])
        ->and($plugins['event-calendar']->enabled)->toBeTrue()
        ->and($plugins['future-gallery']->enabled)->toBeFalse();
});

it('finds plugins by slug', function () {
    expect($this->plugins->find('event-calendar')?->manifest->name)->toBe('Event Calendar')
        ->and($this->plugins->find('photo-booth'))->toBeNull()
        ->and(fn () => $this->plugins->get('photo-booth'))->toThrow(PluginNotFound::class);
});

it('enables a plugin and stores it in the plugins.enabled setting', function () {
    $plugin = $this->plugins->enable('event-calendar');

    expect($plugin->enabled)->toBeTrue()
        ->and($this->settings->get('plugins.enabled'))->toBe(['event-calendar'])
        ->and($this->plugins->isEnabled('event-calendar'))->toBeTrue();
});

it('does not store a plugin twice', function () {
    $this->plugins->enable('event-calendar');
    $this->plugins->enable('event-calendar');

    expect($this->settings->get('plugins.enabled'))->toBe(['event-calendar']);
});

it('disables a plugin', function () {
    $this->settings->set('plugins.enabled', ['event-calendar', 'newsletter']);

    $plugin = $this->plugins->disable('event-calendar');

    expect($plugin->enabled)->toBeFalse()
        ->and($this->settings->get('plugins.enabled'))->toBe(['newsletter']);
});

it('refuses to enable broken, incompatible or unloadable plugins', function (string $slug, string $message) {
    expect(fn () => $this->plugins->enable($slug))->toThrow(PluginCannotBeEnabled::class, $message);

    expect($this->settings->get('plugins.enabled'))->toBe([]);
})->with([
    'broken manifest' => ['broken-json', 'not valid JSON'],
    'wrong Verso version' => ['future-gallery', 'It needs Verso ^9.0'],
    'missing provider class' => ['missing-provider', 'was not found'],
    'provider is not a plugin provider' => ['not-a-provider', 'must extend'],
]);

it('ignores junk in the plugins.enabled setting', function () {
    $this->settings->set('plugins.enabled', ['event-calendar', '', 42, 'event-calendar', null]);

    expect($this->plugins->enabledSlugs())->toBe(['event-calendar']);

    $this->settings->set('plugins.enabled', 'event-calendar');

    expect($this->plugins->enabledSlugs())->toBe([]);
});

it('registers the providers of enabled plugins and runs their boot code', function () {
    $this->settings->set('plugins.enabled', ['event-calendar', 'future-gallery', 'removed-plugin']);

    expect($this->plugins->registerEnabled())->toBe(['event-calendar'])
        ->and(array_keys($this->plugins->bootedProviders()))->toBe(['event-calendar'])
        ->and(app(ContentTypeRegistry::class)->has('event'))->toBeTrue()
        ->and(app(PluginBlockRegistry::class)->has('venue'))->toBeTrue()
        ->and(app(FilterRegistry::class)->has(ApiEntryFilter::class))->toBeTrue()
        ->and(AdminNav::has('events.calendar'))->toBeTrue();
});

it('registers a provider only once', function () {
    $plugin = $this->plugins->get('event-calendar');

    expect($this->plugins->register($plugin))->toBe($this->plugins->register($plugin));
});

it('skips and logs plugins that fail to boot', function () {
    Log::spy();
    $this->settings->set('plugins.enabled', ['missing-provider', 'event-calendar']);

    expect($this->plugins->registerEnabled())->toBe(['event-calendar']);

    Log::shouldHaveReceived('warning')->once();
});

it('reads the plugin folders again after refresh', function () {
    $path = storage_path('framework/testing/plugins-refresh');
    @mkdir($path, 0755, true);
    array_map('unlink', glob($path.'/*/verso.json') ?: []);

    $plugins = PluginFixtures::use($path);
    expect($plugins->all())->toBe([]);

    @mkdir($path.'/table-of-contents', 0755, true);
    file_put_contents($path.'/table-of-contents/verso.json', json_encode([
        'name' => 'Table of Contents', 'slug' => 'table-of-contents', 'version' => '1.0.0', 'provider' => 'Verso\\Plugins\\Toc\\TocServiceProvider',
    ]));

    expect($plugins->all())->toBe([]);

    $plugins->refresh();

    expect($plugins->all())->toHaveCount(1);
});

it('is bound as a singleton', function () {
    expect(app(PluginManager::class))->toBe(app(PluginManager::class));
});
