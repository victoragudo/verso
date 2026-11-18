<?php

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use Illuminate\Support\Facades\Gate;

it('stores the subscriber role by default', function () {
    $user = User::factory()->create(['name' => 'Lucía Fernández']);

    expect($user->fresh()->role)->toBe(Role::Subscriber);
});

it('uses the subscriber role for a new user in memory', function () {
    expect((new User)->role)->toBe(Role::Subscriber);
});

it('maps capabilities to the gate for admins', function () {
    $admin = User::factory()->admin()->create();

    foreach (Capability::cases() as $capability) {
        expect($admin->can($capability->value))->toBeTrue();
    }
});

it('denies capabilities the role does not give', function () {
    $author = User::factory()->author()->create();

    expect($author->can(Capability::PublishEntries->value))->toBeTrue()
        ->and($author->can(Capability::ManageSettings->value))->toBeFalse()
        ->and(Gate::forUser($author)->allows(Capability::ManageUsers->value))->toBeFalse();
});

it('does not touch abilities that are not capabilities', function () {
    Gate::define('read-newsletter', fn (User $user) => $user->email === 'marta@example.org');

    $user = User::factory()->create(['email' => 'marta@example.org']);

    expect($user->can('read-newsletter'))->toBeTrue();
});

it('exposes capability names and the admin check', function () {
    $editor = User::factory()->editor()->create();

    expect($editor->isAdmin())->toBeFalse()
        ->and($editor->capabilityNames())->toContain('edit_any_entry', 'manage_terms')
        ->and(User::factory()->admin()->make()->isAdmin())->toBeTrue();
});
