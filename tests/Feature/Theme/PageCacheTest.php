<?php

use App\Models\User;
use App\Verso\Comments\Actions\ModerateComments;
use App\Verso\Comments\Enums\CommentStatus;
use App\Verso\Comments\Models\Comment;
use App\Verso\Content\Actions\CreateTerm;
use App\Verso\Content\Actions\PublishEntry;
use App\Verso\Content\Actions\TrashEntry;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Actions\UpdateTerm;
use App\Verso\Content\Jobs\PublishScheduledEntries;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Models\Menu;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => true]);
    app()->forgetInstance(PageCache::class);

    $this->author = User::factory()->author()->create(['name' => 'Tomás Herrera']);
    $this->entry = Entry::factory()->published()->by($this->author)->create([
        'title' => 'A Slow Weekend in Porto',
        'slug' => 'slow-weekend-porto',
    ]);
});

it('marks the first visit as a miss and the next one as a hit', function () {
    $this->get('/blog/slow-weekend-porto')->assertOk()->assertHeader(PageCache::HEADER, 'MISS');

    $this->get('/blog/slow-weekend-porto')
        ->assertOk()
        ->assertHeader(PageCache::HEADER, 'HIT')
        ->assertSee('A Slow Weekend in Porto');
});

it('serves the stored copy on a hit', function () {
    $this->get('/blog/slow-weekend-porto');

    Entry::query()->whereKey($this->entry->id)->update(['title' => 'Changed Behind the Cache']);

    $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'HIT')->assertSee('A Slow Weekend in Porto');
});

it('caches every public route', function (string $path) {
    $this->get($path)->assertOk()->assertHeader(PageCache::HEADER, 'MISS');
    $this->get($path)->assertOk()->assertHeader(PageCache::HEADER, 'HIT');
})->with(['/', '/blog', '/blog/slow-weekend-porto', '/feed']);

it('keeps the feed content type on a hit', function () {
    $this->get('/feed');

    $this->get('/feed')->assertHeader(PageCache::HEADER, 'HIT')->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
});

it('skips the cache for signed in users', function () {
    $this->actingAs($this->author);

    $this->get('/blog/slow-weekend-porto')->assertOk()->assertHeaderMissing(PageCache::HEADER);
    $this->get('/blog/slow-weekend-porto')->assertOk()->assertHeaderMissing(PageCache::HEADER);
});

it('does not cache 404 pages', function () {
    $this->get('/a-missing-page')->assertNotFound()->assertHeaderMissing(PageCache::HEADER);
    $this->get('/a-missing-page')->assertNotFound()->assertHeaderMissing(PageCache::HEADER);
});

it('does not cache redirects', function () {
    $front = Entry::factory()->page()->published()->by($this->author)->create(['slug' => 'welcome']);
    app(Settings::class)->set('reading.front_page', $front->id);

    $this->get('/welcome')->assertStatus(301)->assertHeaderMissing(PageCache::HEADER);
});

it('skips the cache for unknown query parameters', function () {
    $this->get('/search?q=porto')->assertOk()->assertHeaderMissing(PageCache::HEADER);
    $this->get('/blog?utm_source=newsletter')->assertOk()->assertHeaderMissing(PageCache::HEADER);
});

it('caches each list page on its own', function () {
    app(Settings::class)->set('reading.posts_per_page', 1);
    Entry::factory()->published()->by($this->author)->create(['title' => 'Older Notes on Sourdough', 'published_at' => now()->subYear()]);

    $this->get('/blog')->assertHeader(PageCache::HEADER, 'MISS');
    $this->get('/blog?page=2')->assertHeader(PageCache::HEADER, 'MISS')->assertSee('Older Notes on Sourdough');
    $this->get('/blog?page=2')->assertHeader(PageCache::HEADER, 'HIT')->assertSee('Older Notes on Sourdough');
});

it('skips the cache for form posts', function () {
    $this->post('/blog/slow-weekend-porto')->assertHeaderMissing(PageCache::HEADER);
});

it('can be turned off in the config', function () {
    config(['verso.page_cache.enabled' => false]);
    app()->forgetInstance(PageCache::class);

    $this->get('/')->assertOk()->assertHeaderMissing(PageCache::HEADER);
});

