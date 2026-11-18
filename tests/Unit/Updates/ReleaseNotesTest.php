<?php

use App\Verso\Updates\Support\ReleaseNotes;

it('renders markdown release notes', function () {
    $html = ReleaseNotes::toHtml("## What is new\n\n- **Faster** image pages\n- [Upgrade guide](https://verso-cms.example/docs/upgrade)");

    expect($html)->toContain('<h2>What is new</h2>')
        ->toContain('<strong>Faster</strong>')
        ->toContain('<a href="https://verso-cms.example/docs/upgrade">Upgrade guide</a>');
});

it('escapes raw HTML and drops unsafe links', function () {
    $html = ReleaseNotes::toHtml("<script>alert('pwned')</script>\n\n[Click me](javascript:alert(1)) <img src=x onerror=alert(2)>");

    expect($html)->not->toContain('<script>')
        ->not->toContain('<img')
        ->not->toContain('javascript:')
        ->toContain('&lt;script&gt;');
});

it('gives empty HTML for empty notes', function () {
    expect(ReleaseNotes::toHtml("  \n"))->toBe('');
});
