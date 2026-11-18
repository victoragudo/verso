<?php

use App\Models\User;
use App\Verso\Content\Enums\EntryStatus;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Content\Models\Term;
use App\Verso\Plugins\Slots\AdminSlot;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SharedSlots;

beforeEach(function () {
    $this->editor = User::factory()->editor()->create(['name' => 'Marta Gil']);
});

describe('index', function () {
    it('redirects guests to login', function () {
        $this->get(route('admin.entries.index', 'post'))->assertRedirect(route('login'));
    });

    it('forbids subscribers', function () {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.entries.index', 'post'))
            ->assertForbidden();
    });

    it('returns 404 for unknown content types', function () {
        $this->actingAs($this->editor)->get('/admin/entries/podcast')->assertNotFound();
    });

    it('lists entries of the type with status counts', function () {
        Entry::factory()->published()->count(2)->create();
        Entry::factory()->create(['title' => 'Draft about olive harvest']);
        Entry::factory()->trashed()->create();
        Entry::factory()->page()->create();

        $this->actingAs($this->editor)
            ->get(route('admin.entries.index', 'post'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/entries/index')
                ->where('contentType.name', 'post')
                ->has('entries.data', 3)
                ->where('counts', ['all' => 3, 'draft' => 1, 'scheduled' => 0, 'published' => 2, 'trash' => 1])
                ->where('can.create', true)
            );
    });

    it('filters by status', function () {
        Entry::factory()->published()->create(['title' => 'Live post about Porto']);
        Entry::factory()->create(['title' => 'Draft about Lisbon']);

        $this->actingAs($this->editor)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'status' => 'draft']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.title', 'Draft about Lisbon')
                ->where('filters.status', 'draft')
            );
    });

    it('shows the trash', function () {
        Entry::factory()->trashed()->create(['title' => 'Old announcement']);
        Entry::factory()->create();

        $this->actingAs($this->editor)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'status' => 'trash']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('entries.data.0.title', 'Old announcement')
                ->where('entries.data.0.can.restore', true)
                ->where('entries.data.0.can.update', false)
            );
    });

    it('searches by title', function () {
        Entry::factory()->create(['title' => 'Cooking paella for twelve']);
        Entry::factory()->create(['title' => 'Cycling to work']);

        $this->actingAs($this->editor)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'search' => 'paella']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 1)
                ->where('filters.search', 'paella')
            );

        $this->actingAs($this->editor)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'search' => 'Cooking PAELLA']))
            ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
    });

    it('paginates with the configured page size', function () {
        config(['verso.admin.per_page' => 5]);
        Entry::factory()->count(7)->create();

        $this->actingAs($this->editor)
            ->get(route('admin.entries.index', ['contentType' => 'post', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries.data', 2)
                ->where('entries.last_page', 2)
            );
    });

    it('marks rows the author cannot edit', function () {
        $author = User::factory()->author()->create();
        Entry::factory()->by($author)->create(['title' => 'Mine']);
        Entry::factory()->create(['title' => 'Not mine', 'updated_at' => now()->subDay()]);

        $this->actingAs($author)
            ->get(route('admin.entries.index', 'post'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.data.0.title', 'Mine')
                ->where('entries.data.0.can.update', true)
                ->where('entries.data.1.can.update', false)
                ->where('entries.data.1.can.delete', false)
            );
    });
});

describe('create and store', function () {
    it('shows the create page with taxonomies and statuses', function () {
        Term::factory()->create(['name' => 'Travel']);

        $this->actingAs($this->editor)
            ->get(route('admin.entries.create', 'post'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/entries/edit')
                ->where('entry', null)
                ->has('taxonomies', 2)
                ->where('taxonomies.0.name', 'category')
                ->where('taxonomies.0.terms.0.name', 'Travel')
                ->has('statuses', 3)
                ->where('parents', [])
                ->where('can.publish', true)
            );
    });

    it('only offers drafts to contributors', function () {
        $this->actingAs(User::factory()->contributor()->create())
            ->get(route('admin.entries.create', 'post'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('statuses', [['value' => 'draft', 'label' => 'Draft']])
                ->where('can.publish', false)
            );
    });

    it('lists possible parents for pages', function () {
        Entry::factory()->page()->create(['title' => 'About us']);

        $this->actingAs($this->editor)
            ->get(route('admin.entries.create', 'page'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('parents.0.title', 'About us')
                ->has('taxonomies', 0)
            );
    });

    it('creates an entry and redirects to the editor', function () {
        $travel = Term::factory()->create();

        $response = $this->actingAs($this->editor)->post(route('admin.entries.store', 'post'), [
            'title' => 'Ten Lessons from a Year of Remote Work',
            'slug' => '',
            'status' => 'published',
            'excerpt' => 'What worked, what did not, and what I would change.',
            'blocks' => [['id' => 'b_r3m0t3', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>Lesson one: get a good chair.</p>', 'children' => []]],
            'terms' => ['category' => [$travel->id]],
        ]);

        $entry = Entry::firstWhere('slug', 'ten-lessons-from-a-year-of-remote-work');

        $response->assertRedirect(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]));
        expect($entry->isPublished())->toBeTrue()
            ->and($entry->author_id)->toBe($this->editor->id)
            ->and($entry->blocks[0]['content'])->toBe('<p>Lesson one: get a good chair.</p>')
            ->and($entry->terms->pluck('id')->all())->toBe([$travel->id]);
    });

    it('validates the title and the block shape', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), [
                'title' => '',
                'status' => 'draft',
                'blocks' => [['content' => 'missing type']],
            ])
            ->assertSessionHasErrors(['title', 'blocks']);
    });

    it('rejects unknown block types', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), [
                'title' => 'Notes from the Porto book fair',
                'status' => 'draft',
                'blocks' => [['id' => 'b_fair01', 'type' => 'marquee', 'attrs' => [], 'content' => 'Welcome', 'children' => []]],
            ])
            ->assertSessionHasErrors('blocks');

        expect(Entry::count())->toBe(0);
    });

    it('keeps nested column blocks as they were sent', function () {
        $blocks = [[
            'id' => 'b_cols01',
            'type' => 'columns',
            'attrs' => [],
            'content' => '',
            'children' => [
                ['id' => 'b_col001', 'type' => 'column', 'attrs' => [], 'content' => '', 'children' => [
                    ['id' => 'b_par001', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Bring a light jacket for the evenings.', 'children' => []],
                ]],
                ['id' => 'b_col002', 'type' => 'column', 'attrs' => [], 'content' => '', 'children' => [
                    ['id' => 'b_par002', 'type' => 'paragraph', 'attrs' => [], 'content' => 'Book the river cruise a day ahead.', 'children' => []],
                ]],
            ],
        ]];

        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), ['title' => 'Packing for Porto in October', 'status' => 'draft', 'blocks' => $blocks])
            ->assertRedirect();

        $saved = Entry::firstWhere('slug', 'packing-for-porto-in-october')->blocks;

        expect(data_get($saved, '0.children.0.children.0.content'))->toBe('Bring a light jacket for the evenings.')
            ->and(data_get($saved, '0.children.1.children.0.id'))->toBe('b_par002')
            ->and(data_get($saved, '0.children.1.type'))->toBe('column');
    });

    it('stops editors from saving raw html blocks', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), [
                'title' => 'Tracking pixel test page',
                'status' => 'draft',
                'blocks' => [['id' => 'b_raw001', 'type' => 'html', 'attrs' => ['html' => '<div>Hello</div>'], 'content' => '', 'children' => []]],
            ])
            ->assertSessionHasErrors('blocks');
    });

    it('stops contributors from publishing', function () {
        $this->actingAs(User::factory()->contributor()->create())
            ->post(route('admin.entries.store', 'post'), ['title' => 'Sneaky post', 'status' => 'published'])
            ->assertSessionHasErrors('status');

        expect(Entry::count())->toBe(0);
    });

    it('lets contributors save drafts', function () {
        $contributor = User::factory()->contributor()->create();

        $this->actingAs($contributor)
            ->post(route('admin.entries.store', 'post'), ['title' => 'My first recipe', 'status' => 'draft'])
            ->assertRedirect();

        expect(Entry::first()->author_id)->toBe($contributor->id);
    });

    it('needs a future date to schedule', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), ['title' => 'Winter menu', 'status' => 'scheduled', 'published_at' => now()->subDay()->toIso8601String()])
            ->assertSessionHasErrors('published_at');

        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), ['title' => 'Winter menu', 'status' => 'scheduled'])
            ->assertSessionHasErrors('published_at');
    });

    it('schedules with a future date', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), ['title' => 'Winter menu', 'status' => 'scheduled', 'published_at' => now()->addWeek()->toIso8601String()])
            ->assertSessionHasNoErrors();

        expect(Entry::first()->status)->toBe(EntryStatus::Scheduled);
    });

    it('refuses a parent on posts and parents of another type', function () {
        $post = Entry::factory()->create();

        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'post'), ['title' => 'Child post', 'status' => 'draft', 'parent_id' => $post->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'page'), ['title' => 'Child page', 'status' => 'draft', 'parent_id' => $post->id])
            ->assertSessionHasErrors('parent_id');
    });

    it('creates a child page', function () {
        $about = Entry::factory()->page()->create();

        $this->actingAs($this->editor)
            ->post(route('admin.entries.store', 'page'), ['title' => 'Our Team', 'status' => 'draft', 'parent_id' => $about->id, 'menu_order' => 2])
            ->assertSessionHasNoErrors();

        expect(Entry::firstWhere('title', 'Our Team')->parent_id)->toBe($about->id);
    });

    it('forbids subscribers from creating', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.entries.store', 'post'), ['title' => 'Spam', 'status' => 'draft'])
            ->assertForbidden();
    });
});

