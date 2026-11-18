<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Settings\Settings;
use App\Verso\Media\Models\Media;
use App\Verso\Site\Sitemap\SitemapSettings;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 12:00:00');
    $this->admin = User::factory()->admin()->create();
    $this->post = Entry::factory()->published()->create(['title' => 'Bica or Galão?', 'slug' => 'bica-or-galao', 'published_at' => '2026-02-01 08:00:00']);
    $this->page = Entry::factory()->page()->published()->create(['title' => 'Contact the Roastery', 'slug' => 'contact']);
});

function sitemapForm(array $overrides = []): array
{
    return [
        'enabled' => true,
        'types' => ['post', 'page'],
        'taxonomies' => ['category', 'tag'],
        'excluded_entries' => [],
        'include_images' => false,
        'max_urls' => 5000,
        'last_modified' => 'updated',
        ...$overrides,
    ];
}

it('saves the sitemap options', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.site.permalinks.sitemap'), sitemapForm(['types' => ['post'], 'excluded_entries' => [$this->post->id], 'max_urls' => 1000, 'last_modified' => 'published']))
        ->assertRedirect(route('admin.site.permalinks.edit', ['tab' => 'sitemap']))
        ->assertSessionHasNoErrors();

    $sitemap = app(SitemapSettings::class);
    expect($sitemap->types())->toBe(['post'])
        ->and($sitemap->excludedEntries())->toBe([$this->post->id])
        ->and($sitemap->maxUrls())->toBe(1000)
        ->and($sitemap->lastModified())->toBe('published');
});

it('validates the sitemap options', function (array $overrides, string $field) {
    $this->actingAs($this->admin)->put(route('admin.site.permalinks.sitemap'), sitemapForm($overrides))->assertSessionHasErrors($field);
})->with([
    'unknown type' => [['types' => ['podcast']], 'types.0'],
    'unknown taxonomy' => [['taxonomies' => ['mood']], 'taxonomies.0'],
    'missing entry' => [['excluded_entries' => [999999]], 'excluded_entries.0'],
    'too many urls' => [['max_urls' => 60000], 'max_urls'],
    'zero urls' => [['max_urls' => 0], 'max_urls'],
    'bad date source' => [['last_modified' => 'created'], 'last_modified'],
]);

it('turns the sitemap off and drops it from robots.txt', function () {
    app(Settings::class)->set(SitemapSettings::ENABLED, false);

    $this->get('/sitemap.xml')->assertNotFound();
    $this->get('/sitemap-post.xml')->assertNotFound();
    expect($this->get('/robots.txt')->getContent())->not->toContain('Sitemap:');
});

it('lists only the chosen content types and taxonomies', function () {
    $this->post->terms()->attach(Term::factory()->create(['taxonomy' => 'category', 'slug' => 'coffee']));
    app(Settings::class)->setMany([SitemapSettings::TYPES => ['page'], SitemapSettings::TAXONOMIES => []]);

    $index = $this->get('/sitemap.xml')->getContent();

    expect($index)->toContain(url('/sitemap-page.xml'))
        ->not->toContain('sitemap-post.xml')
        ->not->toContain('sitemap-taxonomy-category.xml');
    $this->get('/sitemap-post.xml')->assertNotFound();
});

it('leaves out single entries from the settings and from the entry SEO panel', function () {
    $hidden = Entry::factory()->published()->create(['slug' => 'staff-rota', 'meta' => ['seo' => ['sitemap_exclude' => true]]]);
    app(Settings::class)->set(SitemapSettings::EXCLUDED_ENTRIES, [$this->post->id]);

    $xml = $this->get('/sitemap-post.xml')->getContent();

    expect($xml)->not->toContain('bica-or-galao')->not->toContain('staff-rota')
        ->and($hidden->fresh()->meta['seo']['sitemap_exclude'])->toBeTrue();
});

it('adds featured images when asked', function () {
    $media = Media::factory()->create(['filename' => 'galao.jpg', 'mime_type' => 'image/jpeg']);
    $this->post->update(['meta' => ['featured_image' => ['mediaId' => $media->id]]]);
    app(Settings::class)->set(SitemapSettings::INCLUDE_IMAGES, true);

    $xml = $this->get('/sitemap-post.xml')->getContent();

    expect($xml)->toContain('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"')
        ->toContain('<image:loc>'.url($media->url()).'</image:loc>');
});

it('uses the publish date as last modified when asked', function () {
    $this->post->forceFill(['updated_at' => '2026-10-10 10:00:00'])->saveQuietly();
    app(Settings::class)->set(SitemapSettings::LAST_MODIFIED, 'published');

    expect($this->get('/sitemap-post.xml')->getContent())->toContain('<lastmod>2026-02-01T08:00:00+00:00</lastmod>');
});

it('splits files by the saved number of urls', function () {
    Entry::factory()->count(2)->published()->create();
    app(Settings::class)->set(SitemapSettings::MAX_URLS, 2);

    expect($this->get('/sitemap.xml')->getContent())->toContain(url('/sitemap-post-2.xml'));
});
