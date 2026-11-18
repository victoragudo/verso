<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Support\LeadImage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

// Parses a public page so the checks below can walk its tags.
function parsePage(string $html): DOMXPath
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($document);
}

// Public pages of the demo site that the checklist runs on.
function checklistPages(): array
{
    return [
        'home' => '/',
        'entry' => Entry::firstWhere('slug', 'a-slow-weekend-in-porto')->path(),
        'page' => '/about/our-team',
        'archive' => '/blog',
        'category' => '/category/travel',
        'search' => '/search?q=paella',
    ];
}

beforeEach(function () {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
});

it('has no render blocking stylesheets or scripts in the head', function () {
    foreach (checklistPages() as $name => $path) {
        $page = parsePage($this->get($path)->assertOk()->getContent());

        expect($page->query('//head/link[@rel="stylesheet"]')->length)->toBe(0, "{$name} links a stylesheet")
            ->and($page->query('//head/script[@src and not(@async) and not(@defer) and not(@type="module")]')->length)->toBe(0, "{$name} has a blocking script");
    }
});

it('inlines the theme CSS in the head', function () {
    $page = parsePage($this->get('/')->getContent());
    $styles = $page->query('//head/style');

    expect($styles->length)->toBe(1)
        ->and(strlen($styles->item(0)->textContent))->toBeGreaterThan(1000)
        ->and($styles->item(0)->textContent)->toContain('.site-header');
});

it('loads no external scripts and keeps inline scripts small', function () {
    foreach (checklistPages() as $name => $path) {
        $page = parsePage($this->get($path)->getContent());
        $inlineBytes = 0;

        foreach ($page->query('//script[not(@type="application/ld+json")]') as $script) {
            expect($script->getAttribute('src'))->toBe('', "{$name} loads an external script");
            $inlineBytes += strlen($script->textContent);
        }

        expect($inlineBytes)->toBeLessThan(4096, "{$name} has {$inlineBytes} bytes of JavaScript");
    }
});

it('loads only fonts from the theme folder, with swap, and preloads the main one', function () {
    foreach (checklistPages() as $name => $path) {
        $html = $this->get($path)->getContent();
        $page = parsePage($html);
        $preloads = $page->query('//link[@rel="preload" and @as="font"]');

        preg_match_all('/@font-face\s*{[^}]*}/', $html, $faces);
        preg_match_all('/url\("([^"]+)"\)/', implode('', $faces[0]), $urls);

        expect($preloads->length)->toBe(1, "{$name} preloads one font")
            ->and($faces[0])->not->toBeEmpty();

        foreach ($faces[0] as $face) {
            expect(str_contains($face, 'font-display: swap'))->toBeTrue("{$name} has a font without swap");
        }

        foreach ([...$urls[1], $preloads->item(0)->getAttribute('href')] as $url) {
            expect(str_starts_with($url, url('/theme-assets/')))->toBeTrue("{$name} loads {$url}");
        }

        expect($preloads->item(0)->hasAttribute('crossorigin'))->toBeTrue();
    }
});

it('needs no web font downloads with the default theme', function () {
    app(Settings::class)->set('theme.active', 'default');

    foreach (checklistPages() as $name => $path) {
        expect(str_contains($this->get($path)->getContent(), '@font-face'))->toBeFalse("{$name} downloads a web font");
    }
});

it('gives every image a width, a height and a loading mode', function () {
    foreach (checklistPages() as $name => $path) {
        $page = parsePage($this->get($path)->getContent());

        foreach ($page->query('//img') as $image) {
            $src = $image->getAttribute('src');

            expect($image->getAttribute('width'))->not->toBe('', "{$name}: {$src} has no width")
                ->and($image->getAttribute('height'))->not->toBe('', "{$name}: {$src} has no height")
                ->and($image->getAttribute('loading'))->toBeIn(['lazy', 'eager'])
                ->and($image->getAttribute('decoding'))->toBe('async');
        }
    }
});

it('loads the first image right away with a high priority and the rest lazily', function (string $name) {
    $path = checklistPages()[$name];
    $images = iterator_to_array(parsePage($this->get($path)->getContent())->query('//main//img'));

    expect($images)->not->toBeEmpty();
    expect($images[0]->getAttribute('fetchpriority'))->toBe('high')
        ->and($images[0]->getAttribute('loading'))->toBe('eager');

    foreach (array_slice($images, 1) as $image) {
        expect($image->getAttribute('loading'))->toBe('lazy')
            ->and($image->getAttribute('fetchpriority'))->not->toBe('high');
    }
})->with(['home', 'entry', 'archive', 'category']);

