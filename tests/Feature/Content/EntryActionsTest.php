<?php

use App\Models\User;
use App\Verso\Content\Actions\CreateEntry;
use App\Verso\Content\Actions\PublishEntry;
use App\Verso\Content\Actions\RestoreEntry;
use App\Verso\Content\Actions\RestoreRevision;
use App\Verso\Content\Actions\ScheduleEntry;
use App\Verso\Content\Actions\TrashEntry;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Events\EntryDeleted;
use App\Verso\Content\Events\EntryPublished;
use App\Verso\Content\Events\EntrySaved;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Models\Redirect;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    $this->author = User::factory()->author()->create(['name' => 'Inés Navarro']);
});

describe('CreateEntry', function () {
    it('creates a draft with a slug from the title', function () {
        Event::fake();

        $entry = app(CreateEntry::class)->handle($this->author, 'post', [
            'title' => 'A Slow Weekend in Porto',
            'excerpt' => 'Two days of tiles, wine and river walks.',
        ]);

        expect($entry->exists)->toBeTrue()
            ->and($entry->slug)->toBe('a-slow-weekend-in-porto')
            ->and($entry->status)->toBe(EntryStatus::Draft)
            ->and($entry->author_id)->toBe($this->author->id)
            ->and($entry->blocks)->toBe([])
            ->and($entry->published_at)->toBeNull();

        Event::assertDispatched(EntrySaved::class, fn ($event) => $event->entry->is($entry));
        Event::assertNotDispatched(EntryPublished::class);
    });

    it('publishes right away when asked', function () {
        Event::fake();

        $entry = app(CreateEntry::class)->handle($this->author, 'post', [
            'title' => 'Opening day at the bakery',
            'status' => 'published',
        ]);

        expect($entry->isPublished())->toBeTrue()
            ->and($entry->published_at->toDateTimeString())->toBe('2026-11-16 12:00:00');

        Event::assertDispatched(EntryPublished::class);
    });

    it('schedules when the status is published but the date is in the future', function () {
        $entry = app(CreateEntry::class)->handle($this->author, 'post', [
            'title' => 'Winter menu',
            'status' => EntryStatus::Published,
            'published_at' => '2026-12-01 09:00:00',
        ]);

        expect($entry->status)->toBe(EntryStatus::Scheduled)
            ->and($entry->published_at->toDateTimeString())->toBe('2026-12-01 09:00:00');
    });

    it('keeps a custom slug unique', function () {
        Entry::factory()->create(['slug' => 'garden-diary']);

        $entry = app(CreateEntry::class)->handle($this->author, 'post', ['title' => 'Garden diary, week one', 'slug' => 'Garden Diary']);

        expect($entry->slug)->toBe('garden-diary-2');
    });

    it('sets the parent of a page', function () {
        $about = Entry::factory()->page()->create(['slug' => 'about']);

        $team = app(CreateEntry::class)->handle($this->author, 'page', ['title' => 'Our Team', 'parent_id' => $about->id, 'menu_order' => 3]);

        expect($team->parent_id)->toBe($about->id)
            ->and($team->menu_order)->toBe(3)
            ->and($team->path())->toBe('/about/our-team');
    });

    it('refuses a parent for posts', function () {
        $other = Entry::factory()->create();

        app(CreateEntry::class)->handle($this->author, 'post', ['title' => 'Lonely post', 'parent_id' => $other->id]);
    })->throws(InvalidArgumentException::class);

    it('refuses unknown content types', function () {
        app(CreateEntry::class)->handle($this->author, 'podcast', ['title' => 'Episode one']);
    })->throws(InvalidArgumentException::class);

    it('attaches terms of allowed taxonomies only', function () {
        $travel = Term::factory()->create(['name' => 'Travel']);
        $porto = Term::factory()->tag()->create(['name' => 'Porto']);
        $wrongTaxonomy = Term::factory()->tag()->create(['name' => 'Lisbon']);

        $entry = app(CreateEntry::class)->handle($this->author, 'post', [
            'title' => 'Porto by train',
            'terms' => ['category' => [$travel->id, $wrongTaxonomy->id], 'tag' => [$porto->id]],
        ]);

        expect($entry->terms()->pluck('name')->sort()->values()->all())->toBe(['Porto', 'Travel']);
    });

    it('stores blocks and meta', function () {
        $blocks = [['id' => 'b_k2j3h4', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Hello from Porto.</p>', 'children' => []]];

        $entry = app(CreateEntry::class)->handle($this->author, 'post', ['title' => 'Postcard', 'blocks' => $blocks, 'meta' => ['seo_title' => 'Postcard from Porto']]);

        expect($entry->fresh()->blocks)->toBe($blocks)
            ->and($entry->fresh()->meta)->toBe(['seo_title' => 'Postcard from Porto']);
    });
});

describe('UpdateEntry', function () {
    it('saves the old content as a revision', function () {
        $entry = Entry::factory()->by($this->author)->create(['title' => 'First draft of the bread guide']);

        app(UpdateEntry::class)->handle($entry, ['title' => 'The bread guide'], $this->author);

        $revision = $entry->revisions()->first();

        expect($entry->fresh()->title)->toBe('The bread guide')
            ->and($revision->title)->toBe('First draft of the bread guide')
            ->and($revision->user_id)->toBe($this->author->id);
    });

    it('does not make a revision when only the status changes', function () {
        $entry = Entry::factory()->create();

        app(UpdateEntry::class)->handle($entry, ['status' => 'published']);

        expect($entry->revisions()->count())->toBe(0)
            ->and($entry->isPublished())->toBeTrue();
    });

    it('keeps only the newest revisions', function () {
        app(Settings::class)->set('content.revisions_limit', 3);
        $entry = Entry::factory()->create();

        foreach (['One', 'Two', 'Three', 'Four', 'Five'] as $title) {
            app(UpdateEntry::class)->handle($entry, ['title' => "Draft {$title}"]);
        }

        expect($entry->revisions()->pluck('title')->all())->toBe(['Draft Four', 'Draft Three', 'Draft Two']);
    });

    it('changes the slug and redirects the old URL of a live post', function () {
        $entry = Entry::factory()->published()->create(['slug' => 'porto-weekend']);

        app(UpdateEntry::class)->handle($entry, ['slug' => 'a-weekend-in-porto']);

        expect($entry->slug)->toBe('a-weekend-in-porto')
            ->and(Redirect::findForPath('/blog/porto-weekend')->to_path)->toBe('/blog/a-weekend-in-porto');
    });

    it('does not redirect drafts', function () {
        $entry = Entry::factory()->create(['slug' => 'secret-plans']);

        app(UpdateEntry::class)->handle($entry, ['slug' => 'public-plans']);

        expect(Redirect::count())->toBe(0);
    });

    it('rebuilds the slug from the title when the slug is cleared', function () {
        $entry = Entry::factory()->create(['title' => 'Tomato season', 'slug' => 'old-slug']);

        app(UpdateEntry::class)->handle($entry, ['slug' => '']);

        expect($entry->slug)->toBe('tomato-season');
    });

    it('moves a page to a new parent and keeps slugs unique there', function () {
        $about = Entry::factory()->page()->published()->create(['slug' => 'about']);
        Entry::factory()->page()->create(['slug' => 'team', 'parent_id' => $about->id]);
        $team = Entry::factory()->page()->published()->create(['slug' => 'team']);

        app(UpdateEntry::class)->handle($team, ['parent_id' => $about->id]);

        expect($team->slug)->toBe('team-2')
            ->and($team->path())->toBe('/about/team-2')
            ->and(Redirect::findForPath('/team')->to_path)->toBe('/about/team-2');
    });

    it('refuses a parent that would make a loop', function () {
        $about = Entry::factory()->page()->create();
        $team = Entry::factory()->page()->create(['parent_id' => $about->id]);

        app(UpdateEntry::class)->handle($about, ['parent_id' => $team->id]);
    })->throws(InvalidArgumentException::class);

    it('syncs terms per taxonomy', function () {
        $entry = Entry::factory()->create();
        $travel = Term::factory()->create();
        $food = Term::factory()->create();
        $porto = Term::factory()->tag()->create();
        $entry->terms()->attach([$travel->id, $porto->id]);

        app(UpdateEntry::class)->handle($entry, ['terms' => ['category' => [$food->id]]]);

        expect($entry->terms()->pluck('terms.id')->sort()->values()->all())->toBe([$food->id, $porto->id]);
    });

    it('fires saved and published events', function () {
        Event::fake();
        $entry = Entry::factory()->create();

        app(UpdateEntry::class)->handle($entry, ['status' => 'published']);

        Event::assertDispatched(EntrySaved::class);
        Event::assertDispatched(EntryPublished::class);
    });

    it('does not fire published again for a live entry', function () {
        $entry = Entry::factory()->published()->create();
        Event::fake();

        app(UpdateEntry::class)->handle($entry, ['title' => 'Better title', 'status' => 'published']);

        Event::assertNotDispatched(EntryPublished::class);
    });

    it('turns a live entry back into a draft', function () {
        $entry = Entry::factory()->published()->create();

        app(UpdateEntry::class)->handle($entry, ['status' => 'draft', 'published_at' => null]);

        expect($entry->fresh()->status)->toBe(EntryStatus::Draft);
    });
});

describe('PublishEntry and ScheduleEntry', function () {
    it('publishes and fires events', function () {
        Event::fake();
        $entry = Entry::factory()->create();

        app(PublishEntry::class)->handle($entry);

        expect($entry->isPublished())->toBeTrue();
        Event::assertDispatched(EntryPublished::class);
        Event::assertDispatched(EntrySaved::class);
    });

    it('schedules and fires the saved event', function () {
        Event::fake();
        $entry = Entry::factory()->create();

        app(ScheduleEntry::class)->handle($entry, now()->addWeek());

        expect($entry->status)->toBe(EntryStatus::Scheduled);
        Event::assertDispatched(EntrySaved::class);
        Event::assertNotDispatched(EntryPublished::class);
    });
});

describe('TrashEntry and RestoreEntry', function () {
    it('trashes and fires the deleted event', function () {
        Event::fake();
        $entry = Entry::factory()->published()->create();

        app(TrashEntry::class)->handle($entry);

        expect($entry->trashed())->toBeTrue();
        Event::assertDispatched(EntryDeleted::class, fn ($event) => $event->permanently === false);
    });

    it('does nothing for an entry already in the trash', function () {
        $entry = Entry::factory()->trashed()->create();
        Event::fake();

        app(TrashEntry::class)->handle($entry);

        Event::assertNotDispatched(EntryDeleted::class);
    });

    it('restores as a draft', function () {
        Event::fake();
        $entry = Entry::factory()->trashed()->create();

        app(RestoreEntry::class)->handle($entry);

        expect($entry->trashed())->toBeFalse()
            ->and($entry->status)->toBe(EntryStatus::Draft);
        Event::assertDispatched(EntrySaved::class);
    });

    it('ignores entries that are not trashed', function () {
        $entry = Entry::factory()->published()->create();

        app(RestoreEntry::class)->handle($entry);

        expect($entry->isPublished())->toBeTrue();
    });
});

describe('RestoreRevision', function () {
    it('brings back old content and keeps the current one as a revision', function () {
        $entry = Entry::factory()->create(['title' => 'Current title']);
        $old = Revision::factory()->for($entry)->create(['title' => 'Old title', 'excerpt' => 'Old excerpt']);

        app(RestoreRevision::class)->handle($entry, $old, $this->author);

        expect($entry->fresh()->title)->toBe('Old title')
            ->and($entry->fresh()->excerpt)->toBe('Old excerpt')
            ->and($entry->revisions()->first()->title)->toBe('Current title');
    });

    it('refuses a revision of another entry', function () {
        $entry = Entry::factory()->create();
        $foreign = Revision::factory()->create();

        app(RestoreRevision::class)->handle($entry, $foreign);
    })->throws(InvalidArgumentException::class);
});
