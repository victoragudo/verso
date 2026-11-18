<?php

use App\Verso\Easy\Sections\SectionLibrary;
use App\Verso\Easy\Starters\StarterMenuItem;
use App\Verso\Easy\Starters\StarterPage;
use App\Verso\Easy\Starters\StarterSection;
use App\Verso\Easy\Starters\StarterSite;
use App\Verso\Easy\Starters\StarterSiteRegistry;

// Sections that bundled plugins register when they are on.
const EASY_PLUGIN_SECTIONS = ['shop-products', 'newsletter'];

dataset('core starters', ['blog', 'business', 'portfolio', 'restaurant', 'shop', 'event', 'personal']);

function yogaStudioStarter(int $order = 100): StarterSite
{
    return new StarterSite(
        key: 'yoga-studio',
        label: 'Yoga studio',
        description: 'Classes, teachers and a timetable.',
        icon: 'calendar',
        order: $order,
        pages: [new StarterPage('Home', 'home', [new StarterSection('hero', ['heading' => 'Breathe in, slow down'])], front: true)],
    );
}

it('registers the seven core starter sites in a fixed order', function () {
    expect(app(StarterSiteRegistry::class)->keys())->toBe(['blog', 'business', 'portfolio', 'restaurant', 'shop', 'event', 'personal']);
});

it('lets plugins add and replace starter sites', function () {
    $registry = app(StarterSiteRegistry::class);
    $registry->register(yogaStudioStarter(order: 15));

    expect($registry->keys())->toBe(['blog', 'yoga-studio', 'business', 'portfolio', 'restaurant', 'shop', 'event', 'personal'])
        ->and($registry->get('yoga-studio')->label)->toBe('Yoga studio')
        ->and($registry->find('bakery'))->toBeNull()
        ->and(fn () => $registry->get('bakery'))->toThrow(InvalidArgumentException::class);
});

it('rejects starter keys with spaces', function () {
    expect(fn () => new StarterSite('Yoga Studio', 'Yoga', 'Classes.', 'calendar', []))->toThrow(InvalidArgumentException::class);
});

it('only uses known sections, pictures and pages', function (string $key) {
    $site = app(StarterSiteRegistry::class)->get($key);
    $library = app(SectionLibrary::class);
    $slugs = array_map(fn (StarterPage $page): string => $page->slug, $site->pages);

    foreach ($site->pages as $page) {
        foreach ($page->sections as $section) {
            expect($library->has($section->key) || in_array($section->key, EASY_PLUGIN_SECTIONS, true))->toBeTrue("Unknown section {$section->key}");

            foreach ([...($section->content['images'] ?? []), ...array_column($section->content['items'] ?? [], 'image')] as $image) {
                expect($site->image($image))->not->toBeNull("Unknown picture {$image}");
            }
        }
    }

    $menuPages = array_filter(array_map(fn (StarterMenuItem $item): ?string => $item->page, [...$site->primaryMenu, ...$site->footerMenu]));

    expect(array_diff($menuPages, $slugs))->toBe([])
        ->and($site->primaryMenu)->not->toBeEmpty()
        ->and($slugs)->toHaveCount(count(array_unique($slugs)))
        ->and($site->settings['site.tagline'] ?? '')->not->toBe('');
})->with('core starters');

it('has a home page except for the blog, which keeps the latest posts', function (string $key) {
    $front = app(StarterSiteRegistry::class)->get($key)->frontPage();

    $key === 'blog' ? expect($front)->toBeNull() : expect($front?->slug)->toBe('home');
})->with('core starters');

it('turns on the right plugins', function () {
    $plugins = fn (string $key): array => app(StarterSiteRegistry::class)->get($key)->plugins;

    expect($plugins('shop'))->toContain('shop', 'forms')
        ->and($plugins('restaurant'))->toBe(['forms'])
        ->and($plugins('business'))->toBe(['forms'])
        ->and($plugins('event'))->toBe(['forms'])
        ->and($plugins('blog'))->toBe(['newsletter'])
        ->and($plugins('personal'))->toBe([]);
});

it('describes each starter for the guided start', function () {
    $restaurant = collect(app(StarterSiteRegistry::class)->toArray())->firstWhere('key', 'restaurant');

    expect($restaurant)->toMatchArray(['label' => 'Restaurant or cafe', 'icon' => 'restaurant', 'stylePreset' => 'playful', 'plugins' => ['forms']])
        ->and(array_column($restaurant['pages'], 'title'))->toBe(['Home', 'Menu', 'About', 'Visit us'])
        ->and($restaurant['pages'][0]['front'])->toBeTrue()
        ->and($restaurant['sections'])->toBe(['hero', 'about', 'gallery', 'testimonials', 'cta']);
});

it('gives every starter a style preset key for the next step', function (string $key) {
    expect(app(StarterSiteRegistry::class)->get($key)->stylePreset)->toBeIn(['calm', 'bold', 'playful', 'elegant']);
})->with('core starters');
