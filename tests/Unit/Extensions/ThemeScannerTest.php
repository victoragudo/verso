<?php

use App\Verso\Extensions\Verification\Scanners\ThemeScanner;
use App\Verso\Extensions\Verification\ScanResult;

beforeEach(function () {
    config(['app.url' => 'https://harbor-journal.test']);
    $this->scanner = new ThemeScanner(['fonts.googleapis.com', 'fonts.gstatic.com', 'use.typekit.net']);

    // Scan one view and return [rule, severity, line] for each finding.
    $this->blade = function (string $code, array $declared = []): array {
        $result = new ScanResult;
        $this->scanner->scanBlade('views/home.blade.php', $code, $result, $declared);

        return array_map(fn ($finding): array => [$finding->rule, $finding->severity->value, $finding->line], $result->findings);
    };
});

it('reports raw output of request and visitor data as high risk', function (string $expression) {
    expect(($this->blade)("<p>\n{!! {$expression} !!}\n</p>"))->toBe([['theme.raw-user-output', 'high', 2]]);
})->with([
    'request helper' => "request('q')",
    'request object' => '$request->input("q")',
    'superglobal' => '$_GET["search"]',
    'old input' => "old('email')",
    'comment body' => '$comment->body',
    'signed in user' => 'auth()->user()->name',
]);

it('reports other raw output as information only', function () {
    expect(($this->blade)("<div class=\"prose\">{!! \$content !!}</div>\n<style>{!! \$themeStyles !!}</style>"))->toBe([
        ['theme.raw-output', 'info', 1],
        ['theme.raw-output', 'info', 2],
    ]);
});

it('reports inline event handlers', function () {
    expect(($this->blade)("<a href=\"#\"\n   onclick=\"openMenu()\">Menu</a>"))->toBe([['theme.inline-handler', 'medium', 1]]);
});

it('does not mistake Blade arrows inside a tag for the end of the tag', function () {
    $code = '<img src="{{ $logo->url() }}" alt="" height="32" @if ($logo->width && $logo->height) width="{{ $logo->width }}" @endif>';

    expect(($this->blade)($code))->toBe([]);
});

it('reports images without width and height', function () {
    expect(($this->blade)("<figure>\n<img src=\"/media/pier.jpg\" alt=\"The old pier\" width=\"800\">\n</figure>"))->toBe([['theme.image-size', 'low', 2]]);
});

it('reports undeclared external scripts and scripts that block rendering', function () {
    expect(($this->blade)('<script src="https://cdn.ads-tracker.example/collect.js"></script>'))->toBe([
        ['theme.external-script', 'medium', 1],
        ['theme.render-blocking-script', 'low', 1],
    ]);
});

it('accepts scripts from declared hosts and their subdomains when they are deferred', function () {
    $code = "<script src=\"https://cdn.jsdelivr.net/npm/lite-youtube-embed@0.3.3/src/lite-yt-embed.js\" defer></script>\n<script type=\"module\" src=\"https://static.mapbox.com/map.js\"></script>";

    expect(($this->blade)($code, ['cdn.jsdelivr.net', 'mapbox.com']))->toBe([]);
});

it('reports undeclared external stylesheets and remote fonts', function () {
    $code = "<link rel=\"stylesheet\" href=\"https://unpkg.com/normalize.css@8.0.1/normalize.css\">\n<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\">\n<link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=Lora\">";

    expect(($this->blade)($code))->toBe([
        ['theme.external-style', 'medium', 1],
        ['theme.remote-font', 'medium', 2],
        ['theme.remote-font', 'medium', 3],
    ]);
});

it('still reports remote fonts when the font host is declared', function () {
    expect(($this->blade)('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lora">', ['fonts.googleapis.com']))
        ->toBe([['theme.remote-font', 'medium', 1]]);
});

it('ignores links that do not load files, like canonical and feed links', function () {
    expect(($this->blade)('<link rel="alternate" type="application/rss+xml" href="https://harbor-journal.test/feed">'))->toBe([]);
});

it('reports forms that post to another site, but not to the site itself', function () {
    $code = "<form action=\"https://newsletter-capture.example/join\" method=\"post\"></form>\n<form action=\"https://harbor-journal.test/search\"></form>\n<form action=\"{{ url('/search') }}\"></form>";

    expect(($this->blade)($code))->toBe([['theme.external-form', 'medium', 1]]);
});

it('reports an html tag without a lang attribute', function () {
    expect(($this->blade)("<!DOCTYPE html>\n<html class=\"no-js\">"))->toBe([['theme.no-lang', 'medium', 2]])
        ->and(($this->blade)("<!DOCTYPE html>\n<html lang=\"{{ \$site['locale'] }}\">"))->toBe([]);
});

it('reports imports, fonts and images from other hosts in stylesheets, once per line', function () {
    $result = new ScanResult;
    $css = "@import url('https://fonts.googleapis.com/css2?family=Lora');\n@font-face { src: url(https://use.typekit.net/af/lora.woff2); }\nbody { background: url('/assets/sand.webp'); }\n.hero { background: url(//images.stock-photos.example/pier.jpg); }";

    $this->scanner->scanStylesheet('assets/theme.css', $css, $result, []);

    expect(array_map(fn ($finding): array => [$finding->rule, $finding->line], $result->findings))->toBe([
        ['theme.remote-font', 1],
        ['theme.remote-font', 2],
        ['theme.external-style', 4],
    ])->and(array_keys($result->hosts))->toBe(['fonts.googleapis.com', 'use.typekit.net', 'images.stock-photos.example']);
});

it('finds the quality markers of a layout', function () {
    $layout = "<html lang=\"en\">\n<head><x-verso-seo /><x-verso-head /></head>\n<body><a class=\"skip-link\" href=\"#content\">Skip to content</a>\n<x-verso-footer />\n</body></html>";

    expect($this->scanner->markers($layout))->toBe(['seo' => true, 'head' => true, 'footer' => true, 'skipLink' => true, 'lang' => true])
        ->and($this->scanner->markers('<html><body>{{-- <x-verso-seo /> --}}<a href="#main">Jump</a></body></html>'))
        ->toBe(['seo' => false, 'head' => false, 'footer' => false, 'skipLink' => false, 'lang' => false]);
});

it('accepts a skip link found by its text', function () {
    expect($this->scanner->markers('<a href="#main-content">Skip to the stories</a>')['skipLink'])->toBeTrue();
});
