<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Site\Seo\SeoBuilder;

beforeEach(function () {
    app(Settings::class)->setMany([
        'site.name' => 'Lisbon Food Journal',
        'site.tagline' => 'Recipes and markets from the Portuguese coast',
        'site.url' => 'https://lisbonfood.example.org',
        'site.locale' => 'pt',
    ]);
    $this->builder = app(SeoBuilder::class);
});

function schemaNode(array $schema, string $type): ?array
{
    return collect($schema)->firstWhere('@type', $type);
}

it('builds an article for a post', function () {
    $author = User::factory()->create(['name' => 'Inês Duarte']);
    $post = Entry::factory()->published()->by($author)->create([
        'title' => 'The Best Coffee Shops in Lisbon',
        'slug' => 'best-coffee-lisbon',
        'excerpt' => 'Seven places for a strong bica and a pastel de nata.',
    ]);

    $seo = $this->builder->build($post);

    expect($seo->title)->toBe('The Best Coffee Shops in Lisbon | Lisbon Food Journal')
        ->and($seo->description)->toBe('Seven places for a strong bica and a pastel de nata.')
        ->and($seo->canonical)->toBe(url('/blog/best-coffee-lisbon'))
        ->and($seo->robots)->toBeNull()
        ->and($seo->openGraph['og:type'])->toBe('article')
        ->and($seo->openGraph['og:locale'])->toBe('pt')
        ->and($seo->openGraph['og:site_name'])->toBe('Lisbon Food Journal')
        ->and($seo->openGraph['article:author'])->toBe('Inês Duarte')
        ->and($seo->openGraph)->toHaveKey('article:published_time')
        ->and($seo->twitter['twitter:card'])->toBe('summary');

    $article = schemaNode($seo->schema, 'BlogPosting');
    expect($article['headline'])->toBe('The Best Coffee Shops in Lisbon')
        ->and($article['author'])->toMatchArray(['@type' => 'Person', 'name' => 'Inês Duarte', 'url' => $author->authorUrl()])
        ->and($article['publisher']['name'])->toBe('Lisbon Food Journal')
        ->and($article['mainEntityOfPage'])->toBe(url('/blog/best-coffee-lisbon'));
});

it('builds a web page for a page', function () {
    $page = Entry::factory()->page()->published()->create(['title' => 'About us', 'slug' => 'about']);

    $seo = $this->builder->build($page);

    expect($seo->openGraph['og:type'])->toBe('website')
        ->and(schemaNode($seo->schema, 'Article'))->toBeNull()
        ->and(schemaNode($seo->schema, 'WebPage')['name'])->toBe('About us | Lisbon Food Journal');
});

it('uses the seo fields when they are set', function () {
    $post = Entry::factory()->published()->create(['meta' => ['seo' => [
        'title' => 'Lisbon coffee guide 2026',
        'description' => 'Where locals drink coffee.',
        'canonical' => 'https://coffee.example.org/lisbon',
        'og_image' => '/img/media/coffee.jpg',
        'noindex' => true,
    ]]]);

    $seo = $this->builder->build($post);

    expect($seo->title)->toBe('Lisbon coffee guide 2026')
        ->and($seo->description)->toBe('Where locals drink coffee.')
        ->and($seo->canonical)->toBe('https://coffee.example.org/lisbon')
        ->and($seo->robots)->toBe('noindex, follow')
        ->and($seo->openGraph['og:image'])->toBe(url('/img/media/coffee.jpg'))
        ->and($seo->twitter['twitter:card'])->toBe('summary_large_image')
        ->and($seo->twitter['twitter:image'])->toBe(url('/img/media/coffee.jpg'));
});

it('falls back to the tagline when there is no excerpt', function () {
    $post = Entry::factory()->published()->create(['excerpt' => null]);

    expect($this->builder->build($post)->description)->toBe('Recipes and markets from the Portuguese coast');
});

