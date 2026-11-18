<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Site\Sitemap\SitemapBuilder;

it('parses sitemap file names', function (string $name, array $expected) {
    expect(app(SitemapBuilder::class)->parseFileName($name))->toBe($expected);
})->with([
    ['post', ['post', 1]],
    ['post-2', ['post', 2]],
    ['page-15', ['page', 15]],
    ['recipe', ['recipe', 1]],
]);

it('builds file names', function () {
    $builder = app(SitemapBuilder::class);

    expect($builder->fileName('post'))->toBe('sitemap-post.xml')
        ->and($builder->fileName('post', 1))->toBe('sitemap-post.xml')
        ->and($builder->fileName('page', 3))->toBe('sitemap-page-3.xml');
});

it('returns null for unknown types and bad pages', function () {
    $builder = app(SitemapBuilder::class);

    expect($builder->forType('recipe'))->toBeNull()
        ->and($builder->forType('post', 0))->toBeNull();
});

it('returns an empty urlset for a type without entries', function () {
    expect(app(SitemapBuilder::class)->forType('post'))->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"');
});

it('escapes special characters in urls', function () {
    Entry::factory()->published()->create(['slug' => 'fish&chips']);

    expect(app(SitemapBuilder::class)->forType('post'))->toContain('fish&amp;chips');
});

it('lists the registered content types', function () {
    expect(array_keys(app(SitemapBuilder::class)->types()))->toBe(['post', 'page']);
});

it('reads taxonomy names from sitemap file names', function () {
    $builder = app(SitemapBuilder::class);

    expect($builder->taxonomyFromFileName('taxonomy-category'))->toBe('category')
        ->and($builder->taxonomyFromFileName('taxonomy-tag-3'))->toBe('tag-3')
        ->and($builder->taxonomyFromFileName('post'))->toBeNull();
});

it('parses file names against a list of known names', function () {
    expect(app(SitemapBuilder::class)->parseFileName('tag-3', ['category', 'tag']))->toBe(['tag', 3])
        ->and(app(SitemapBuilder::class)->parseFileName('tag', ['category', 'tag']))->toBe(['tag', 1]);
});

it('lists the taxonomies with a public archive', function () {
    expect(array_keys(app(SitemapBuilder::class)->taxonomies()))->toBe(['category', 'tag']);
});

it('returns null for unknown taxonomies and bad pages', function () {
    $builder = app(SitemapBuilder::class);

    expect($builder->forTaxonomy('genre'))->toBeNull()
        ->and($builder->forTaxonomy('category', 0))->toBeNull()
        ->and($builder->forTaxonomy('tag'))->toContain('<urlset');
});