describe('edit and update', function () {
    it('opens comments on new entries when the config says so', function (bool $default) {
        config(['verso.comments.open_by_default' => $default]);

        $response = $this->actingAs($this->editor)->get(route('admin.entries.create', 'post'));

        expect(SharedSlots::item($response, AdminSlot::EntryPublish, 'comments')['props'])->toBe(['open' => $default]);
    })->with([true, false]);

    it('shows and saves the comments switch of an entry', function () {
        $entry = Entry::factory()->by($this->editor)->create(['title' => 'Our Favourite Bakeries in Braga', 'meta' => ['comments_open' => false]]);

        $response = $this->actingAs($this->editor)->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]));

        expect(SharedSlots::item($response, AdminSlot::EntryPublish, 'comments')['props'])->toBe(['open' => false]);

        $this->actingAs($this->editor)
            ->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $entry->id]), [
                'title' => 'Our Favourite Bakeries in Braga',
                'status' => 'draft',
                'meta' => ['comments_open' => true],
            ])
            ->assertRedirect();

        expect($entry->fresh()->meta['comments_open'])->toBeTrue();
    });

    it('shows the editor with terms and revisions', function () {
        $entry = Entry::factory()->create(['title' => 'Garden diary']);
        $tag = Term::factory()->tag()->create();
        $entry->terms()->attach($tag);
        Revision::factory()->for($entry)->create(['title' => 'Garden diary, first try', 'user_id' => $this->editor->id]);

        $this->actingAs($this->editor)
            ->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/entries/edit')
                ->where('entry.title', 'Garden diary')
                ->where('entry.terms.tag', [$tag->id])
                ->where('entry.terms.category', [])
                ->where('revisions.0.title', 'Garden diary, first try')
                ->where('revisions.0.author', 'Marta Gil')
                ->where('can.delete', true)
            );
    });

    it('returns 404 when the type in the URL does not match', function () {
        $page = Entry::factory()->page()->create();

        $this->actingAs($this->editor)
            ->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $page->id]))
            ->assertNotFound();
    });

    it('excludes the page and its children from parent options', function () {
        $about = Entry::factory()->page()->create(['title' => 'About']);
        Entry::factory()->page()->create(['title' => 'Team', 'parent_id' => $about->id]);
        Entry::factory()->page()->create(['title' => 'Contact']);

        $this->actingAs($this->editor)
            ->get(route('admin.entries.edit', ['contentType' => 'page', 'entry' => $about->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('parents', 1)
                ->where('parents.0.title', 'Contact')
            );
    });

    it('updates the entry and keeps a revision', function () {
        $entry = Entry::factory()->published()->create(['title' => 'Bike guide', 'slug' => 'bike-guide']);

        $this->actingAs($this->editor)
            ->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $entry->id]), [
                'title' => 'Choosing a Bike for City Commuting',
                'slug' => 'bike-guide',
                'status' => 'published',
                'published_at' => $entry->published_at->toIso8601String(),
            ])
            ->assertRedirect(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]));

        expect($entry->fresh()->title)->toBe('Choosing a Bike for City Commuting')
            ->and($entry->revisions()->first()->title)->toBe('Bike guide')
            ->and($entry->fresh()->isPublished())->toBeTrue();
    });

    it('lets authors edit their own entries only', function () {
        $author = User::factory()->author()->create();
        $own = Entry::factory()->by($author)->create();
        $other = Entry::factory()->create();

        $this->actingAs($author)
            ->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $own->id]), ['title' => 'Updated by me', 'status' => 'draft'])
            ->assertSessionHasNoErrors();

        $this->actingAs($author)
            ->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $other->id]), ['title' => 'Hijacked', 'status' => 'draft'])
            ->assertForbidden();

        $this->actingAs($author)
            ->get(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $other->id]))
            ->assertForbidden();
    });

    it('refuses a parent loop', function () {
        $about = Entry::factory()->page()->create();
        $team = Entry::factory()->page()->create(['parent_id' => $about->id]);

        $this->actingAs($this->editor)
            ->put(route('admin.entries.update', ['contentType' => 'page', 'entry' => $about->id]), ['title' => 'About', 'status' => 'draft', 'parent_id' => $team->id])
            ->assertSessionHasErrors('parent_id');
    });
});

