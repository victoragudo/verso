<?php

use App\Verso\Content\Actions\CreateTerm;
use App\Verso\Content\Actions\DeleteTerm;
use App\Verso\Content\Actions\UpdateTerm;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Core\Models\Redirect;

it('creates a term with a unique slug', function () {
    Term::factory()->create(['slug' => 'travel']);

    $term = app(CreateTerm::class)->handle('category', ['name' => 'Travel', 'description' => 'Trips and city guides.']);

    expect($term->slug)->toBe('travel-2')
        ->and($term->taxonomy)->toBe('category')
        ->and($term->description)->toBe('Trips and city guides.');
});

it('creates a child category', function () {
    $travel = Term::factory()->create();

    $europe = app(CreateTerm::class)->handle('category', ['name' => 'Europe', 'parent_id' => $travel->id]);

    expect($europe->parent_id)->toBe($travel->id);
});

it('refuses parents for tags', function () {
    $porto = Term::factory()->tag()->create();

    app(CreateTerm::class)->handle('tag', ['name' => 'Ribeira', 'parent_id' => $porto->id]);
})->throws(InvalidArgumentException::class);

it('refuses unknown taxonomies', function () {
    app(CreateTerm::class)->handle('season', ['name' => 'Autumn']);
})->throws(InvalidArgumentException::class);

it('updates a term and redirects the old URL', function () {
    $term = Term::factory()->create(['name' => 'Food', 'slug' => 'food']);

    app(UpdateTerm::class)->handle($term, ['name' => 'Food and Drink', 'slug' => 'food-and-drink']);

    expect($term->fresh()->name)->toBe('Food and Drink')
        ->and(Redirect::findForPath('/category/food')->to_path)->toBe('/category/food-and-drink');
});

it('keeps the slug when it is not sent', function () {
    $term = Term::factory()->create(['name' => 'Books', 'slug' => 'books']);

    app(UpdateTerm::class)->handle($term, ['name' => 'Good Books']);

    expect($term->slug)->toBe('books')
        ->and(Redirect::count())->toBe(0);
});

it('refuses a parent loop on update', function () {
    $travel = Term::factory()->create();
    $europe = Term::factory()->create(['parent_id' => $travel->id]);

    app(UpdateTerm::class)->handle($travel, ['parent_id' => $europe->id]);
})->throws(InvalidArgumentException::class);

it('deletes a term, moves children up and detaches entries', function () {
    $travel = Term::factory()->create();
    $europe = Term::factory()->create(['parent_id' => $travel->id]);
    $portugal = Term::factory()->create(['parent_id' => $europe->id]);
    $entry = Entry::factory()->create();
    $entry->terms()->attach($europe);

    app(DeleteTerm::class)->handle($europe);

    expect(Term::find($europe->id))->toBeNull()
        ->and($portugal->fresh()->parent_id)->toBe($travel->id)
        ->and($entry->terms()->count())->toBe(0);
});
