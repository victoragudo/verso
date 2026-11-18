<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;

it('lets editors and admins do everything with entries', function (string $role) {
    $user = User::factory()->{$role}()->create();
    $entry = Entry::factory()->published()->create();

    expect($user->can('viewAny', Entry::class))->toBeTrue()
        ->and($user->can('create', Entry::class))->toBeTrue()
        ->and($user->can('update', $entry))->toBeTrue()
        ->and($user->can('publish', $entry))->toBeTrue()
        ->and($user->can('delete', $entry))->toBeTrue()
        ->and($user->can('restore', $entry))->toBeTrue();
})->with(['admin', 'editor']);

it('lets authors edit, publish and trash only their own entries', function () {
    $author = User::factory()->author()->create();
    $own = Entry::factory()->by($author)->published()->create();
    $other = Entry::factory()->create();

    expect($author->can('update', $own))->toBeTrue()
        ->and($author->can('publish', $own))->toBeTrue()
        ->and($author->can('delete', $own))->toBeTrue()
        ->and($author->can('update', $other))->toBeFalse()
        ->and($author->can('publish', $other))->toBeFalse()
        ->and($author->can('delete', $other))->toBeFalse();
});

it('lets contributors edit only their own drafts', function () {
    $contributor = User::factory()->contributor()->create();
    $draft = Entry::factory()->by($contributor)->create();
    $live = Entry::factory()->by($contributor)->published()->create();

    expect($contributor->can('create', Entry::class))->toBeTrue()
        ->and($contributor->can('update', $draft))->toBeTrue()
        ->and($contributor->can('update', $live))->toBeFalse()
        ->and($contributor->can('publish', $draft))->toBeFalse()
        ->and($contributor->can('publish'))->toBeFalse()
        ->and($contributor->can('delete', $draft))->toBeFalse();
});

it('keeps subscribers out of entries', function () {
    $subscriber = User::factory()->create();

    expect($subscriber->can('viewAny', Entry::class))->toBeFalse()
        ->and($subscriber->can('create', Entry::class))->toBeFalse()
        ->and($subscriber->can('update', Entry::factory()->by($subscriber)->create()))->toBeFalse();
});

it('lets only term managers change terms', function () {
    $term = Term::factory()->create();

    expect(User::factory()->editor()->create()->can('update', $term))->toBeTrue()
        ->and(User::factory()->admin()->create()->can('create', Term::class))->toBeTrue()
        ->and(User::factory()->author()->create()->can('viewAny', Term::class))->toBeFalse()
        ->and(User::factory()->author()->create()->can('delete', $term))->toBeFalse();
});
