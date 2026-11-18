<?php

use App\Models\User;
use App\Verso\Media\Models\Media;

it('lets authors, editors and admins use the library', function (string $role, bool $allowed) {
    $user = User::factory()->{$role}()->create();

    expect($user->can('viewAny', Media::class))->toBe($allowed)
        ->and($user->can('create', Media::class))->toBe($allowed);
})->with([
    ['admin', true],
    ['editor', true],
    ['author', true],
    ['contributor', false],
]);

it('lets authors change only their own files', function () {
    $author = User::factory()->author()->create();
    $own = Media::factory()->create(['uploaded_by' => $author->id]);
    $other = Media::factory()->create();

    expect($author->can('update', $own))->toBeTrue()
        ->and($author->can('delete', $own))->toBeTrue()
        ->and($author->can('update', $other))->toBeFalse()
        ->and($author->can('delete', $other))->toBeFalse();
});

it('lets editors change any file', function () {
    $editor = User::factory()->editor()->create();
    $media = Media::factory()->create();

    expect($editor->can('update', $media))->toBeTrue()
        ->and($editor->can('delete', $media))->toBeTrue()
        ->and($editor->can('view', $media))->toBeTrue();
});

it('blocks subscribers', function () {
    $subscriber = User::factory()->create();
    $media = Media::factory()->create(['uploaded_by' => $subscriber->id]);

    expect($subscriber->can('view', $media))->toBeFalse()
        ->and($subscriber->can('update', $media))->toBeFalse();
});
