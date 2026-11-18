<?php

use App\Models\User;
use App\Verso\Content\Actions\CreateTerm;
use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters;
use App\Verso\Plugins\Filters\BlockHtmlFilter;
use App\Verso\Plugins\Filters\BodyClassFilter;
use App\Verso\Plugins\Filters\EntryQueryFilter;
use App\Verso\Plugins\Filters\HeadTagsFilter;
use App\Verso\Plugins\Filters\SearchResultsFilter;
use App\Verso\Plugins\Filters\SeoMetaFilter;
use App\Verso\Plugins\View\HeadTag;
use App\Verso\Site\Seo\SeoPage;
use App\Verso\Theme\Enums\ArchiveKind;
use App\Verso\Theme\Support\ArchiveContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

beforeEach(function () {
    config(['verso.page_cache.enabled' => false]);
    $this->author = User::factory()->author()->create(['name' => 'Inês Duarte']);
});

describe('EntryQueryFilter', function () {
    it('lets a plugin hide entries from the blog archive', function () {
        Entry::factory()->published()->by($this->author)->create(['title' => 'Tram 28 at Sunrise', 'meta' => ['sponsored' => false]]);
        Entry::factory()->published()->by($this->author)->create(['title' => 'Sponsored Hotel Review', 'meta' => ['sponsored' => true]]);

        Filters::add(EntryQueryFilter::class, fn (Builder $query, ArchiveContext $context): Builder => $context->is(ArchiveKind::Archive)
            ? $query->where('title', '!=', 'Sponsored Hotel Review')
            : $query);

        $this->get('/blog')->assertOk()->assertSee('Tram 28 at Sunrise')->assertDontSee('Sponsored Hotel Review');
        $this->get('/')->assertOk()->assertSee('Sponsored Hotel Review');
    });

    it('passes the kind and the content type of the page', function () {
        $seen = [];
        Filters::add(EntryQueryFilter::class, function (Builder $query, ArchiveContext $context) use (&$seen): Builder {
            $seen[] = [$context->kind, $context->contentType?->name, $context->search];

            return $query;
        });

        $this->get('/')->assertOk();
        $this->get('/blog')->assertOk();
        $this->get('/search?q=lisbon')->assertOk();

        expect($seen)->toBe([
            [ArchiveKind::Home, 'post', null],
            [ArchiveKind::Archive, 'post', null],
            [ArchiveKind::Search, null, 'lisbon'],
        ]);
    });

    it('passes the term on taxonomy pages', function () {
        $entry = Entry::factory()->published()->by($this->author)->create(['title' => 'Pastel de Nata Trail']);
        $term = app(CreateTerm::class)->handle('category', ['name' => 'Food']);
        $entry->terms()->attach($term);
        $terms = [];

        Filters::add(EntryQueryFilter::class, function (Builder $query, ArchiveContext $context) use (&$terms): Builder {
            $terms[] = $context->term?->slug;

            return $query;
        });

        $this->get('/category/food')->assertOk()->assertSee('Pastel de Nata Trail');

        expect($terms)->toBe(['food']);
    });
});

describe('SearchResultsFilter', function () {
    it('lets a plugin change the search results', function () {
        Entry::factory()->published()->by($this->author)->create(['title' => 'Lisbon on Foot']);
        Entry::factory()->published()->by($this->author)->create(['title' => 'Lisbon by Bike']);

        Filters::add(SearchResultsFilter::class, function (LengthAwarePaginator $results, string $search): LengthAwarePaginator {
            $results->setCollection($results->getCollection()->reject(fn (Entry $entry): bool => str_contains($entry->title, 'Bike')));

            return $results;
        });

        $this->get('/search?q=lisbon')->assertOk()->assertSee('Lisbon on Foot')->assertDontSee('Lisbon by Bike');
    });
});

