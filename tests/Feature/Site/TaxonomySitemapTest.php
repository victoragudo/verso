<?php

use App\Verso\Content\Actions\UpdateTerm;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use Illuminate\Support\Carbon;

function taxonomySitemapLocs(string $body): array
{
    $xml = simplexml_load_string($body);
    $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

    return array_map('strval', $xml->xpath('//s:loc'));
}

function publishedPostIn(Term ...$terms): Entry
{
    $post = Entry::factory()->published()->create();
    $post->terms()->attach(array_map(fn (Term $term) => $term->id, $terms));

    return $post;
}

it('lists taxonomy sitemaps for categories and tags with published entries', function () {
    $travel = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel']);
    $coffee = Term::factory()->tag()->create(['name' => 'Coffee', 'slug' => 'coffee']);
    publishedPostIn($travel, $coffee);

    expect(taxonomySitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([
        url('/sitemap-post.xml'),
        url('/sitemap-taxonomy-category.xml'),
        url('/sitemap-taxonomy-tag.xml'),
    ]);
});

it('skips taxonomies whose terms hold no published entries', function () {
    $travel = Term::factory()->create(['slug' => 'travel']);
    Term::factory()->tag()->create(['slug' => 'espresso']);
    $draft = Entry::factory()->create();
    $draft->terms()->attach($travel->id);

    expect(taxonomySitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([]);
});

it('lists term archives with published entries only', function () {
    $travel = Term::factory()->create(['slug' => 'travel']);
    $recipes = Term::factory()->create(['slug' => 'recipes']);
    Term::factory()->create(['slug' => 'empty-shelf']);
    publishedPostIn($travel);
    Entry::factory()->create()->terms()->attach($recipes->id);

    $response = $this->get('/sitemap-taxonomy-category.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    expect(taxonomySitemapLocs($response->getContent()))->toBe([url('/category/travel')]);
});

it('uses the last published entry change as lastmod', function () {
    $this->freezeTime();
    $coffee = Term::factory()->tag()->create(['slug' => 'coffee']);
    $older = publishedPostIn($coffee);
    $newer = publishedPostIn($coffee);
    Entry::query()->whereKey($older->id)->update(['updated_at' => Carbon::parse('2026-03-02 08:00:00')]);
    Entry::query()->whereKey($newer->id)->update(['updated_at' => Carbon::parse('2026-05-10 09:30:00')]);

    $body = $this->get('/sitemap-taxonomy-tag.xml')->getContent();

    expect($body)->toContain('<lastmod>2026-05-10T09:30:00+00:00</lastmod>')
        ->and($body)->not->toContain('2026-03-02');
});

it('splits big taxonomies in pages', function () {
    config(['verso.sitemap.max_urls' => 2]);
    foreach (['lisbon', 'porto', 'braga'] as $slug) {
        publishedPostIn(Term::factory()->create(['slug' => $slug]));
    }

    expect(taxonomySitemapLocs($this->get('/sitemap.xml')->getContent()))->toContain(
        url('/sitemap-taxonomy-category.xml'),
        url('/sitemap-taxonomy-category-2.xml'),
    )
        ->and(taxonomySitemapLocs($this->get('/sitemap-taxonomy-category-2.xml')->getContent()))->toBe([url('/category/braga')]);
});

it('returns 404 for unknown taxonomies and missing pages', function () {
    publishedPostIn(Term::factory()->create(['slug' => 'travel']));

    $this->get('/sitemap-taxonomy-genre.xml')->assertNotFound();
    $this->get('/sitemap-taxonomy-category-4.xml')->assertNotFound();
});

it('leaves out excluded taxonomies', function () {
    config(['verso.sitemap.exclude_taxonomies' => ['tag']]);
    publishedPostIn(Term::factory()->tag()->create(['slug' => 'coffee']));

    expect(taxonomySitemapLocs($this->get('/sitemap.xml')->getContent()))->toBe([url('/sitemap-post.xml')]);
    $this->get('/sitemap-taxonomy-tag.xml')->assertNotFound();
});

it('refreshes the taxonomy sitemap when a term changes', function () {
    $travel = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel']);
    publishedPostIn($travel);
    $this->get('/sitemap-taxonomy-category.xml');

    app(UpdateTerm::class)->handle($travel, ['slug' => 'journeys']);

    expect(taxonomySitemapLocs($this->get('/sitemap-taxonomy-category.xml')->getContent()))->toBe([url('/category/journeys')]);
});
