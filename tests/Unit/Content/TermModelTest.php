<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;

it('builds public paths from the taxonomy', function () {
    $travel = Term::factory()->create(['slug' => 'travel']);
    $porto = Term::factory()->tag()->create(['slug' => 'porto']);

    expect($travel->path())->toBe('/category/travel')
        ->and($porto->path())->toBe('/tag/porto')
        ->and($porto->url())->toBe(url('/tag/porto'))
        ->and($travel->taxonomyDefinition()->label)->toBe('Category');
});

it('filters by taxonomy', function () {
    Term::factory()->count(2)->create();
    Term::factory()->tag()->create();

    expect(Term::inTaxonomy('tag')->count())->toBe(1);
});

it('has parents, children and entries', function () {
    $travel = Term::factory()->create(['name' => 'Travel']);
    $europe = Term::factory()->create(['name' => 'Europe', 'parent_id' => $travel->id]);
    $entry = Entry::factory()->create();
    $entry->terms()->attach($europe);

    expect($europe->parent->is($travel))->toBeTrue()
        ->and($travel->children->pluck('name')->all())->toBe(['Europe'])
        ->and($europe->entries->first()->is($entry))->toBeTrue();
});

it('checks which parents are allowed', function () {
    $travel = Term::factory()->create();
    $europe = Term::factory()->create(['parent_id' => $travel->id]);
    $tag = Term::factory()->tag()->create();
    $otherTag = Term::factory()->tag()->create();

    expect($europe->canHaveParent(null))->toBeTrue()
        ->and($europe->canHaveParent($travel))->toBeTrue()
        ->and($travel->canHaveParent($europe))->toBeFalse()
        ->and($travel->canHaveParent($travel))->toBeFalse()
        ->and($europe->canHaveParent($tag))->toBeFalse()
        ->and($tag->canHaveParent($otherTag))->toBeFalse();
});