describe('BlockHtmlFilter', function () {
    it('lets a plugin wrap the html of one block type', function () {
        $entry = Entry::factory()->published()->by($this->author)->create([
            'title' => 'Fado Nights in Alfama',
            'slug' => 'fado-nights-in-alfama',
            'blocks' => [
                ['id' => 'b_fado01', 'type' => 'paragraph', 'attrs' => [], 'content' => 'The singer stood by the window.', 'children' => []],
                ['id' => 'b_fado02', 'type' => 'quote', 'attrs' => [], 'content' => 'Saudade is a song.', 'children' => []],
            ],
        ]);

        Filters::add(BlockHtmlFilter::class, fn (string $html, array $block, ?Entry $entry): string => $block['type'] === 'quote'
            ? '<div class="pull-quote" data-entry="'.$entry?->id.'">'.$html.'</div>'
            : $html);

        $this->get($entry->path())->assertOk()
            ->assertSee('<div class="pull-quote" data-entry="'.$entry->id.'"><blockquote', false)
            ->assertSee('<p>The singer stood by the window.</p>', false);
    });
});

describe('HeadTagsFilter', function () {
    it('prints plugin tags in the head of public pages', function () {
        Filters::add(HeadTagsFilter::class, fn (array $tags, Request $request): array => [
            ...$tags,
            HeadTag::meta(['name' => 'theme-color', 'content' => '#0f766e']),
            HeadTag::link(['rel' => 'preconnect', 'href' => 'https://fonts.example.org']),
        ]);

        $content = (string) $this->get('/')->assertOk()->getContent();

        expect($content)->toContain('<meta name="theme-color" content="#0f766e">')
            ->toContain('<link rel="preconnect" href="https://fonts.example.org">')
            ->and(strpos($content, 'theme-color'))->toBeLessThan(strpos($content, '</head>'));
    });

    it('drops values that are not head tags', function () {
        Filters::add(HeadTagsFilter::class, fn (array $tags): array => [...$tags, '<script>alert(1)</script>', HeadTag::style('body{margin:0}')]);

        expect(HeadTagsFilter::apply([], Request::create('/')))->toHaveCount(1);
    });
});

describe('BodyClassFilter', function () {
    it('gives entry pages classes from the entry and lets plugins add more', function () {
        $entry = Entry::factory()->published()->by($this->author)->create(['title' => 'Azulejo Hunting', 'slug' => 'azulejo-hunting']);

        Filters::add(BodyClassFilter::class, fn (array $classes, Request $request, ?Entry $entry): array => [...$classes, 'has-reading-bar']);

        $this->get($entry->path())->assertOk()
            ->assertSee('<body class="single type-post slug-azulejo-hunting has-reading-bar">', false);
    });

    it('names list pages and drops unsafe class names', function () {
        Filters::add(BodyClassFilter::class, fn (array $classes): array => [...$classes, 'x" onload="alert(1)', 'night-mode']);

        $this->get('/blog')->assertOk()->assertSee('<body class="archive type-post night-mode">', false);
        $this->get('/search?q=porto')->assertOk()->assertSee('<body class="search night-mode">', false);
    });
});

describe('SeoMetaFilter', function () {
    it('lets a plugin change the SEO tags of an entry', function () {
        $entry = Entry::factory()->published()->by($this->author)->create(['title' => 'Sintra in One Day', 'slug' => 'sintra-in-one-day']);

        Filters::add(SeoMetaFilter::class, fn (SeoPage $seo, ?Entry $entry): SeoPage => $entry === null ? $seo : $seo
            ->withTitle($entry->title.' | Day Trips from Lisbon')
            ->withRobots('noindex, follow'));

        $this->get($entry->path())->assertOk()
            ->assertSee('<title>Sintra in One Day | Day Trips from Lisbon</title>', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    });

    it('keeps the other values when one is changed', function () {
        $seo = new SeoPage('Porto Guide', 'Where to eat.', 'https://example.org/porto', null, ['og:title' => 'Porto Guide'], [], []);

        $changed = $seo->withDescription('Where to eat and sleep.')->withSchema([['@type' => 'WebPage']]);

        expect($changed->title)->toBe('Porto Guide')
            ->and($changed->description)->toBe('Where to eat and sleep.')
            ->and($changed->openGraph)->toBe(['og:title' => 'Porto Guide'])
            ->and($changed->schema)->toBe([['@type' => 'WebPage']])
            ->and($seo->description)->toBe('Where to eat.');
    });
});