it('promotes the first content image when the entry has no featured image above it', function () {
    $entry = Entry::firstWhere('slug', 'a-slow-weekend-in-porto');
    $photo = $entry->meta['featured_image']['mediaId'];
    $entry->forceFill([
        'meta' => [...$entry->meta, 'featured_image' => null],
        'blocks' => [
            ['id' => 'b_lead01', 'type' => 'image', 'attrs' => ['mediaId' => $photo, 'alt' => 'Tiled houses by the river'], 'content' => '', 'children' => []],
            ['id' => 'b_par001', 'type' => 'paragraph', 'attrs' => [], 'content' => '<p>We took the old tram up the hill.</p>', 'children' => []],
            ['id' => 'b_img002', 'type' => 'image', 'attrs' => ['mediaId' => $photo, 'alt' => 'Sunset over the bridge'], 'content' => '', 'children' => []],
        ],
    ])->save();

    $images = iterator_to_array(parsePage($this->get($entry->path())->getContent())->query('//*[contains(concat(" ", @class, " "), " prose ")]//img'));

    expect($images)->toHaveCount(2)
        ->and($images[0]->getAttribute('fetchpriority'))->toBe('high')
        ->and($images[0]->getAttribute('loading'))->toBe('eager')
        ->and($images[1]->getAttribute('loading'))->toBe('lazy');
});

describe('lead image helper', function () {
    it('changes only the first image', function () {
        $html = '<p>Intro</p><img src="/img/a.jpg" alt="Harbour" loading="lazy"><img src="/img/b.jpg" alt="Market" loading="lazy">';

        expect(app(LeadImage::class)->promote($html))
            ->toBe('<p>Intro</p><img src="/img/a.jpg" alt="Harbour" loading="eager" fetchpriority="high"><img src="/img/b.jpg" alt="Market" loading="lazy">');
    });

    it('leaves HTML without images alone', function () {
        expect(app(LeadImage::class)->promote('<p>Only words about the coast.</p>'))->toBe('<p>Only words about the coast.</p>');
    });

    it('keeps an existing fetchpriority', function () {
        $html = '<img src="/img/a.jpg" alt="" loading="lazy" fetchpriority="low">';

        expect(app(LeadImage::class)->promote($html))->toBe('<img src="/img/a.jpg" alt="" loading="eager" fetchpriority="low">');
    });
});

it('gives browsers without font-size-adjust local fallback fonts sized like the theme fonts', function () {
    $html = $this->get('/')->getContent();
    $start = (int) strpos($html, '@supports not (font-size-adjust: from-font)');
    $fallbacks = substr($html, $start, (int) strpos($html, '}'."\n".'}', $start) - $start);

    expect($start)->toBeGreaterThan(0)
        ->and($fallbacks)->toContain('font-family: "Newsreader Fallback"; src: local("Georgia")')
        ->toContain('font-family: "Instrument Sans Fallback"; src: local("Arial")')
        ->toMatch('/size-adjust: \d+(\.\d+)?%; ascent-override: \d+(\.\d+)?%; descent-override: \d+(\.\d+)?%/')
        ->and($html)->toContain("Newsreader, 'Newsreader Fallback', Charter")
        ->toContain("'Instrument Sans', 'Instrument Sans Fallback', system-ui")
        ->toContain('font-size-adjust: from-font');
});

it('lays out long lists far down the page only when they come near the screen', function (string $theme, string $selector) {
    app(Settings::class)->set('theme.active', $theme);
    $css = parsePage($this->get('/')->getContent())->query('//head/style')->item(0)->textContent;
    $start = (int) strpos($css, $selector);
    $rule = substr($css, $start, (int) strpos($css, '}', $start) - $start);

    expect($start)->toBeGreaterThan(0)
        ->and($rule)->toContain('content-visibility: auto;')
        ->toMatch('/contain-intrinsic-size: auto none auto \d+rem;/')
        ->toContain('overflow-clip-margin:');
})->with([
    'default cards' => ['default', '.entry-card:nth-child(n + 4)'],
    'ashgrove cards' => ['ashgrove', '.post-grid > li:nth-child(n + 7)'],
]);
