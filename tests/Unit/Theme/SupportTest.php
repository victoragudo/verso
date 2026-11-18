<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Actions\ActivateTheme;
use App\Verso\Theme\Cache\PageCache;
use App\Verso\Theme\Listeners\FlushPageCache;
use App\Verso\Theme\Support\ArchiveContext;
use App\Verso\Theme\Support\EntryListing;
use App\Verso\Theme\Support\EntryResolver;
use App\Verso\Theme\Support\FrontPage;
use App\Verso\Theme\Support\StaticExporter;
use App\Verso\Theme\Support\ThemeComposer;
use App\Verso\Theme\Support\ThemeResponse;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    $this->author = User::factory()->author()->create(['name' => 'Pablo Serrano']);
});

describe('EntryResolver', function () {
    it('walks nested slugs', function () {
        $about = Entry::factory()->page()->published()->create(['slug' => 'about']);
        $team = Entry::factory()->page()->published()->create(['slug' => 'team', 'parent_id' => $about->id]);

        expect(app(EntryResolver::class)->resolve(['page'], 'about/team')?->id)->toBe($team->id)
            ->and(app(EntryResolver::class)->resolve(['page'], '/about/')?->id)->toBe($about->id);
    });

    it('returns null for empty paths, unknown types and missing slugs', function () {
        Entry::factory()->page()->published()->create(['slug' => 'about']);

        expect(app(EntryResolver::class)->resolve(['page'], ''))->toBeNull()
            ->and(app(EntryResolver::class)->resolve([], 'about'))->toBeNull()
            ->and(app(EntryResolver::class)->resolve(['post'], 'about'))->toBeNull()
            ->and(app(EntryResolver::class)->resolve(['page'], 'about/history'))->toBeNull();
    });

    it('needs every entry on the path to be published', function () {
        $about = Entry::factory()->page()->scheduled()->create(['slug' => 'about']);
        Entry::factory()->page()->published()->create(['slug' => 'team', 'parent_id' => $about->id]);

        expect(app(EntryResolver::class)->resolve(['page'], 'about/team'))->toBeNull();
    });
});

describe('EntryListing', function () {
    it('reads posts per page from settings', function () {
        app(Settings::class)->set('reading.posts_per_page', 7);
        expect(app(EntryListing::class)->perPage())->toBe(7);

        app(Settings::class)->set('reading.posts_per_page', 0);
        expect(app(EntryListing::class)->perPage())->toBe(1);

        app(Settings::class)->set('reading.posts_per_page', 'many');
        expect(app(EntryListing::class)->perPage())->toBe(10);
    });

    it('lists only published entries, newest first', function () {
        Entry::factory()->published()->create(['title' => 'Older Harbour Walk', 'published_at' => now()->subWeek()]);
        Entry::factory()->published()->create(['title' => 'Newer Market Day', 'published_at' => now()->subDay()]);
        Entry::factory()->create(['title' => 'Draft Olive Harvest']);

        $titles = app(EntryListing::class)->paginate(Entry::query(), ArchiveContext::search('harbour'))->getCollection()->pluck('title')->all();

        expect($titles)->toBe(['Newer Market Day', 'Older Harbour Walk']);
    });
});

describe('FrontPage', function () {
    it('returns the published front page', function () {
        $page = Entry::factory()->page()->published()->create();
        app(Settings::class)->set('reading.front_page', $page->id);

        expect(app(FrontPage::class)->entry()?->id)->toBe($page->id);
    });

    it('returns null when none is set or it is not live', function () {
        expect(app(FrontPage::class)->entry())->toBeNull();

        app(Settings::class)->set('reading.front_page', Entry::factory()->page()->create()->id);
        expect(app(FrontPage::class)->entry())->toBeNull();

        app(Settings::class)->set('reading.front_page', 'home');
        expect(app(FrontPage::class)->entry())->toBeNull();
    });
});

describe('ThemeResponse', function () {
    it('picks the entry view of a type when the theme has one', function () {
        app(ThemeManager::class)->registerViews();

        expect(app(ThemeResponse::class)->entryView('page'))->toBe('entry-page')
            ->and(app(ThemeResponse::class)->entryView('post'))->toBe('entry')
            ->and(app(ThemeResponse::class)->entryView('recipe'))->toBe('entry');
    });

    it('renders the 404 view with a 404 status', function () {
        app(ThemeManager::class)->registerViews();

        $response = app(ThemeResponse::class)->notFound();

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getContent())->toContain('This page could not be found');
    });
});

describe('ThemeComposer', function () {
    it('shares the site details', function () {
        app(Settings::class)->setMany(['site.name' => 'Serrano Notes', 'site.tagline' => 'Words from the north', 'site.locale' => 'pt_BR']);

        expect(app(ThemeComposer::class)->site())->toBe([
            'name' => 'Serrano Notes',
            'tagline' => 'Words from the north',
            'locale' => 'pt-BR',
            'dir' => 'ltr',
        ]);
    });

    it('gives right to left site languages the rtl direction', function () {
        app(Settings::class)->setMany(['site.name' => 'Bayt al-Qahwa', 'site.locale' => 'ar']);

        expect(app(ThemeComposer::class)->site())->toMatchArray(['locale' => 'ar', 'dir' => 'rtl']);
    });

    it('lists top level published pages in menu order', function () {
        config(['verso.themes.nav_pages' => 2]);
        $about = Entry::factory()->page()->published()->create(['title' => 'About', 'menu_order' => 2]);
        Entry::factory()->page()->published()->create(['title' => 'Contact', 'menu_order' => 1]);
        Entry::factory()->page()->published()->create(['title' => 'Press', 'menu_order' => 3]);
        Entry::factory()->page()->published()->create(['title' => 'Team', 'parent_id' => $about->id]);
        Entry::factory()->page()->create(['title' => 'Draft Jobs']);

        expect(app(ThemeComposer::class)->navigation()->pluck('title')->all())->toBe(['Contact', 'About']);
    });
});

describe('StaticExporter', function () {
    it('rewrites page links into folders', function () {
        $html = '<a href="https://journal.example.org/blog?page=2">Older</a> <a href="https://journal.example.org/blog?page=1">Newer</a> <a href="/?page=1">Home</a> <a href="/search?q=bread">Search</a>';

        expect(app(StaticExporter::class)->rewriteLinks($html))->toBe(
            '<a href="https://journal.example.org/blog/page/2/">Older</a> <a href="https://journal.example.org/blog">Newer</a> <a href="/">Home</a> <a href="/search?q=bread">Search</a>'
        );
    });

    it('lists every public path with its page count', function () {
        app(Settings::class)->set('reading.posts_per_page', 2);
        Entry::factory()->count(3)->published()->create();
        Entry::factory()->page()->published()->create(['slug' => 'about']);

        $paths = app(StaticExporter::class)->paths();

        expect($paths['/'])->toBe(2)
            ->and($paths['/blog'])->toBe(2)
            ->and($paths['/about'])->toBe(1)
            ->and($paths['/feed'])->toBe(1)
            ->and($paths)->toHaveCount(7);
    });
});

describe('ActivateTheme and FlushPageCache', function () {
    it('activates the theme through the manager', function () {
        expect(app(ActivateTheme::class)->handle('default')->slug)->toBe('default')
            ->and(app(Settings::class)->get('theme.active'))->toBe('default');
    });

    it('flushes the page cache for any event', function () {
        $cache = app(PageCache::class);
        $version = $cache->version();

        app(FlushPageCache::class)->handle(new stdClass);

        expect($cache->version())->toBe($version + 1);
    });
});
