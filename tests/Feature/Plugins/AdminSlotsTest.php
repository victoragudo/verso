<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\AdminEntryRowFilter;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Plugins\Slots\SlotItem;
use Illuminate\Http\Request;
use Tests\Support\SharedSlots;

beforeEach(function () {
    $this->slots = app(AdminSlotRegistry::class);
});

it('sorts the items of a slot by order, then by key', function () {
    $this->slots->add(new SlotItem(AdminSlot::DashboardWidgets, 'weather', 'weather.widget', order: 30));
    $this->slots->add(new SlotItem(AdminSlot::DashboardWidgets, 'bookings', 'bookings.widget', order: 10));
    $this->slots->add(new SlotItem(AdminSlot::DashboardWidgets, 'arrivals', 'arrivals.widget', order: 30));

    expect(array_map(fn (SlotItem $item): string => $item->key, $this->slots->all(AdminSlot::DashboardWidgets)))
        ->toBe(['bookings', 'arrivals', 'weather']);
});

it('replaces an item with the same key and removes items', function () {
    $this->slots->add(new SlotItem(AdminSlot::ProfileSections, 'newsletter', 'newsletter.profile', label: 'Newsletter'));
    $this->slots->add(new SlotItem(AdminSlot::ProfileSections, 'newsletter', 'newsletter.profile-v2', label: 'Email updates'));

    $newsletter = array_values(array_filter($this->slots->all(AdminSlot::ProfileSections), fn (SlotItem $item): bool => $item->key === 'newsletter'));

    expect($newsletter)->toHaveCount(1)
        ->and($newsletter[0]->component)->toBe('newsletter.profile-v2');

    $this->slots->remove(AdminSlot::ProfileSections, 'newsletter');

    expect($this->slots->has(AdminSlot::ProfileSections, 'newsletter'))->toBeFalse();
});

it('shares items only on the screen of their slot', function () {
    $this->slots->add(new SlotItem(AdminSlot::DashboardWidgets, 'bookings', 'bookings.widget', label: 'Bookings', props: ['limit' => 5]));
    $admin = User::factory()->admin()->create();

    $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $media = $this->actingAs($admin)->get(route('admin.media.index'))->assertOk();

    expect(SharedSlots::items($dashboard, AdminSlot::DashboardWidgets))->toBe([
        ['key' => 'bookings', 'component' => 'bookings.widget', 'label' => 'Bookings', 'order' => 100, 'props' => ['limit' => 5]],
    ])->and(SharedSlots::items($media, AdminSlot::DashboardWidgets))->toBe([]);
});

it('hides items from users without the capability', function () {
    $this->slots->add(new SlotItem(AdminSlot::EntriesColumns, 'reading-time', 'reading-time.column', label: 'Reading time', capability: Capability::EditAnyEntry));

    $editor = $this->actingAs(User::factory()->editor()->create())->get(route('admin.entries.index', 'post'));
    $author = $this->actingAs(User::factory()->author()->create())->get(route('admin.entries.index', 'post'));

    expect(SharedSlots::item($editor, AdminSlot::EntriesColumns, 'reading-time'))->not->toBeNull()
        ->and(SharedSlots::item($author, AdminSlot::EntriesColumns, 'reading-time'))->toBeNull();
});

it('builds props for each request and hides items whose props are null', function () {
    $this->slots->add(new SlotItem(AdminSlot::EntryToolbar, 'preview-link', 'preview.button', props: fn (Request $request): ?array => $request->route('entry') instanceof Entry
        ? ['entryTitle' => $request->route('entry')->title]
        : null));
    $editor = User::factory()->editor()->create();
    $entry = Entry::factory()->create(['title' => 'Whale Watching in the Azores']);

    $create = $this->actingAs($editor)->get(route('admin.entries.create', 'post'));
    $edit = $this->actingAs($editor)->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]));

    expect(SharedSlots::item($create, AdminSlot::EntryToolbar, 'preview-link'))->toBeNull()
        ->and(SharedSlots::item($edit, AdminSlot::EntryToolbar, 'preview-link')['props'])->toBe(['entryTitle' => 'Whale Watching in the Azores']);
});

it('shares the core SEO and comments items on the entry editor', function () {
    $response = $this->actingAs(User::factory()->editor()->create())->get(route('admin.entries.create', 'page'));

    expect(SharedSlots::item($response, AdminSlot::EntrySidebar, 'seo'))->toMatchArray(['component' => 'site.seo', 'order' => 50])
        ->and(SharedSlots::item($response, AdminSlot::EntryPublish, 'comments'))->toMatchArray(['component' => 'comments.switch']);
});

it('shares nothing with guests', function () {
    $this->slots->add(new SlotItem(AdminSlot::DashboardWidgets, 'bookings', 'bookings.widget'));

    expect($this->slots->forRequest(Request::create('/admin')))->toBe([]);
});

it('lets plugins add data to entries table rows', function () {
    $editor = User::factory()->editor()->create();
    Entry::factory()->create(['title' => 'Hiking the Levadas of Madeira', 'blocks' => Entry::factory()->paragraphs(['One two three four five six.'])]);

    Filters::add(AdminEntryRowFilter::class, fn (array $row, Entry $entry, User $user): array => [...$row, 'words' => str_word_count($entry->title)]);

    $rows = $this->actingAs($editor)->get(route('admin.entries.index', 'post'))->inertiaProps('entries.data');

    expect($rows[0]['title'])->toBe('Hiking the Levadas of Madeira')
        ->and($rows[0]['words'])->toBe(5);
});
