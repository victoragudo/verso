<?php

use App\Models\User;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Carbon;

it('starts as a draft', function () {
    expect((new Entry)->status)->toBe(EntryStatus::Draft)
        ->and(Entry::factory()->create()->isDraft())->toBeTrue();
});

it('casts blocks and meta to arrays', function () {
    $entry = Entry::factory()->create(['meta' => ['seo_title' => 'Porto in two days']]);

    expect($entry->fresh()->blocks)->toBeArray()->toHaveCount(3)
        ->and($entry->fresh()->blocks[0]['type'])->toBe('paragraph')
        ->and($entry->fresh()->meta)->toBe(['seo_title' => 'Porto in two days']);
});

it('publishes now', function () {
    Carbon::setTestNow('2026-11-16 10:00:00');
    $entry = Entry::factory()->create();

    $entry->publish();

    expect($entry->fresh()->status)->toBe(EntryStatus::Published)
        ->and($entry->published_at->toDateTimeString())->toBe('2026-11-16 10:00:00')
        ->and($entry->isPublished())->toBeTrue();
});

it('keeps an older publish date when publishing again', function () {
    $entry = Entry::factory()->create(['published_at' => '2024-03-01 09:30:00']);

    $entry->publish();

    expect($entry->published_at->toDateTimeString())->toBe('2024-03-01 09:30:00');
});

it('can publish with a past date', function () {
    $entry = Entry::factory()->create();

    $entry->publish(Carbon::parse('2025-01-15 08:00:00'));

    expect($entry->published_at->toDateTimeString())->toBe('2025-01-15 08:00:00');
});

it('refuses to publish in the future', function () {
    Entry::factory()->create()->publish(now()->addDay());
})->throws(InvalidArgumentException::class);

it('schedules for a future date', function () {
    $entry = Entry::factory()->create();
    $at = now()->addDays(3)->startOfMinute();

    $entry->schedule($at);

    expect($entry->fresh()->status)->toBe(EntryStatus::Scheduled)
        ->and($entry->published_at->equalTo($at))->toBeTrue()
        ->and($entry->isScheduled())->toBeTrue()
        ->and($entry->isPublished())->toBeFalse();
});

it('refuses to schedule in the past', function () {
    Entry::factory()->create()->schedule(now()->subMinute());
})->throws(InvalidArgumentException::class);

it('unpublishes to a draft', function () {
    $entry = Entry::factory()->published()->create();

    $entry->unpublish();

    expect($entry->fresh()->status)->toBe(EntryStatus::Draft)
        ->and($entry->isPublished())->toBeFalse();
});

it('moves to the trash and comes back as a draft', function () {
    $entry = Entry::factory()->published()->create();

    $entry->moveToTrash();

    expect($entry->trashed())->toBeTrue()
        ->and(Entry::withTrashed()->find($entry->id)->status)->toBe(EntryStatus::Trash)
        ->and(Entry::find($entry->id))->toBeNull();

    $entry->restoreFromTrash();

    expect($entry->fresh()->status)->toBe(EntryStatus::Draft)
        ->and($entry->trashed())->toBeFalse();
});

it('cannot publish a trashed entry', function () {
    $entry = Entry::factory()->create();
    $entry->moveToTrash();

    $entry->publish();
})->throws(LogicException::class);

it('only lists live entries in the published scope', function () {
    Entry::factory()->published()->create(['title' => 'Live post']);
    Entry::factory()->create(['title' => 'Draft post']);
    Entry::factory()->scheduled()->create(['title' => 'Scheduled post']);
    Entry::factory()->create(['title' => 'Odd post', 'status' => EntryStatus::Published, 'published_at' => now()->addHour()]);

    expect(Entry::published()->pluck('title')->all())->toBe(['Live post']);
});

it('finds scheduled entries that are due', function () {
    $due = Entry::factory()->create(['status' => EntryStatus::Scheduled, 'published_at' => now()->subMinute()]);
    Entry::factory()->scheduled()->create();

    expect(Entry::dueForPublishing()->pluck('id')->all())->toBe([$due->id]);
});

it('filters by type', function () {
    Entry::factory()->count(2)->create();
    Entry::factory()->page()->create();

    expect(Entry::ofType('page')->count())->toBe(1);
});

