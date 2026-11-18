<?php

use App\Models\User;
use App\Verso\Access\Actions\SaveRole;
use App\Verso\Access\Actions\SaveUserAccess;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\EscalationGuard;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Actions\ChangeUserRole;
use App\Verso\Users\Exceptions\LastAdminException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/Helpers.php';

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Helena Duarte']);
    // A team lead who may manage roles and promote users, with editor powers.
    customRole('team_lead', ['manage_roles', 'promote_users', 'list_users', 'edit_any_entry', 'edit_own_entries', 'publish_entries'], 'Team lead');
    $this->lead = giveExtraRoles(User::factory()->author()->create(['name' => 'Paulo Matos']), 'team_lead');
});

it('refuses to add capabilities to a role that the actor does not have', function () {
    $author = app(Roles::class)->find('author');

    app(SaveRole::class)->handle($this->lead, $author, ['name' => 'Author', 'capabilities' => [...$author->capabilities, 'manage_system']]);
})->throws(ValidationException::class, 'You cannot give capabilities you do not have yourself: Manage system settings and server health.');

it('lets the actor keep capabilities a role already has, even ones they lack', function () {
    $editor = app(Roles::class)->find('editor');
    $without = array_values(array_diff($editor->capabilities, ['edit_any_entry']));

    $saved = app(SaveRole::class)->handle($this->lead, $editor, ['name' => 'Editor', 'capabilities' => $without]);

    expect($saved->capabilities)->toContain('moderate_comments')->not->toContain('edit_any_entry');
});

it('refuses to give a role that has more than the actor', function () {
    $writer = User::factory()->contributor()->create();

    app(SaveUserAccess::class)->handle($this->lead, $writer, Role::Contributor, ['editor'], [], []);
})->throws(ValidationException::class);

it('refuses to change an administrator unless the actor is one', function () {
    $other = User::factory()->admin()->create();

    app(SaveUserAccess::class)->handle($this->lead, $other, Role::Editor, [], [], []);
})->throws(AuthorizationException::class);

it('refuses to raise your own access', function () {
    app(SaveUserAccess::class)->handle($this->lead, $this->lead, Role::Author, ['team_lead'], ['manage_system'], []);
})->throws(ValidationException::class);

it('refuses to take away your own deny, because it gives the capability back', function () {
    overrideCapabilities($this->lead, denies: ['moderate_comments']);

    app(SaveUserAccess::class)->handle($this->lead, $this->lead, Role::Author, ['team_lead'], [], []);
})->throws(ValidationException::class);

it('lets you lower your own access', function () {
    $saved = app(SaveUserAccess::class)->handle($this->lead, $this->lead, Role::Author, ['team_lead'], [], ['publish_entries']);

    expect($saved->hasCapability('publish_entries'))->toBeFalse();
});

it('never removes the last administrator', function () {
    app(SaveUserAccess::class)->handle($this->admin, $this->admin, Role::Editor, [], [], []);
})->throws(LastAdminException::class);

it('lets the last administrator move the admin role to an extra role', function () {
    $saved = app(SaveUserAccess::class)->handle($this->admin, $this->admin, Role::Editor, ['admin'], [], []);

    expect($saved->isAdmin())->toBeTrue()
        ->and($saved->fresh()->role)->toBe(Role::Editor);
});

it('stops a main role change of the last administrator also through the old action', function () {
    app(ChangeUserRole::class)->handle($this->admin, Role::Author, $this->admin);
})->throws(LastAdminException::class);

it('counts an extra administrator role when checking for the last admin', function () {
    giveExtraRoles(User::factory()->editor()->create(), 'admin');

    $changed = app(ChangeUserRole::class)->handle($this->admin, Role::Author, $this->admin);

    expect($changed->isAdmin())->toBeFalse();
});

it('knows which roles an actor may give', function () {
    $guard = app(EscalationGuard::class);

    expect($guard->canAssignRole($this->lead, 'contributor'))->toBeTrue()
        ->and($guard->canAssignRole($this->lead, 'author'))->toBeTrue()
        ->and($guard->canAssignRole($this->lead, 'editor'))->toBeFalse()
        ->and($guard->canAssignRole($this->lead, 'admin'))->toBeFalse()
        ->and($guard->canAssignRole($this->admin, 'admin'))->toBeTrue()
        ->and($guard->canManage($this->lead, User::factory()->contributor()->create()))->toBeTrue()
        ->and($guard->canManage($this->lead, User::factory()->editor()->create()))->toBeFalse();
});
