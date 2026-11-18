<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;

it('captures the current content of an entry', function () {
    $editor = User::factory()->editor()->create();
    $entry = Entry::factory()->create(['title' => 'Harvest week', 'excerpt' => 'Pumpkins everywhere']);

    $revision = Revision::capture($entry, $editor);

    expect($revision->title)->toBe('Harvest week')
        ->and($revision->excerpt)->toBe('Pumpkins everywhere')
        ->and($revision->blocks)->toBe($entry->blocks)
        ->and($revision->entry->is($entry))->toBeTrue()
        ->and($revision->user->is($editor))->toBeTrue();
});

it('can capture an older snapshot', function () {
    $entry = Entry::factory()->create();

    $revision = Revision::capture($entry, null, ['title' => 'First idea', 'blocks' => [], 'excerpt' => null, 'meta' => null]);

    expect($revision->title)->toBe('First idea')
        ->and($revision->user)->toBeNull();
});

it('applies its content back to an entry without saving', function () {
    $entry = Entry::factory()->create(['title' => 'New title']);
    $revision = Revision::factory()->for($entry)->create(['title' => 'Old title', 'excerpt' => 'Old excerpt']);

    $revision->applyTo($entry);

    expect($entry->title)->toBe('Old title')
        ->and($entry->excerpt)->toBe('Old excerpt')
        ->and($entry->isDirty('title'))->toBeTrue();
});
