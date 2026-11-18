<?php

use App\Models\User;
use App\Verso\Access\Models\StoredRole;
use App\Verso\Access\Permissions\UserPermissions;
use Illuminate\Support\Facades\DB;

// A custom role saved straight to the database, like an admin made it on the Roles page.
function customRole(string $slug, array $capabilities, ?string $name = null): StoredRole
{
    $role = StoredRole::query()->create([
        'slug' => $slug,
        'name' => $name ?? ucfirst(str_replace('_', ' ', $slug)),
        'capabilities' => $capabilities,
    ]);

    app(UserPermissions::class)->forget();

    return $role;
}

// Give a user extra roles on top of their main role.
function giveExtraRoles(User $user, string ...$roles): User
{
    foreach ($roles as $role) {
        DB::table('role_user')->insert(['user_id' => $user->id, 'role' => $role]);
    }

    $user->refreshAccess();

    return $user;
}

// Give or take single capabilities from one user.
function overrideCapabilities(User $user, array $grants = [], array $denies = []): User
{
    foreach ($grants as $capability) {
        DB::table('user_capabilities')->insert(['user_id' => $user->id, 'capability' => $capability, 'granted' => true]);
    }

    foreach ($denies as $capability) {
        DB::table('user_capabilities')->insert(['user_id' => $user->id, 'capability' => $capability, 'granted' => false]);
    }

    $user->refreshAccess();

    return $user;
}

// A subscriber with one custom role that holds exactly these capabilities.
function userWith(array $capabilities, string $name = 'Marta Ribeiro'): User
{
    static $count = 0;
    $count++;
    $slug = 'only_'.$count;

    customRole($slug, $capabilities);

    return giveExtraRoles(User::factory()->create(['name' => $name]), $slug);
}
