<?php

use App\Models\User;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Plugins\Blocks\PluginBlockAdapter;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\Plugins\PluginsServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\Support\PluginFixtures;
use Verso\Plugins\EventCalendar\EventCalendarServiceProvider;

it('knows its plugin, slug and paths', function () {
    $manager = PluginFixtures::use();
    $provider = $manager->register($manager->get('event-calendar'));

    expect($provider)->toBeInstanceOf(EventCalendarServiceProvider::class)
        ->and($provider->slug())->toBe('event-calendar')
        ->and($provider->path())->toBe(PluginFixtures::path().'/event-calendar')
        ->and($provider->path('/src/VenueBlock.php'))->toBe(PluginFixtures::path().'/event-calendar/src/VenueBlock.php');
});

it('fails clearly when used without the plugin manager', function () {
    $provider = new class(app()) extends PluginServiceProvider {};

    expect(fn () => $provider->plugin())->toThrow(LogicException::class, 'not registered by the PluginManager');
});

it('registers enabled plugins when the plugins module boots', function () {
    config(['verso.plugins.paths' => [PluginFixtures::path()]]);
    app(Settings::class)->set('plugins.enabled', ['event-calendar']);

    (new PluginsServiceProvider(app()))->register();

    expect(app(ContentTypeRegistry::class)->has('event'))->toBeTrue()
        ->and(array_keys(app(PluginManager::class)->bootedProviders()))->toBe(['event-calendar']);
});

it('boots without plugins when the settings table is missing', function () {
    config(['verso.plugins.paths' => [PluginFixtures::path()]]);
    app(Settings::class)->set('plugins.enabled', ['event-calendar']);
    app(Settings::class)->forgetCache();
    Schema::drop('settings');

    (new PluginsServiceProvider(app()))->register();

    expect(app(PluginManager::class)->bootedProviders())->toBe([]);
});

it('adds plugin blocks to the core block registry', function () {
    config(['verso.plugins.paths' => [PluginFixtures::path()]]);
    app(Settings::class)->set('plugins.enabled', ['event-calendar']);

    (new PluginsServiceProvider(app()))->register();

    expect(app(BlockRegistry::class)->has('venue'))->toBeTrue()
        ->and(app(BlockRegistry::class)->get('venue'))->toBeInstanceOf(PluginBlockAdapter::class);
});

it('accepts and renders plugin blocks inside core blocks', function () {
    config(['verso.plugins.paths' => [PluginFixtures::path()]]);
    app(Settings::class)->set('plugins.enabled', ['event-calendar']);
    (new PluginsServiceProvider(app()))->register();

    $blocks = [
        ['id' => 'b_venue1', 'type' => 'venue', 'attrs' => ['name' => 'Coliseu dos Recreios'], 'content' => '', 'children' => []],
    ];

    $validator = Validator::make(['blocks' => $blocks], ['blocks' => [new ValidBlocks(User::factory()->author()->create())]]);

    expect($validator->passes())->toBeTrue()
        ->and(app(BlockRenderer::class)->render($blocks))->toBe('<p class="venue">Coliseu dos Recreios</p>');
});