describe('trash and restore', function () {
    it('moves an entry to the trash', function () {
        $entry = Entry::factory()->create();

        $this->actingAs($this->editor)
            ->delete(route('admin.entries.destroy', ['contentType' => 'post', 'entry' => $entry->id]))
            ->assertRedirect(route('admin.entries.index', 'post'));

        expect($entry->fresh()->trashed())->toBeTrue();
    });

    it('forbids contributors from trashing', function () {
        $contributor = User::factory()->contributor()->create();
        $entry = Entry::factory()->by($contributor)->create();

        $this->actingAs($contributor)
            ->delete(route('admin.entries.destroy', ['contentType' => 'post', 'entry' => $entry->id]))
            ->assertForbidden();
    });

    it('restores an entry from the trash', function () {
        $entry = Entry::factory()->trashed()->create();

        $this->actingAs($this->editor)
            ->from(route('admin.entries.index', ['contentType' => 'post', 'status' => 'trash']))
            ->post(route('admin.entries.restore', ['contentType' => 'post', 'entry' => $entry->id]))
            ->assertRedirect(route('admin.entries.index', ['contentType' => 'post', 'status' => 'trash']));

        expect($entry->fresh()->trashed())->toBeFalse()
            ->and($entry->fresh()->status)->toBe(EntryStatus::Draft);
    });

    it('trashes many entries and skips the ones the user cannot touch', function () {
        $author = User::factory()->author()->create();
        $own = Entry::factory()->by($author)->count(2)->create();
        $other = Entry::factory()->create();
        $page = Entry::factory()->page()->by($author)->create();

        $this->actingAs($author)
            ->post(route('admin.entries.bulk', 'post'), ['action' => 'trash', 'ids' => [...$own->pluck('id'), $other->id, $page->id]])
            ->assertRedirect();

        expect(Entry::onlyTrashed()->pluck('id')->sort()->values()->all())->toBe($own->pluck('id')->sort()->values()->all());
    });

    it('restores many entries', function () {
        $entries = Entry::factory()->trashed()->count(3)->create();

        $this->actingAs($this->editor)
            ->post(route('admin.entries.bulk', 'post'), ['action' => 'restore', 'ids' => $entries->pluck('id')->all()])
            ->assertRedirect();

        expect(Entry::onlyTrashed()->count())->toBe(0);
    });

    it('validates bulk actions', function () {
        $this->actingAs($this->editor)
            ->post(route('admin.entries.bulk', 'post'), ['action' => 'publish', 'ids' => []])
            ->assertSessionHasErrors(['action', 'ids']);
    });
});

