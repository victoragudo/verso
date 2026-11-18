<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Content\Support\SlugGenerator;

beforeEach(function () {
    $this->slugs = app(SlugGenerator::class);
});

it('turns text into slugs', function (string $text, string $slug) {
    expect($this->slugs->slugify($text))->toBe($slug);
})->with([
    ['A Slow Weekend in Porto', 'a-slow-weekend-in-porto'],
    ['Café con leche!', 'cafe-con-leche'],
    ['  Ten   Lessons  ', 'ten-lessons'],
    ['¿¡!?', 'untitled'],
]);

it('limits the slug length', function () {
    $slugs = new SlugGenerator(maxLength: 12);

    expect($slugs->slugify('Notes from a rainy hike in the Pyrenees'))->toBe('notes-from-a');
});

it('adds a number when the slug is taken in the same type', function () {
    Entry::factory()->create(['slug' => 'summer-in-porto']);
    Entry::factory()->create(['slug' => 'summer-in-porto-2']);

    expect($this->slugs->forEntry('post', 'Summer in Porto'))->toBe('summer-in-porto-3')
        ->and($this->slugs->forEntry('page', 'Summer in Porto'))->toBe('summer-in-porto');
});

it('counts trashed entries as taken', function () {
    Entry::factory()->trashed()->create(['slug' => 'old-menu']);

    expect($this->slugs->forEntry('post', 'Old menu'))->toBe('old-menu-2');
});

it('keeps slugs unique per parent only', function () {
    $about = Entry::factory()->page()->create(['slug' => 'about']);
    $services = Entry::factory()->page()->create(['slug' => 'services']);
    Entry::factory()->page()->create(['slug' => 'team', 'parent_id' => $about->id]);

    expect($this->slugs->forEntry('page', 'Team', $about->id))->toBe('team-2')
        ->and($this->slugs->forEntry('page', 'Team', $services->id))->toBe('team')
        ->and($this->slugs->forEntry('page', 'Team'))->toBe('team');
});

it('ignores the entry being edited', function () {
    $entry = Entry::factory()->create(['slug' => 'garden-diary']);

    expect($this->slugs->forEntry('post', 'Garden diary', null, $entry->id))->toBe('garden-diary');
});

it('avoids reserved top level slugs', function () {
    expect($this->slugs->forEntry('page', 'Blog'))->toBe('blog-page')
        ->and($this->slugs->forEntry('page', 'Blog', Entry::factory()->page()->create()->id))->toBe('blog');
});

it('keeps the number inside the length limit', function () {
    $slugs = new SlugGenerator(maxLength: 10);
    Entry::factory()->create(['slug' => 'lisbon-caf']);

    expect($slugs->forEntry('post', 'Lisbon cafes'))->toBe('lisbon-c-2');
});

it('makes term slugs unique per taxonomy', function () {
    Term::factory()->create(['taxonomy' => 'category', 'slug' => 'travel']);

    expect($this->slugs->forTerm('category', 'Travel'))->toBe('travel-2')
        ->and($this->slugs->forTerm('tag', 'Travel'))->toBe('travel');
});
