<?php

use App\Models\User;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Core\Enums\Capability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Sites that update keep every user and role: the new tables start empty and users.role stays the main role.
it('keeps the access of existing users when the roles tables are added', function () {
    $migration = require database_path('migrations/2026_11_21_100000_create_roles_tables.php');
    $editor = User::factory()->editor()->create(['name' => 'Olga Ventura']);
    $admin = User::factory()->admin()->create(['name' => 'Pedro Alves']);

    $migration->down();
    expect(Schema::hasTable('role_user'))->toBeFalse();

    $migration->up();
    app(UserPermissions::class)->forget();

    $editor = $editor->fresh();

    expect(Schema::hasTable('roles'))->toBeTrue()
        ->and($editor->roleSlugs())->toBe(['editor'])
        ->and($editor->hasCapability(Capability::EditAnyEntry))->toBeTrue()
        ->and($editor->hasCapability(Capability::ManageUsers))->toBeFalse()
        ->and($admin->fresh()->isAdmin())->toBeTrue();
});

it('drops a deleted user from the extra roles and grants', function () {
    $user = User::factory()->create();
    DB::table('role_user')->insert(['user_id' => $user->id, 'role' => 'editor']);
    DB::table('user_capabilities')->insert(['user_id' => $user->id, 'capability' => 'manage_menus', 'granted' => true]);

    $user->delete();

    expect(DB::table('role_user')->count())->toBe(0)
        ->and(DB::table('user_capabilities')->count())->toBe(0);
});
