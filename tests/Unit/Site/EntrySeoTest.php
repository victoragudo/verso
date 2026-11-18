<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Site\Seo\EntrySeo;

it('reads the seo fields from the entry meta', function () {
    $entry = new Entry(['meta' => ['seo' => [
        'title' => 'Sourdough at Home: A Weekend Guide',
        'description' => 'Starter, folds and baking times for a crusty loaf.',
        'canonical' => 'https://bakery.example.org/sourdough',
        'noindex' => true,
        'og_image' => '/img/media/sourdough.jpg',
    ]]]);

    $seo = EntrySeo::fromEntry($entry);

    expect($seo->title)->toBe('Sourdough at Home: A Weekend Guide')
        ->and($seo->description)->toBe('Starter, folds and baking times for a crusty loaf.')
        ->and($seo->canonical)->toBe('https://bakery.example.org/sourdough')
        ->and($seo->noindex)->toBeTrue()
        ->and($seo->ogImage)->toBe('/img/media/sourdough.jpg');
});

it('returns empty values when the entry has no seo meta', function () {
    $seo = EntrySeo::fromEntry(new Entry(['meta' => null]));

    expect($seo->toArray())->toBe(['title' => null, 'description' => null, 'canonical' => null, 'noindex' => false, 'og_image' => null, 'sitemap_exclude' => false, 'focus_keyphrase' => null, 'nofollow' => false, 'og_title' => null, 'og_description' => null, 'score' => null]);
});

it('ignores a seo meta that is not an array', function () {
    expect(EntrySeo::fromEntry(new Entry(['meta' => ['seo' => 'Best paella in Valencia']]))->title)->toBeNull();
});

it('cleans text fields', function () {
    $seo = EntrySeo::fromArray([
        'title' => "  Cooking <b>Paella</b>\n for   Twelve  ",
        'description' => '   ',
    ]);

    expect($seo->title)->toBe('Cooking Paella for Twelve')
        ->and($seo->description)->toBeNull();
});

it('cuts long text to the configured length', function () {
    config(['verso.seo.title_max_length' => 20]);

    expect(EntrySeo::fromArray(['title' => 'Notes from a Rainy Hike in the Pyrenees'])->title)->toBe('Notes from a Rainy H');
});

it('drops unsafe links', function (mixed $link) {
    $seo = EntrySeo::fromArray(['canonical' => $link, 'og_image' => $link]);

    expect($seo->canonical)->toBeNull()->and($seo->ogImage)->toBeNull();
})->with([
    'javascript' => 'javascript:alert(1)',
    'protocol relative' => '//evil.example.com/page',
    'data' => 'data:text/html;base64,PHNjcmlwdD4=',
    'not a string' => 42,
    'spaces' => 'https://example.org/a page',
]);

it('keeps http, https and local links', function (string $link) {
    expect(EntrySeo::fromArray(['canonical' => $link])->canonical)->toBe($link);
})->with(['https://garden.example.org/tomatoes', 'http://garden.example.org/tomatoes', '/blog/why-our-garden-loves-tomatoes']);

it('reads noindex from strings and booleans', function (mixed $value, bool $expected) {
    expect(EntrySeo::fromArray(['noindex' => $value])->noindex)->toBe($expected);
})->with([
    [true, true], ['1', true], ['true', true], [false, false], ['0', false], [null, false],
]);