describe('revisions', function () {
    it('restores a revision', function () {
        $entry = Entry::factory()->create(['title' => 'Now']);
        $revision = Revision::factory()->for($entry)->create(['title' => 'Before']);

        $this->actingAs($this->editor)
            ->post(route('admin.entries.revisions.restore', ['contentType' => 'post', 'entry' => $entry->id, 'revision' => $revision->id]))
            ->assertRedirect(route('admin.entries.edit', ['contentType' => 'post', 'entry' => $entry->id]));

        expect($entry->fresh()->title)->toBe('Before');
    });

    it('returns 404 for a revision of another entry', function () {
        $entry = Entry::factory()->create();
        $foreign = Revision::factory()->create();

        $this->actingAs($this->editor)
            ->post(route('admin.entries.revisions.restore', ['contentType' => 'post', 'entry' => $entry->id, 'revision' => $foreign->id]))
            ->assertNotFound();
    });

    it('forbids authors from restoring revisions of other entries', function () {
        $entry = Entry::factory()->create();
        $revision = Revision::factory()->for($entry)->create();

        $this->actingAs(User::factory()->author()->create())
            ->post(route('admin.entries.revisions.restore', ['contentType' => 'post', 'entry' => $entry->id, 'revision' => $revision->id]))
            ->assertForbidden();
    });
});

