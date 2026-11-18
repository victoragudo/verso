<?php

use App\Verso\Site\Permalinks\PermalinkPattern;

it('normalizes slashes and spaces', function (string $raw, string $normalized) {
    expect((new PermalinkPattern($raw))->pattern)->toBe($normalized);
})->with([
    ['blog/{slug}', '/blog/{slug}'],
    [' /blog//{slug}/ ', '/blog/{slug}'],
    ['/', '/'],
    ['/{year}/{month}/{slug}/', '/{year}/{month}/{slug}'],
]);

it('lists tokens and unknown tokens', function () {
    $pattern = new PermalinkPattern('/stories/{year}/{author}/{slug}');

    expect($pattern->tokens())->toBe(['year', 'author', 'slug'])
        ->and($pattern->unknownTokens())->toBe(['author'])
        ->and($pattern->has('year'))->toBeTrue()
        ->and($pattern->has('day'))->toBeFalse();
});

it('finds the fixed start of a pattern', function (string $raw, string $prefix, ?string $first) {
    $pattern = new PermalinkPattern($raw);

    expect($pattern->staticPrefix())->toBe($prefix)
        ->and($pattern->firstStaticSegment())->toBe($first);
})->with([
    ['/blog/{slug}', 'blog', 'blog'],
    ['/articles/news/{year}/{slug}', 'articles/news', 'articles'],
    ['/{year}/{month}/{slug}', '', null],
    ['/archives/{id}', 'archives', 'archives'],
]);

it('builds paths from token values', function (string $raw, string $path) {
    $values = ['slug' => 'lisbon-tram-28', 'id' => '418', 'year' => '2026', 'month' => '03', 'day' => '09', 'category' => 'travel'];

    expect((new PermalinkPattern($raw))->build($values))->toBe($path);
})->with([
    ['/blog/{slug}', '/blog/lisbon-tram-28'],
    ['/{slug}', '/lisbon-tram-28'],
    ['/{year}/{month}/{slug}', '/2026/03/lisbon-tram-28'],
    ['/{year}/{month}/{day}/{slug}', '/2026/03/09/lisbon-tram-28'],
    ['/{category}/{slug}', '/travel/lisbon-tram-28'],
    ['/archives/{id}', '/archives/418'],
    ['/articles/{year}/{slug}', '/articles/2026/lisbon-tram-28'],
    ['/notes/{slug}.html', '/notes/lisbon-tram-28.html'],
]);

it('matches paths back to token values', function (string $raw, string $path, ?array $values) {
    expect((new PermalinkPattern($raw))->match($path))->toBe($values);
})->with([
    ['/blog/{slug}', 'blog/lisbon-tram-28', ['slug' => 'lisbon-tram-28']],
    ['/blog/{slug}', '/blog/lisbon-tram-28/', ['slug' => 'lisbon-tram-28']],
    ['/blog/{slug}', 'news/lisbon-tram-28', null],
    ['/{year}/{month}/{slug}', '2026/03/lisbon-tram-28', ['year' => '2026', 'month' => '03', 'slug' => 'lisbon-tram-28']],
    ['/{year}/{month}/{slug}', '26/03/lisbon-tram-28', null],
    ['/{year}/{month}/{day}/{slug}', '2026/03/09/lisbon-tram-28', ['year' => '2026', 'month' => '03', 'day' => '09', 'slug' => 'lisbon-tram-28']],
    ['/archives/{id}', 'archives/418', ['id' => '418']],
    ['/archives/{id}', 'archives/lisbon', null],
    ['/{slug}', 'about/team', null],
    ['/notes/{slug}.html', 'notes/lisbon-tram-28.html', ['slug' => 'lisbon-tram-28']],
    ['/notes/{slug}.html', 'notes/lisbon-tram-28xhtml', null],
]);

it('lets {slug} hold a nested path for hierarchical types', function () {
    expect((new PermalinkPattern('/{slug}'))->match('about/team/lisbon', true))->toBe(['slug' => 'about/team/lisbon'])
        ->and((new PermalinkPattern('/guides/{slug}'))->match('guides/porto/food', true))->toBe(['slug' => 'porto/food']);
});

it('ranks patterns with more fixed parts first', function () {
    expect((new PermalinkPattern('/blog/{slug}'))->specificity())->toBeGreaterThan((new PermalinkPattern('/{year}/{month}/{slug}'))->specificity())
        ->and((new PermalinkPattern('/{year}/{month}/{slug}'))->specificity())->toBeGreaterThan((new PermalinkPattern('/{slug}'))->specificity());
});
