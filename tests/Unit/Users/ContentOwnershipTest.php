<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Revision;
use App\Verso\Users\Support\ContentOwnership;

it('counts entries and revisions of a user', function () {
    $carmen = User::factory()->author()->create(['name' => 'Carmen Ortiz']);
    $entry = Entry::factory()->by($carmen)->create();
    Entry::factory()->by($carmen)->trashed()->create();
    Revision::factory()->create(['entry_id' => $entry->id, 'user_id' => $carmen->id]);
    Entry::factory()->create();

    expect(app(ContentOwnership::class)->count($carmen))->toBe(3)
        ->and(app(ContentOwnership::class)->hasContent($carmen))->toBeTrue();
});

it('says a new user has no content', function () {
    $reader = User::factory()->create();

    expect(app(ContentOwnership::class)->hasContent($reader))->toBeFalse();
});

it('moves all content to another user', function () {
    $from = User::factory()->author()->create(['name' => 'Pedro Alves']);
    $to = User::factory()->editor()->create(['name' => 'Laura Gomez']);
    $entries = Entry::factory()->count(2)->by($from)->create();
    Revision::factory()->create(['entry_id' => $entries[0]->id, 'user_id' => $from->id]);
    $other = Entry::factory()->create();

    $moved = app(ContentOwnership::class)->reassign($from, $to);

    expect($moved)->toBe(3)
        ->and(Entry::query()->where('author_id', $to->id)->count())->toBe(2)
        ->and(Revision::query()->where('user_id', $to->id)->count())->toBe(1)
        ->and($other->fresh()->author_id)->not->toBe($to->id);
});

it('skips tables and columns that do not exist', function () {
    $ownership = new ContentOwnership([
        'entries' => 'author_id',
        'podcasts' => 'host_id',
        'terms' => 'owner_id',
    ]);
    $user = User::factory()->author()->create();
    Entry::factory()->by($user)->create();

    expect($ownership->count($user))->toBe(1)
        ->and($ownership->reassign($user, User::factory()->create()))->toBe(1);
});
