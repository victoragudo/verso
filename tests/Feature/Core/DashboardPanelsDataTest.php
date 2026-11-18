<?php

use App\Models\User;
use App\Verso\Access\Permissions\EntryPermissions;
use App\Verso\Comments\Admin\PendingCommentsPanel;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Admin\RecentEntriesPanel;
use App\Verso\Content\ContentTypeRegistry;
use App\Verso\Content\Models\Entry;
use Inertia\Testing\AssertableInertia as Assert;

it('lists the latest edited entries and skips the trash', function () {
    $admin = User::factory()->admin()->create();
    $older = Entry::factory()->create(['title' => 'Notes from the harbor market', 'updated_at' => now()->subDays(2)]);
    $newer = Entry::factory()->published()->create(['title' => 'A week of sourdough experiments', 'updated_at' => now()->subHour()]);
    Entry::factory()->trashed()->create(['title' => 'Old draft about fences']);

    $items = (new RecentEntriesPanel(app(ContentTypeRegistry::class), 5, app(EntryPermissions::class)))($admin);

    expect(array_column($items, 'title'))->toBe(['A week of sourdough experiments', 'Notes from the harbor market'])
        ->and($items[0])->toMatchArray([
            'key' => 'entry-'.$newer->id,
            'href' => '/admin/entries/post/'.$newer->id.'/edit',
            'status' => 'published',
        ])
        ->and($items[1]['meta'])->toStartWith('Post, edited')
        ->and($items[1]['key'])->toBe('entry-'.$older->id);
});

it('shows authors only their own recent entries', function () {
    $author = User::factory()->author()->create();
    Entry::factory()->by($author)->create(['title' => 'My trip to Lisbon']);
    Entry::factory()->create(['title' => 'Someone else wrote this']);

    $items = (new RecentEntriesPanel(app(ContentTypeRegistry::class), 5, app(EntryPermissions::class)))($author);

    expect(array_column($items, 'title'))->toBe(['My trip to Lisbon']);
});

it('respects the item limit', function () {
    Entry::factory()->count(4)->create();

    $items = (new RecentEntriesPanel(app(ContentTypeRegistry::class), 2, app(EntryPermissions::class)))(User::factory()->admin()->create());

    expect($items)->toHaveCount(2);
});

it('lists pending comments with the author and entry', function () {
    $entry = Entry::factory()->published()->create(['title' => 'Planting tomatoes in May']);
    $pending = Comment::factory()->on($entry)->create(['author_name' => 'Marta Ruiz', 'body' => 'Do you water them every day?']);
    Comment::factory()->on($entry)->approved()->create();

    $items = (new PendingCommentsPanel(5))(User::factory()->admin()->create());

    expect($items)->toBe([[
        'key' => 'comment-'.$pending->id,
        'title' => 'Do you water them every day?',
        'meta' => 'Marta Ruiz on Planting tomatoes in May',
        'href' => '/admin/comments?status=pending',
        'status' => 'pending',
    ]]);
});

it('sends the dashboard panels to the page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/core/dashboard')
            ->where('panels.0.key', 'recent-entries')
            ->where('panels.1.key', 'pending-comments')
        );
});

it('hides the pending comments panel from authors', function () {
    $this->actingAs(User::factory()->author()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('panels', fn ($panels) => collect($panels)->pluck('key')->all() === ['recent-entries'])
        );
});
