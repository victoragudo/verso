<?php

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Exceptions\LastAdminException;
use App\Verso\Users\Support\AdminGuard;

beforeEach(function () {
    $this->guard = app(AdminGuard::class);
});

it('knows when a user is the last admin', function () {
    $marta = User::factory()->admin()->create(['name' => 'Marta Ribeiro']);

    expect($this->guard->isLastAdmin($marta))->toBeTrue();

    User::factory()->admin()->create(['name' => 'Jonas Becker']);

    expect($this->guard->isLastAdmin($marta))->toBeFalse();
});

it('never treats editors or unsaved users as the last admin', function () {
    $editor = User::factory()->editor()->create();
    $draft = User::factory()->admin()->make();

    expect($this->guard->isLastAdmin($editor))->toBeFalse()
        ->and($this->guard->isLastAdmin($draft))->toBeFalse();
});

it('uses the stored role, not an unsaved change', function () {
    $admin = User::factory()->admin()->create();
    $admin->role = Role::Editor;

    expect($this->guard->isLastAdmin($admin))->toBeTrue();
});

it('blocks demoting the last admin', function () {
    $admin = User::factory()->admin()->create();

    $this->guard->ensureCanChangeRole($admin, Role::Editor);
})->throws(LastAdminException::class, 'at least one admin');

it('allows keeping the admin role on the last admin', function () {
    $admin = User::factory()->admin()->create();

    $this->guard->ensureCanChangeRole($admin, Role::Admin);

    expect(true)->toBeTrue();
});

it('blocks deleting the last admin', function () {
    $admin = User::factory()->admin()->create();

    $this->guard->ensureCanDelete($admin);
})->throws(LastAdminException::class, 'last admin');

it('allows deleting an admin when another admin remains', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    $this->guard->ensureCanDelete($admin);

    expect(true)->toBeTrue();
});
