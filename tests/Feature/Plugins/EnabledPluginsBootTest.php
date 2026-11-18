<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Plugins\PluginsServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Tests\Support\PluginFixtures;

it('registers the blocks of enabled plugins when the app boots', function () {
    PluginFixtures::use(base_path('plugins'));
    app(Settings::class)->set('plugins.enabled', ['reading-time']);
    app()->forgetInstance(PluginBlockRegistry::class);

    (new PluginsServiceProvider(app()))->registerEnabledPlugins();

    expect(app(PluginBlockRegistry::class)->has('reading-time'))->toBeTrue();
});

it('loads enabled plugins before the database provider has connected Eloquent', function () {
    PluginFixtures::use(base_path('plugins'));
    app(Settings::class)->set('plugins.enabled', ['reading-time']);
    app()->forgetInstance(PluginBlockRegistry::class);

    $resolver = Model::getConnectionResolver();
    Model::unsetConnectionResolver();

    try {
        (new PluginsServiceProvider(app()))->registerEnabledPlugins();
    } finally {
        Model::setConnectionResolver($resolver);
    }

    expect(app(PluginBlockRegistry::class)->has('reading-time'))->toBeTrue();
});
