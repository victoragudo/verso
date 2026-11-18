<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Models\MenuItem;

it('links to a published entry with its title', function () {
    $entry = Entry::factory()->page()->published()->create(['title' => 'About the club', 'slug' => 'about']);
    $item = MenuItem::factory()->forEntry($entry)->create();

    expect($item->type)->toBe(MenuItemType::Entry)
        ->and($item->resolvedLabel())->toBe('About the club')
        ->and($item->resolvedUrl())->toBe(url('/about'))
        ->and($item->isVisible())->toBeTrue();
});

it('hides entries that are not published', function () {
    $item = MenuItem::factory()->forEntry(Entry::factory()->page()->create())->create();

    expect($item->resolvedUrl())->toBeNull()->and($item->isVisible())->toBeFalse();
});

it('hides entries in the trash', function () {
    $entry = Entry::factory()->page()->published()->create();
    $item = MenuItem::factory()->forEntry($entry)->create();
    $entry->moveToTrash();

    expect($item->fresh()->isVisible())->toBeFalse()
        ->and($item->fresh()->resolvedLabel())->toBe('');
});

it('links to a term with its name', function () {
    $term = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel']);
    $item = MenuItem::factory()->forTerm($term)->create();

    expect($item->resolvedLabel())->toBe('Travel')
        ->and($item->resolvedUrl())->toBe(url('/category/travel'));
});

it('prefers the custom label', function () {
    $term = Term::factory()->create(['name' => 'Travel']);
    $item = MenuItem::factory()->forTerm($term)->create(['label' => '  Trips  ']);

    expect($item->resolvedLabel())->toBe('Trips');
});

it('turns local custom links into full urls', function () {
    $item = MenuItem::factory()->create(['label' => 'Contact', 'url' => '/contact']);

    expect($item->resolvedUrl())->toBe(url('/contact'));
});

it('keeps external, mail and anchor links as they are', function (string $url) {
    expect(MenuItem::factory()->create(['url' => $url])->resolvedUrl())->toBe($url);
})->with(['https://market.example.org', 'mailto:hello@porto-cycling.example.org', 'tel:+351220000000', '#newsletter']);

it('hides custom links without a url', function () {
    expect(MenuItem::factory()->create(['url' => ' '])->isVisible())->toBeFalse();
});

it('uses the url as label when a custom link has no label', function () {
    expect(MenuItem::factory()->create(['label' => null, 'url' => 'https://market.example.org'])->resolvedLabel())->toBe('https://market.example.org');
});