describe('invalidation', function () {
    beforeEach(function () {
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'HIT');
    });

    it('happens when an entry is saved', function () {
        app(UpdateEntry::class)->handle($this->entry, ['title' => 'A Slower Weekend in Porto']);

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS')->assertSee('A Slower Weekend in Porto');
    });

    it('happens when an entry is published', function () {
        $draft = Entry::factory()->by($this->author)->create(['title' => 'Cooking Paella for Twelve People']);

        app(PublishEntry::class)->handle($draft);

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');
    });

    it('happens when a scheduled entry goes live', function () {
        $scheduled = Entry::factory()->by($this->author)->create(['title' => 'Spring Garden Plan', 'slug' => 'spring-garden-plan']);
        $scheduled->schedule(now()->addMinutes(5));
        $this->get('/blog')->assertHeader(PageCache::HEADER, 'MISS');
        $this->get('/blog')->assertHeader(PageCache::HEADER, 'HIT')->assertDontSee('Spring Garden Plan');

        Carbon::setTestNow(now()->addMinutes(10));
        (new PublishScheduledEntries)->handle(app(PublishEntry::class));

        $this->get('/blog')->assertHeader(PageCache::HEADER, 'MISS')->assertSee('Spring Garden Plan');
    });

    it('happens when an entry is trashed', function () {
        app(TrashEntry::class)->handle($this->entry);

        $this->get('/blog/slow-weekend-porto')->assertNotFound();
    });

    it('happens when a setting changes', function () {
        app(Settings::class)->set('site.tagline', 'Notes from a small kitchen');

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');
    });

    it('happens when a setting is removed', function () {
        app(Settings::class)->set('site.tagline', 'Notes from a small kitchen');
        $this->get('/blog/slow-weekend-porto');

        app(Settings::class)->forget('site.tagline');

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');
    });

    it('happens when terms change', function () {
        $term = app(CreateTerm::class)->handle('category', ['name' => 'Travel']);
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');

        app(UpdateTerm::class)->handle($term, ['name' => 'Travel Notes']);

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');
    });

    it('happens when a term is deleted', function () {
        $term = Term::factory()->create();
        $this->get('/blog/slow-weekend-porto');

        $term->delete();

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');
    });
});

describe('comments and menus', function () {
    beforeEach(function () {
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'HIT');
    });

    it('is emptied when a comment is approved', function () {
        $comment = Comment::factory()->on($this->entry)->create(['author_name' => 'Beatriz Sousa', 'body' => 'We loved the Livraria Lello tip.']);
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'HIT');

        app(ModerateComments::class)->handle([$comment->id], CommentStatus::Approved);

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS')->assertSee('We loved the Livraria Lello tip.');
    });

    it('is emptied when an approved comment goes to spam', function () {
        $comment = Comment::factory()->approved()->on($this->entry)->create(['body' => 'Lovely photos of the Douro.']);
        $this->get('/blog/slow-weekend-porto');
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'HIT');

        app(ModerateComments::class)->handle([$comment->id], CommentStatus::Spam);

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS')->assertDontSee('Lovely photos of the Douro.');
    });

    it('is kept when a comment waits for moderation', function () {
        Comment::factory()->on($this->entry)->create(['body' => 'Check out my casino site']);

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'HIT');
    });

    it('is emptied when a menu changes', function () {
        $menu = Menu::factory()->at('primary')->create();
        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS');

        app(SyncMenuItems::class)->handle($menu, [['type' => 'custom', 'label' => 'Contact', 'url' => '/contact']]);

        $this->get('/blog/slow-weekend-porto')->assertHeader(PageCache::HEADER, 'MISS')->assertSee('verso-menu--primary', false);
    });
});

it('is skipped right after a form post, so notices stay private', function () {
    $this->withSession(['_flash.old' => ['verso_comment'], 'verso_comment' => ['status' => 'pending', 'message' => 'Thanks! Your comment is waiting for moderation.']])
        ->get('/blog/slow-weekend-porto')
        ->assertOk()
        ->assertHeaderMissing(PageCache::HEADER);

    $this->get('/blog/slow-weekend-porto')
        ->assertHeader(PageCache::HEADER, 'MISS')
        ->assertDontSee('waiting for moderation');
});
