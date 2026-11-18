<?php

use App\Models\User;
use App\Verso\Comments\Models\Comment;
use App\Verso\Core\Enums\Role;

it('lets admins and editors moderate', function (Role $role, bool $allowed) {
    $user = User::factory()->role($role)->create();
    $comment = Comment::factory()->create();

    expect($user->can('viewAny', Comment::class))->toBe($allowed)
        ->and($user->can('moderate', $comment))->toBe($allowed)
        ->and($user->can('delete', $comment))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'editor' => [Role::Editor, true],
    'author' => [Role::Author, false],
    'contributor' => [Role::Contributor, false],
    'subscriber' => [Role::Subscriber, false],
]);
