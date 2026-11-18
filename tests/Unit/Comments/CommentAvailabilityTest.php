<?php

use App\Verso\Comments\Support\CommentAvailability;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;

it('is open on published entries by default', function () {
    $entry = Entry::factory()->published()->create();

    expect(app(CommentAvailability::class)->isOpenFor($entry))->toBeTrue();
});

it('follows the site wide setting', function () {
    $entry = Entry::factory()->published()->create();
    app(Settings::class)->set('comments.enabled', false);

    expect(app(CommentAvailability::class)->enabled())->toBeFalse()
        ->and(app(CommentAvailability::class)->isOpenFor($entry))->toBeFalse();
});

it('follows the comments_open meta of the entry', function () {
    $closed = Entry::factory()->published()->create(['meta' => ['comments_open' => false]]);
    $open = Entry::factory()->published()->create(['meta' => ['comments_open' => true]]);

    expect(app(CommentAvailability::class)->isOpenFor($closed))->toBeFalse()
        ->and(app(CommentAvailability::class)->isOpenFor($open))->toBeTrue();
});

it('uses the config default when the meta is missing', function () {
    config()->set('verso.comments.open_by_default', false);
    $entry = Entry::factory()->published()->create(['meta' => []]);
    $open = Entry::factory()->published()->create(['meta' => ['comments_open' => true]]);

    expect(app(CommentAvailability::class)->isOpenFor($entry))->toBeFalse()
        ->and(app(CommentAvailability::class)->isOpenFor($open))->toBeTrue();
});

it('is closed on drafts, scheduled and trashed entries', function () {
    expect(app(CommentAvailability::class)->isOpenFor(Entry::factory()->create()))->toBeFalse()
        ->and(app(CommentAvailability::class)->isOpenFor(Entry::factory()->scheduled()->create()))->toBeFalse()
        ->and(app(CommentAvailability::class)->isOpenFor(Entry::factory()->published()->create()->moveToTrash()))->toBeFalse();
});
