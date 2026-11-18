<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\PluginManager;
use App\Verso\Plugins\Slots\AdminSlot;
use Tests\Support\AiAssistantFixtures as Ai;
use Tests\Support\PluginFixtures;
use Tests\Support\SharedSlots;
use Verso\Plugins\AiAssistant\AiSettings;

it('shares the assistant with editors on the entry editor', function () {
    Ai::enable();
    $editor = User::factory()->editor()->create();
    $entry = Entry::factory()->create(['type' => 'post', 'title' => 'Night ferries of the Bosphorus']);

    $response = $this->actingAs($editor)
        ->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]))
        ->assertOk();

    $item = SharedSlots::item($response, AdminSlot::EntrySidebar, 'ai-assistant');

    expect($item['component'])->toBe('ai-assistant.panel')
        ->and($item['order'])->toBe(10)
        ->and($item['props']['ready'])->toBeTrue()
        ->and($item['props']['settingsUrl'])->toBeNull()
        ->and($item['props']['endpoints']['outline'])->toBe(route('admin.ai-assistant.actions.outline'))
        ->and($item['props']['endpoints']['altText'])->toBe(route('admin.ai-assistant.actions.alt-text'))
        ->and($item['props']['endpoints']['translate'])->toBe(route('admin.ai-assistant.actions.translate'))
        ->and($item['props']['locales'][0])->toBe(['code' => 'en', 'name' => 'English'])
        ->and($item['props']['locales'][1])->toBe(['code' => 'es', 'name' => 'Spanish']);
});

it('shares the assistant on the new entry page and links admins to the settings', function () {
    Ai::enable();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.entries.create', 'post'))
        ->assertOk();

    $props = SharedSlots::item($response, AdminSlot::EntrySidebar, 'ai-assistant')['props'];

    expect($props['ready'])->toBeTrue()
        ->and($props['settingsUrl'])->toBe(route('admin.ai-assistant.index'));
});

it('says the assistant is not ready without an API key', function () {
    Ai::enable(withKey: false);

    $response = $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.entries.create', 'post'));

    expect(SharedSlots::item($response, AdminSlot::EntrySidebar, 'ai-assistant')['props']['ready'])->toBeFalse();
});

it('hides the assistant from authors', function () {
    Ai::enable();

    $response = $this->actingAs(User::factory()->author()->create())
        ->get(route('admin.entries.create', 'post'))
        ->assertOk();

    expect(SharedSlots::item($response, AdminSlot::EntrySidebar, 'ai-assistant'))->toBeNull()
        ->and(SharedSlots::item($response, AdminSlot::EntrySidebar, 'seo'))->not->toBeNull();
});

it('does not share the assistant on other admin pages', function () {
    Ai::enable();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.entries.index', 'post'));

    expect(SharedSlots::items($response, AdminSlot::EntrySidebar))->toBe([]);
});

it('does not share anything while the plugin is off', function () {
    PluginFixtures::use(base_path('plugins'));

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.entries.create', 'post'))
        ->assertOk();

    expect(SharedSlots::item($response, AdminSlot::EntrySidebar, 'ai-assistant'))->toBeNull();
});

it('says the assistant is not ready once the plugin is turned off', function () {
    Ai::enable();
    app(PluginManager::class)->disable('ai-assistant');

    expect(app(AiSettings::class)->hasApiKey())->toBeTrue();

    $response = $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.entries.create', 'post'));

    expect(SharedSlots::item($response, AdminSlot::EntrySidebar, 'ai-assistant')['props']['ready'])->toBeFalse();
});