it('hides every page when the site discourages search engines', function () {
    app(Settings::class)->set('reading.discourage_search', true);

    expect($this->builder->build(Entry::factory()->published()->create())->robots)->toBe('noindex, nofollow')
        ->and($this->builder->build()->robots)->toBe('noindex, nofollow');
});

it('builds breadcrumbs with the archive and the parents', function () {
    $post = Entry::factory()->published()->create(['title' => 'Cooking Paella for Twelve People', 'slug' => 'paella']);

    $items = $this->builder->breadcrumbs($post)['itemListElement'];

    expect($items)->toHaveCount(3)
        ->and($items[0])->toMatchArray(['position' => 1, 'name' => 'Lisbon Food Journal', 'item' => 'https://lisbonfood.example.org/'])
        ->and($items[1])->toMatchArray(['position' => 2, 'name' => 'Posts', 'item' => url('/blog')])
        ->and($items[2])->toMatchArray(['position' => 3, 'name' => 'Cooking Paella for Twelve People', 'item' => url('/blog/paella')]);
});

it('adds parent pages to the breadcrumbs', function () {
    $about = Entry::factory()->page()->published()->create(['title' => 'About', 'slug' => 'about']);
    $team = Entry::factory()->page()->published()->create(['title' => 'Our team', 'slug' => 'team', 'parent_id' => $about->id]);

    $items = $this->builder->breadcrumbs($team)['itemListElement'];

    expect(array_column($items, 'name'))->toBe(['Lisbon Food Journal', 'About', 'Our team'])
        ->and($items[2]['item'])->toBe(url('/about/team'));
});

it('leaves breadcrumbs out on the front page', function () {
    $home = Entry::factory()->page()->published()->create();
    app(Settings::class)->set('reading.front_page', $home->id);

    expect(schemaNode($this->builder->build($home)->schema, 'BreadcrumbList'))->toBeNull();
});

it('builds the website node with a search action', function () {
    $site = $this->builder->webSite();

    expect($site['url'])->toBe('https://lisbonfood.example.org/')
        ->and($site['name'])->toBe('Lisbon Food Journal')
        ->and($site['inLanguage'])->toBe('pt')
        ->and($site['potentialAction']['@type'])->toBe('SearchAction')
        ->and($site['potentialAction']['target']['urlTemplate'])->toBe('https://lisbonfood.example.org/search?q={search_term_string}')
        ->and($site['potentialAction']['query-input'])->toBe('required name=search_term_string');
});

it('uses the configured search path and parameter', function () {
    config(['verso.seo.search_path' => '/find', 'verso.seo.search_param' => 's']);

    expect($this->builder->webSite()['potentialAction']['target']['urlTemplate'])->toBe('https://lisbonfood.example.org/find?s={search_term_string}');
});

it('builds a general page without an entry', function () {
    $seo = $this->builder->build();

    expect($seo->title)->toBe('Lisbon Food Journal | Recipes and markets from the Portuguese coast')
        ->and($seo->description)->toBe('Recipes and markets from the Portuguese coast')
        ->and($seo->schema)->toHaveCount(1)
        ->and($seo->schema[0]['@type'])->toBe('WebSite');
});

it('builds a general page with its own title', function () {
    expect($this->builder->build(null, 'Search results')->title)->toBe('Search results | Lisbon Food Journal');
});

it('adds the twitter handle from the config', function () {
    config(['verso.seo.twitter_site' => '@lisbonfood']);

    expect($this->builder->build()->twitter['twitter:site'])->toBe('@lisbonfood');
});

it('escapes json-ld so it cannot close the script tag', function () {
    $post = Entry::factory()->published()->create(['title' => 'Tags </script><script>alert(1)</script>']);

    $json = $this->builder->build($post)->jsonLd();

    expect($json)->not->toContain('</script>')
        ->and(json_decode($json, true)['@context'])->toBe('https://schema.org');
});
