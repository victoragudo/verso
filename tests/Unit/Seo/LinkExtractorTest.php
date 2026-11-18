<?php

use App\Verso\Seo\Links\LinkExtractor;

beforeEach(function () {
    $this->extractor = new LinkExtractor(['harborlane.example.org', 'localhost'], 50);
});

it('splits internal paths and external links', function () {
    $found = $this->extractor->extract(
        '<p><a href="/blog/rye">Rye</a> <a href="https://harborlane.example.org/hours?x=1#map">Hours</a> '
        .'<a href="//harborlane.example.org/contact/">Contact</a> <a href="https://flour.example.org">Miller</a> '
        .'<a href="http://Other.example.com/a">Other</a> <a href="/blog/rye">Rye again</a></p>'
    );

    expect($found->internal)->toBe(['/blog/rye', '/hours', '/contact'])
        ->and($found->external)->toBe(2);
});

it('skips anchors, mail, phone, scripts and relative links', function () {
    $found = $this->extractor->extract('<a href="#top">Top</a><a href="mailto:pan@example.org">Mail</a><a href="tel:+34600">Call</a><a href="javascript:void(0)">Js</a><a href="menu.pdf">Menu</a><a>no href</a><a href="?page=2">Next</a>');

    expect($found->internal)->toBe([])->and($found->external)->toBe(0);
});

it('reads accents and encoded paths', function () {
    $found = $this->extractor->extract('<a href="/recetas/pan-de-mar%C3%ADa">Pan</a> <a href="/caf&eacute;">Café</a>');

    expect($found->internal)->toBe(['/recetas/pan-de-maría', '/café']);
});

it('stops after the most links allowed', function () {
    $html = implode('', array_map(fn (int $number): string => '<a href="/post-'.$number.'">'.$number.'</a>', range(1, 10)));

    expect((new LinkExtractor(['localhost'], 3))->extract($html)->internal)->toHaveCount(3)
        ->and($this->extractor->extract('')->internal)->toBe([]);
});