it('knows its owner', function () {
    $author = User::factory()->author()->create();
    $entry = Entry::factory()->by($author)->create();

    expect($entry->isOwnedBy($author))->toBeTrue()
        ->and($entry->isOwnedBy(User::factory()->create()))->toBeFalse()
        ->and($entry->author->is($author))->toBeTrue();
});

it('builds post URLs under the blog', function () {
    $entry = Entry::factory()->create(['slug' => 'summer-in-porto']);

    expect($entry->path())->toBe('/blog/summer-in-porto')
        ->and($entry->url())->toBe(url('/blog/summer-in-porto'))
        ->and($entry->contentType()->name)->toBe('post');
});

it('builds nested page paths', function () {
    $about = Entry::factory()->page()->create(['slug' => 'about']);
    $team = Entry::factory()->page()->create(['slug' => 'team', 'parent_id' => $about->id]);
    $kitchen = Entry::factory()->page()->create(['slug' => 'kitchen', 'parent_id' => $team->id]);

    expect($kitchen->path())->toBe('/about/team/kitchen')
        ->and($kitchen->slugPath())->toBe('about/team/kitchen')
        ->and(array_map(fn ($page) => $page->slug, $kitchen->ancestors()))->toBe(['about', 'team'])
        ->and($about->path())->toBe('/about');
});

it('uses the root path for the front page', function () {
    $home = Entry::factory()->page()->create(['slug' => 'welcome']);
    app(Settings::class)->set('reading.front_page', $home->id);

    expect($home->isFrontPage())->toBeTrue()
        ->and($home->path())->toBe('/');
});

it('stops when parents form a loop', function () {
    $first = Entry::factory()->page()->create(['slug' => 'first']);
    $second = Entry::factory()->page()->create(['slug' => 'second', 'parent_id' => $first->id]);
    $first->update(['parent_id' => $second->id]);

    expect($second->fresh()->slugPath())->toBe('first/second');
});

it('checks which parents are allowed', function () {
    $about = Entry::factory()->page()->create();
    $team = Entry::factory()->page()->create(['parent_id' => $about->id]);
    $post = Entry::factory()->create();

    expect($team->canHaveParent(null))->toBeTrue()
        ->and($team->canHaveParent($about))->toBeTrue()
        ->and($about->canHaveParent($team))->toBeFalse()
        ->and($about->canHaveParent($about))->toBeFalse()
        ->and($team->canHaveParent($post))->toBeFalse()
        ->and($post->canHaveParent(Entry::factory()->create()))->toBeFalse();
});

it('returns terms of one taxonomy', function () {
    $entry = Entry::factory()->create();
    $travel = Term::factory()->create(['name' => 'Travel']);
    $porto = Term::factory()->tag()->create(['name' => 'Porto']);
    $entry->terms()->attach([$travel->id, $porto->id]);

    expect($entry->termsIn('tag')->pluck('name')->all())->toBe(['Porto'])
        ->and($entry->termsIn('category')->pluck('name')->all())->toBe(['Travel']);
});

it('takes a snapshot of its content', function () {
    $entry = Entry::factory()->create(['title' => 'Bread notes', 'excerpt' => 'Flour and water', 'meta' => ['mood' => 'calm']]);

    expect($entry->snapshot())->toMatchArray([
        'title' => 'Bread notes',
        'excerpt' => 'Flour and water',
        'meta' => ['mood' => 'calm'],
    ])->and($entry->snapshot()['blocks'])->toHaveCount(3);
});

it('prunes old revisions', function () {
    $entry = Entry::factory()->create();
    $revisions = Revision::factory()->count(5)->for($entry)->create();

    $deleted = $entry->pruneRevisions(2);

    expect($deleted)->toBe(3)
        ->and($entry->revisions()->pluck('id')->all())->toBe([$revisions[4]->id, $revisions[3]->id]);
});

it('lists children by menu order', function () {
    $about = Entry::factory()->page()->create();
    Entry::factory()->page()->create(['title' => 'Contact', 'parent_id' => $about->id, 'menu_order' => 2]);
    Entry::factory()->page()->create(['title' => 'History', 'parent_id' => $about->id, 'menu_order' => 1]);

    $about->load('children.parent');

    expect($about->children->pluck('title')->all())->toBe(['History', 'Contact'])
        ->and($about->children->first()->parent->is($about))->toBeTrue();
});
