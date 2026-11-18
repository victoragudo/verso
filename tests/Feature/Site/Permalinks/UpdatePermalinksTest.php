<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Actions\UpdatePermalinks;
use App\Verso\Site\Events\SiteSettingsUpdated;
use App\Verso\Site\Menus\MenuCache;
use App\Verso\Site\Permalinks\PermalinkSettings;
use App\Verso\Site\Permalinks\PermalinkValues;
use App\Verso\Site\Sitemap\SitemapCache;
use App\Verso\Theme\Cache\PageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    config(['verso.page_cache.enabled' => false]);

    $this->author = User::factory()->author()->create(['name' => 'Joana Reis']);
    $this->post = Entry::factory()->published()->by($this->author)->create([
        'title' => 'Pastel de Nata, Ranked',
        'slug' => 'pastel-de-nata-ranked',
        'published_at' => '2026-05-04 09:00:00',
    ]);
});

function savePermalinks(array $structures = [], array $archiveBases = [], array $taxonomyBases = [], bool $trailingSlash = false): void
{
    app(UpdatePermalinks::class)->handle(new PermalinkValues($structures, $archiveBases, $taxonomyBases, $trailingSlash));
}

it('redirects old /blog links after moving to dated links', function () {
    savePermalinks(['post' => '/{year}/{month}/{slug}']);

    expect($this->post->path())->toBe('/2026/05/pastel-de-nata-ranked');
    $this->get('/blog/pastel-de-nata-ranked')->assertStatus(301)->assertRedirect(url('/2026/05/pastel-de-nata-ranked'));
    $this->get('/2026/05/pastel-de-nata-ranked')->assertOk();
});

it('redirects every older structure, not only the last one', function () {
    savePermalinks(['post' => '/{year}/{month}/{slug}']);
    savePermalinks(['post' => '/archives/{id}']);
    savePermalinks(['post' => '/stories/{slug}']);

    $this->get('/blog/pastel-de-nata-ranked')->assertRedirect(url('/stories/pastel-de-nata-ranked'));
    $this->get('/2026/05/pastel-de-nata-ranked')->assertRedirect(url('/stories/pastel-de-nata-ranked'));
    $this->get('/archives/'.$this->post->id)->assertRedirect(url('/stories/pastel-de-nata-ranked'));

    expect(app(Settings::class)->get(PermalinkSettings::PREVIOUS_STRUCTURES)['post'])
        ->toBe(['/archives/{id}', '/{year}/{month}/{slug}', '/blog/{slug}']);
});

it('forgets a structure when it becomes the current one again', function () {
    savePermalinks(['post' => '/{slug}']);
    savePermalinks(['post' => '/blog/{slug}']);

    expect(app(Settings::class)->get(PermalinkSettings::PREVIOUS_STRUCTURES)['post'])->toBe(['/{slug}']);
    $this->get('/blog/pastel-de-nata-ranked')->assertOk();
});

it('keeps only the configured number of older structures', function () {
    config(['verso.permalinks.previous_kept' => 2]);

    savePermalinks(['post' => '/a/{slug}']);
    savePermalinks(['post' => '/b/{slug}']);
    savePermalinks(['post' => '/c/{slug}']);

    expect(app(Settings::class)->get(PermalinkSettings::PREVIOUS_STRUCTURES)['post'])->toBe(['/b/{slug}', '/a/{slug}']);
});

it('redirects old archive and taxonomy bases', function () {
    $food = Term::factory()->create(['taxonomy' => 'category', 'name' => 'Food', 'slug' => 'food']);

    savePermalinks(archiveBases: ['post' => 'journal'], taxonomyBases: ['category' => 'topics', 'tag' => 'tag']);

    $this->get('/blog?page=2')->assertStatus(301)->assertRedirect(url('/journal?page=2'));
    $this->get('/category/food')->assertRedirect(url('/topics/food'));
    $this->get('/topics/food')->assertOk();
    expect($food->path())->toBe('/topics/food');
});

it('refreshes the page cache, sitemap and menus', function () {
    Event::fake([SiteSettingsUpdated::class]);
    $pages = Mockery::mock(PageCache::class);
    $pages->shouldReceive('flush')->atLeast()->once();
    app()->instance(PageCache::class, $pages);
    $menus = Mockery::mock(MenuCache::class);
    $menus->shouldReceive('flush')->atLeast()->once();
    app()->instance(MenuCache::class, $menus);

    savePermalinks(['post' => '/{slug}']);

    Event::assertDispatched(SiteSettingsUpdated::class, fn (SiteSettingsUpdated $event) => in_array(PermalinkSettings::STRUCTURES, $event->keys, true));
});

it('drops cached sitemaps so they list the new links', function () {
    $this->get('/sitemap-post.xml')->assertSee(url('/blog/pastel-de-nata-ranked'));

    savePermalinks(['post' => '/{year}/{slug}']);

    $this->get('/sitemap-post.xml')->assertSee(url('/2026/pastel-de-nata-ranked'))->assertDontSee('/blog/pastel-de-nata-ranked');
    expect(app(SitemapCache::class))->toBeInstanceOf(SitemapCache::class);
});
