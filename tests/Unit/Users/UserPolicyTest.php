<?php

use App\Models\User;
use App\Verso\Core\Enums\Role;

it('lets only admins manage users', function (Role $role, bool $allowed) {
    $viewer = User::factory()->role($role)->create();
    $other = User::factory()->create();

    expect($viewer->can('viewAny', User::class))->toBe($allowed)
        ->and($viewer->can('create', User::class))->toBe($allowed)
        ->and($viewer->can('update', $other))->toBe($allowed)
        ->and($viewer->can('delete', $other))->toBe($allowed);
})->with([
    'admin' => [Role::Admin, true],
    'editor' => [Role::Editor, false],
    'author' => [Role::Author, false],
    'contributor' => [Role::Contributor, false],
    'subscriber' => [Role::Subscriber, false],
]);

it('does not let admins delete themselves from the users page', function () {
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', $admin))->toBeFalse()
        ->and($admin->can('update', $admin))->toBeTrue();
});
