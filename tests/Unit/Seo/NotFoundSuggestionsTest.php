<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Seo\NotFound\NotFoundSuggestions;

it('rates how alike two paths are', function () {
    $suggestions = new NotFoundSuggestions(3, 100, 45);

    expect($suggestions->similarity('/blog/riding-tram28', '/blog/riding-tram-28'))->toBeGreaterThan(90)
        ->and($suggestions->similarity('/recetas/pan-de-maiz.html', '/recetas/pan-de-maíz'))->toBe(100)
        ->and($suggestions->similarity('/contact', '/blog/riding-tram-28'))->toBeLessThan(45)
        ->and($suggestions->similarity('', '/about'))->toBe(0);
});

it('suggests the closest published pages, best first', function () {
    Entry::factory()->published()->create(['title' => 'Pan de masa madre', 'slug' => 'pan-de-masa-madre']);
    Entry::factory()->published()->create(['title' => 'Pan de maíz', 'slug' => 'pan-de-maiz']);
    Entry::factory()->create(['title' => 'Draft about bread', 'slug' => 'pan-de-masa']);
    Entry::factory()->published()->create(['title' => 'Opening hours', 'slug' => 'opening-hours']);

    $found = (new NotFoundSuggestions(2, 100, 45))->for('/blog/pan-masa-madre');

    expect(array_column($found, 'path'))->toBe(['/blog/pan-de-masa-madre', '/blog/pan-de-maiz'])
        ->and($found[0]['similarity'])->toBeGreaterThan($found[1]['similarity']);
});

it('suggests nothing when no page is close enough', function () {
    Entry::factory()->published()->create(['slug' => 'opening-hours']);

    expect((new NotFoundSuggestions(3, 100, 80))->forMany(['/wholesale-prices']))->toBe(['/wholesale-prices' => []]);
});
