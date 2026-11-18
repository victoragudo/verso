<?php

use App\Verso\Plugins\Actions\DisablePlugin;
use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\Events\PluginDisabled;
use App\Verso\Plugins\Events\PluginEnabled;
use Illuminate\Support\Facades\Event;
use Tests\Support\PluginFixtures;

beforeEach(function () {
    PluginFixtures::use();
    Event::fake([PluginEnabled::class, PluginDisabled::class]);
});

it('enables a plugin and sends an event once', function () {
    app(EnablePlugin::class)->handle('event-calendar');
    app(EnablePlugin::class)->handle('event-calendar');

    Event::assertDispatchedTimes(PluginEnabled::class, 1);
    Event::assertDispatched(PluginEnabled::class, fn (PluginEnabled $event) => $event->plugin->slug === 'event-calendar' && $event->plugin->enabled);
});

it('disables a plugin and sends an event only when it was enabled', function () {
    app(DisablePlugin::class)->handle('event-calendar');
    Event::assertNotDispatched(PluginDisabled::class);

    app(EnablePlugin::class)->handle('event-calendar');
    app(DisablePlugin::class)->handle('event-calendar');

    Event::assertDispatched(PluginDisabled::class, fn (PluginDisabled $event) => $event->plugin->slug === 'event-calendar' && ! $event->plugin->enabled);
});
