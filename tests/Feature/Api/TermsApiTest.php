<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;

it('lists the terms of a taxonomy with published entry counts', function () {
    $travel = Term::factory()->create(['name' => 'Travel', 'slug' => 'travel', 'description' => 'Trips near and far.']);
    Term::factory()->create(['name' => 'Food', 'slug' => 'food']);
    Term::factory()->tag()->create(['name' => 'Porto']);

    Entry::factory()->published()->count(2)->create()->each(fn (Entry $entry) => $entry->terms()->attach($travel));
    Entry::factory()->create()->terms()->attach($travel);

    $this->getJson('/api/v1/taxonomies/category/terms')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Food')
        ->assertJsonPath('data.0.entries_count', 0)
        ->assertJsonPath('data.1.name', 'Travel')
        ->assertJsonPath('data.1.entries_count', 2)
        ->assertJsonPath('data.1.description', 'Trips near and far.')
        ->assertJsonPath('data.1.url', url('/category/travel'))
        ->assertJsonPath('meta.total', 2);
});

it('filters terms by parent and paginates', function () {
    $travel = Term::factory()->create(['name' => 'Travel']);
    Term::factory()->create(['name' => 'Europe', 'parent_id' => $travel->id]);
    Term::factory()->create(['name' => 'Asia', 'parent_id' => $travel->id]);

    $this->getJson('/api/v1/taxonomies/category/terms?parent='.$travel->id.'&per_page=1')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Asia')
        ->assertJsonPath('meta.total', 2);
});

it('pages through terms with the same name without repeating any', function () {
    $ids = collect(['lisbon', 'lisbon-old-town', 'lisbon-food', 'lisbon-trams'])
        ->map(fn (string $slug) => Term::factory()->create(['name' => 'Lisbon', 'slug' => $slug])->id);

    $seen = collect([1, 2])->flatMap(fn (int $page) => $this->getJson('/api/v1/taxonomies/category/terms?per_page=2&page='.$page)
        ->assertJsonCount(2, 'data')
        ->json('data.*.id'));

    expect($seen->all())->toBe($ids->all());
});

it('validates per_page', function () {
    $this->getJson('/api/v1/taxonomies/category/terms?per_page=5000')->assertJsonValidationErrors('per_page');
});

it('shows one term by slug', function () {
    $porto = Term::factory()->tag()->create(['name' => 'Porto', 'slug' => 'porto']);
    Entry::factory()->published()->create()->terms()->attach($porto);

    $this->getJson('/api/v1/taxonomies/tag/terms/porto')
        ->assertOk()
        ->assertJsonPath('data.name', 'Porto')
        ->assertJsonPath('data.taxonomy', 'tag')
        ->assertJsonPath('data.entries_count', 1);
});

it('returns 404 for unknown taxonomies and terms', function () {
    Term::factory()->create(['slug' => 'travel']);

    $this->getJson('/api/v1/taxonomies/season/terms')->assertNotFound();
    $this->getJson('/api/v1/taxonomies/tag/terms/travel')->assertNotFound();
});
