<?php

use App\Models\User;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;

it('hides optional roles until a plugin turns them on', function () {
    $roles = new OptionalRoles;

    expect($roles->available())->not->toContain(Role::ShopManager)
        ->and($roles->available())->toContain(Role::Admin, Role::Subscriber);

    $roles->enable(Role::ShopManager);

    expect($roles->available())->toContain(Role::ShopManager)
        ->and($roles->isEnabled(Role::ShopManager))->toBeTrue();
});

it('ignores built-in roles passed to enable', function () {
    $roles = new OptionalRoles;
    $roles->enable(Role::Editor);

    expect($roles->isEnabled(Role::Editor))->toBeFalse()
        ->and(count($roles->available()))->toBe(5);
});

it('marks only the shop manager as optional', function () {
    expect(array_values(array_filter(Role::cases(), fn (Role $role): bool => $role->isOptional())))->toBe([Role::ShopManager]);
});

it('gives admins the shop capability', function () {
    expect(Role::Admin->has(Capability::ManageShop))->toBeTrue()
        ->and(Role::ShopManager->has(Capability::ManageShop))->toBeTrue()
        ->and(Role::Editor->has(Capability::ManageShop))->toBeFalse();
});

it('labels roles with spaces', function () {
    expect(Role::ShopManager->label())->toBe('Shop manager')
        ->and(Role::Contributor->label())->toBe('Contributor');
});

it('refuses the shop manager role while the shop is off', function () {
    $user = User::factory()->author()->create(['name' => 'Jordi Puig']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.users.role', $user), ['role' => 'shop_manager'])
        ->assertSessionHasErrors('role');

    expect($user->fresh()->role)->toBe(Role::Author);
});