// Nav keys from the core and content modules only, so other modules can add items.
function contentNavKeys(iterable $nav): array
{
    return collect($nav)->pluck('key')
        ->filter(fn (string $key) => $key === 'dashboard' || str_starts_with($key, 'entries.') || str_starts_with($key, 'terms.'))
        ->values()
        ->all();
}

describe('navigation and dashboard', function () {
    it('adds posts, pages, categories and tags to the admin nav', function () {
        $this->actingAs($this->editor)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('versoNav', fn ($nav) => contentNavKeys($nav) === ['dashboard', 'entries.post', 'terms.category', 'terms.tag', 'entries.page'])
                ->where('versoNav.1.href', '/admin/entries/post')
            );
    });

    it('hides terms from authors', function () {
        $this->actingAs(User::factory()->author()->create())
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('versoNav', fn ($nav) => contentNavKeys($nav) === ['dashboard', 'entries.post', 'entries.page'])
            );
    });

    it('shows content counts on the dashboard', function () {
        Entry::factory()->published()->count(3)->create();
        Entry::factory()->count(2)->create();
        Entry::factory()->scheduled()->create();

        $this->actingAs($this->editor)
            ->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats', fn ($stats) => collect($stats)->pluck('value', 'key')->only(['published.post', 'published.page', 'drafts', 'scheduled'])->all() === [
                    'published.post' => 3,
                    'published.page' => 0,
                    'drafts' => 2,
                    'scheduled' => 1,
                ])
                ->where('stats.0.href', '/admin/entries/post?status=published')
            );
    });
});
