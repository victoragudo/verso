<?php

use App\Models\User;
use App\Verso\Content\Actions\TrashEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Actions\UpdateSiteSettings;
use App\Verso\Site\Sitemap\SitemapCache;

function sitemapXml(string $body): SimpleXMLElement
{
    $xml = simplexml_load_string($body);
    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    return $xml;
}

function sitemapLocs(string $body): array
{
    return array_map('strval', sitemapXml($body)->xpath('//s:loc'));
}

it('lists one sitemap per content type with public entries', function () {
    Entry::factory()->published()->create();
    Entry::factory()->page()->published()->create();

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    expect(sitemapLocs($response->getContent()))->toBe([url('/sitemap-post.xml'), url('/sitemap-page.xml')])
        ->and(sitemapXml($response->getContent())->getName())->toBe('sitemapindex');
});

it('skips content types without public entries', function () {
    Entry::factory()->published()->create();
    Entry::factory()->page()->create();

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([url('/sitemap-post.xml')]);
});

it('lists published entries of a type with their last change', function () {
    $paella = Entry::factory()->published()->create(['slug' => 'cooking-paella']);
    Entry::factory()->published()->create(['slug' => 'film-photography']);
    Entry::factory()->create(['slug' => 'draft-recipe']);
    Entry::factory()->scheduled()->create(['slug' => 'future-trip']);
    Entry::factory()->page()->published()->create(['slug' => 'about']);

    $body = $this->get('/sitemap-post.xml')->assertOk()->getContent();

    expect(sitemapLocs($body))->toBe([url('/blog/cooking-paella'), url('/blog/film-photography')])
        ->and((string) sitemapXml($body)->xpath('//s:lastmod')[0])->toBe($paella->updated_at->toAtomString());
});

it('leaves out entries hidden from search engines', function () {
    Entry::factory()->published()->create(['slug' => 'visible-post']);
    Entry::factory()->published()->create(['slug' => 'hidden-post', 'meta' => ['seo' => ['noindex' => true]]]);
    Entry::factory()->published()->create(['slug' => 'indexed-post', 'meta' => ['seo' => ['noindex' => false]]]);

    expect(sitemapLocs($this->get('/sitemap-post.xml')->getContent()))->toBe([url('/blog/visible-post'), url('/blog/indexed-post')]);
});

it('uses nested paths for child pages', function () {
    $about = Entry::factory()->page()->published()->create(['slug' => 'about']);
    Entry::factory()->page()->published()->create(['slug' => 'team', 'parent_id' => $about->id]);

    expect(sitemapLocs($this->get('/sitemap-page.xml')->getContent()))->toBe([url('/about'), url('/about/team')]);
});

it('splits big types in pages', function () {
    config(['verso.sitemap.max_urls' => 2]);
    Entry::factory()->published()->count(5)->create();

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([
        url('/sitemap-post.xml'), url('/sitemap-post-2.xml'), url('/sitemap-post-3.xml'),
    ]);
    expect(sitemapLocs($this->get('/sitemap-post-3.xml')->assertOk()->getContent()))->toHaveCount(1);
    $this->get('/sitemap-post-4.xml')->assertNotFound();
});

it('returns 404 for unknown sitemaps', function () {
    $this->get('/sitemap-recipe.xml')->assertNotFound();
});

it('leaves out excluded content types', function () {
    config(['verso.sitemap.exclude_types' => ['page']]);
    Entry::factory()->page()->published()->create();

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([]);
    $this->get('/sitemap-page.xml')->assertNotFound();
});

it('serves the sitemap from the cache', function () {
    Entry::factory()->published()->create(['slug' => 'cached-post']);
    $this->get('/sitemap-post.xml');

    Entry::query()->update(['slug' => 'renamed-quietly']);

    expect(sitemapLocs($this->get('/sitemap-post.xml')->getContent()))->toBe([url('/blog/cached-post')]);
});

it('refreshes the sitemap when an entry is saved', function () {
    $editor = User::factory()->editor()->create();
    $post = Entry::factory()->published()->create(['slug' => 'old-slug']);
    $this->get('/sitemap-post.xml');

    $this->actingAs($editor)->put(route('admin.entries.update', ['contentType' => 'post', 'entry' => $post->id]), [
        'title' => $post->title, 'slug' => 'new-slug', 'status' => 'published',
    ]);

    expect(sitemapLocs($this->get('/sitemap-post.xml')->getContent()))->toBe([url('/blog/new-slug')]);
});

it('refreshes the sitemap when an entry is trashed', function () {
    $post = Entry::factory()->published()->create();
    $this->get('/sitemap.xml');

    app(TrashEntry::class)->handle($post);

    expect(sitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([]);
});

it('refreshes the sitemap when the site settings change', function () {
    $home = Entry::factory()->page()->published()->create(['slug' => 'welcome']);
    $this->get('/sitemap-page.xml');

    app(UpdateSiteSettings::class)->handle(['reading.front_page' => $home->id]);

    expect(sitemapLocs($this->get('/sitemap-page.xml')->getContent()))->toBe([url('/')]);
});

it('drops every cached sitemap on flush', function () {
    $cache = app(SitemapCache::class);
    $before = $cache->key('index');

    $cache->flush();

    expect($cache->key('index'))->not->toBe($before)
        ->and($cache->remember('index', fn () => '<sitemapindex/>'))->toBe('<sitemapindex/>')
        ->and($cache->remember('index', fn () => 'rebuilt'))->toBe('<sitemapindex/>');
});

it('does not cache missing sitemaps', function () {
    $cache = app(SitemapCache::class);

    expect($cache->remember('type.recipe.1', fn () => null))->toBeNull()
        ->and($cache->remember('type.recipe.1', fn () => '<urlset/>'))->toBe('<urlset/>');
});

it('serves robots.txt from the setting with the sitemap link', function () {
    app(Settings::class)->set('seo.robots_txt', "User-agent: *\nDisallow: /checkout\n");

    $response = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())->toBe("User-agent: *\nDisallow: /checkout\n\nSitemap: ".url('/sitemap.xml')."\n");
});

it('serves the default robots.txt', function () {
    expect($this->get('/robots.txt')->getContent())->toStartWith("User-agent: *\nDisallow: /admin");
});

it('blocks all robots when the site discourages search engines', function () {
    app(Settings::class)->set('reading.discourage_search', true);

    expect($this->get('/robots.txt')->getContent())->toBe("User-agent: *\nDisallow: /\n");
});
