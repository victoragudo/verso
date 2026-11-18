<?php

use App\Models\User;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Support\ViewAs;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;
use Illuminate\Support\Facades\Gate;

require_once __DIR__.'/Helpers.php';

it('gives the capabilities of the main role', function () {
    $author = User::factory()->author()->create();

    expect($author->hasCapability(Capability::PublishEntries))->toBeTrue()
        ->and($author->hasCapability('edit_any_entry'))->toBeFalse()
        ->and($author->can('publish_entries'))->toBeTrue();
});

it('joins the capabilities of every role of a user', function () {
    customRole('newsroom_moderator', ['moderate_comments']);
    $author = giveExtraRoles(User::factory()->author()->create(), 'newsroom_moderator');

    expect($author->roleSlugs())->toBe(['author', 'newsroom_moderator'])
        ->and($author->hasRole('newsroom_moderator'))->toBeTrue()
        ->and($author->hasCapability(Capability::ModerateComments))->toBeTrue()
        ->and($author->hasCapability(Capability::PublishEntries))->toBeTrue();
});

it('adds single grants and removes single denies', function () {
    $editor = overrideCapabilities(User::factory()->editor()->create(), grants: ['manage_menus'], denies: ['moderate_comments']);

    expect($editor->hasCapability(Capability::ManageMenus))->toBeTrue()
        ->and($editor->hasCapability(Capability::ModerateComments))->toBeFalse()
        ->and($editor->hasCapability(Capability::EditAnyEntry))->toBeTrue();
});

it('gives implied capabilities, and a deny of an umbrella removes what it implies', function () {
    $manager = userWith(['manage_users']);

    expect($manager->hasCapability(Capability::ListUsers))->toBeTrue()
        ->and($manager->hasCapability(Capability::PromoteUsers))->toBeTrue();

    overrideCapabilities($manager, denies: ['manage_users']);

    expect($manager->hasCapability(Capability::ListUsers))->toBeFalse();
});

it('lets a deny win over a grant of the same capability through a role', function () {
    $user = overrideCapabilities(userWith(['list_users', 'create_users']), denies: ['create_users']);

    expect($user->hasCapability('list_users'))->toBeTrue()
        ->and($user->hasCapability('create_users'))->toBeFalse();
});

it('gives administrators every capability, also plugin ones, and ignores denies', function () {
    $admin = overrideCapabilities(User::factory()->admin()->create(), denies: ['manage_users']);

    expect($admin->hasCapability(Capability::ManageUsers))->toBeTrue()
        ->and($admin->hasCapability('entries.page.delete_others'))->toBeTrue()
        ->and($admin->capabilityNames())->toContain('manage_roles', 'entries.post.publish');
});

it('makes an extra administrator role count as administrator', function () {
    $editor = giveExtraRoles(User::factory()->editor()->create(), Role::Admin->value);

    expect($editor->isAdmin())->toBeTrue()
        ->and($editor->hasCapability(Capability::ManageSystem))->toBeTrue();
});

it('answers the gate for core, content type and plugin capabilities', function () {
    $user = userWith(['entries.page.publish']);

    expect(Gate::forUser($user)->allows('entries.page.publish'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('entries.post.publish'))->toBeFalse()
        ->and(Gate::forUser($user)->allows('manage_users'))->toBeFalse();
});

it('finds users by role, main or extra', function () {
    customRole('fact_checker', ['read_private_entries']);
    $main = User::factory()->editor()->create(['name' => 'Rui Pacheco']);
    $extra = giveExtraRoles(User::factory()->author()->create(['name' => 'Sofia Lima']), 'editor');
    User::factory()->author()->create(['name' => 'Tiago Nunes']);

    expect(User::query()->withRole('editor')->orderBy('name')->pluck('name')->all())->toBe(['Rui Pacheco', 'Sofia Lima'])
        ->and(User::query()->withRole(Role::Author)->count())->toBe(2);
});

it('answers as the previewed role while an administrator views the admin as it', function () {
    $admin = User::factory()->admin()->create();
    app(ViewAs::class)->start($admin->id, 'author');
    app(UserPermissions::class)->forget($admin);

    expect($admin->hasCapability(Capability::PublishEntries))->toBeTrue()
        ->and($admin->hasCapability(Capability::ManageUsers))->toBeFalse()
        ->and($admin->roleSlugs())->toBe(['author'])
        ->and($admin->isAdmin())->toBeFalse();

    app(ViewAs::class)->stop();

    expect($admin->isAdmin())->toBeTrue();
});

it('forgets cached answers after the main role changes', function () {
    $user = User::factory()->author()->create();
    expect($user->hasCapability(Capability::EditAnyEntry))->toBeFalse();

    $user->role = Role::Editor;
    $user->save();

    expect($user->hasCapability(Capability::EditAnyEntry))->toBeTrue();
});

it('lists the capabilities a user has right now', function () {
    $contributor = User::factory()->contributor()->create();

    expect($contributor->capabilityNames())->toBe(['edit_own_entries', 'read_private_entries']);
});
