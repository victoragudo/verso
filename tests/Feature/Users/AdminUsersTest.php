<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Notifications\UserInvitation;
use App\Verso\Users\Support\Invitations;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Alicia Ferrer', 'email' => 'alicia@verso.example']);
});

it('lists users with roles, counts and entry totals', function () {
    $writer = User::factory()->author()->create(['name' => 'Bruno Silva']);
    User::factory()->editor()->create(['name' => 'Clara Mendes']);
    User::factory()->create(['name' => 'Diego Ramos']);
    Entry::factory()->count(2)->by($writer)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 4)
            ->where('users.data.0.name', 'Alicia Ferrer')
            ->where('users.data.0.is_self', true)
            ->where('users.data.1.name', 'Bruno Silva')
            ->where('users.data.1.role', 'author')
            ->where('users.data.1.role_label', 'Author')
            ->where('users.data.1.entries_count', 2)
            ->where('users.data.1.invited', false)
            ->where('counts.all', 4)
            ->where('counts.admin', 1)
            ->where('counts.editor', 1)
            ->where('counts.subscriber', 1)
            ->where('counts.contributor', 0)
            ->has('roles', 5)
            ->where('roles.0', ['value' => 'admin', 'label' => 'Administrator'])
            ->has('reassignOptions', 3)
        );
});

it('filters users by role', function () {
    User::factory()->editor()->count(2)->create();
    User::factory()->author()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => 'editor']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 2)
            ->where('filters.role', 'editor')
        );
});

it('ignores an unknown role filter', function () {
    User::factory()->editor()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => 'overlord']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 2)->where('filters.role', null));
});

it('searches by name and email', function () {
    User::factory()->create(['name' => 'Marina Costa', 'email' => 'marina@olive.example']);
    User::factory()->create(['name' => 'Pablo Ruiz', 'email' => 'pablo@harbour.example']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'marina']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Marina Costa'));

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'harbour']))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Pablo Ruiz'));
});

it('lists invited users as pending invitations, not as active users', function () {
    Notification::fake();
    $this->actingAs($this->admin)->post(route('admin.users.invite'), ['name' => 'Rosa Vidal', 'email' => 'rosa@vidal.example', 'role' => 'author']);

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['search' => 'Rosa']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 0)
            ->has('invitations', 1)
            ->where('invitations.0.name', 'Rosa Vidal')
            ->where('invitations.0.invited', true)
        );
});

it('sends every pending invitation, even when active users fill several pages', function () {
    config(['verso.admin.per_page' => 2]);
    User::factory()->count(3)->create();
    $invitations = app(Invitations::class);
    foreach (['Zoe Navarro', 'Aaron Pereira', 'Mateo Lindqvist'] as $index => $name) {
        $invited = User::factory()->author()->create(['name' => $name]);
        $this->travelTo(now()->subDays(3 - $index));
        $invitations->markInvited($invited);
        $invited->save();
        $this->travelBack();
    }

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 2)
            ->where('users.total', 4)
            ->has('invitations', 3)
            ->where('invitations.0.name', 'Mateo Lindqvist')
            ->where('invitations.2.name', 'Zoe Navarro')
        );

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 2)->has('invitations', 3));
});

it('filters pending invitations by role and search', function () {
    $invitations = app(Invitations::class);
    foreach ([['Ines Duarte', 'editor'], ['Ivo Santos', 'author'], ['Lara Gomes', 'editor']] as [$name, $role]) {
        $invited = User::factory()->create(['name' => $name, 'role' => $role]);
        $invitations->markInvited($invited);
        $invited->save();
    }

    $this->actingAs($this->admin)
        ->get(route('admin.users.index', ['role' => 'editor', 'search' => 'Ines']))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 1)->where('invitations.0.name', 'Ines Duarte'));
});

it('limits the pending invitations list', function () {
    config(['verso.users.pending_invitations_limit' => 2]);
    $invitations = app(Invitations::class);
    foreach (User::factory()->count(3)->create() as $invited) {
        $invitations->markInvited($invited);
        $invited->save();
    }

    $this->actingAs($this->admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 2));
});

it('forbids everyone but admins', function (Role $role) {
    $user = User::factory()->role($role)->create();
    $target = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.store'), [])->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.invite'), [])->assertForbidden();
    $this->actingAs($user)->put(route('admin.users.role', $target), ['role' => 'admin'])->assertForbidden();
    $this->actingAs($user)->delete(route('admin.users.destroy', $target))->assertForbidden();
    $this->actingAs($user)->post(route('admin.users.invitation.resend', $target))->assertForbidden();

    expect($target->fresh()->role)->toBe(Role::Subscriber);
})->with([Role::Editor, Role::Author, Role::Contributor, Role::Subscriber]);

it('redirects guests to the login page', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

it('creates a user with a password', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.store'), [
            'name' => 'Nuria Castell',
            'email' => 'nuria@castell.example',
            'role' => 'editor',
            'password' => 'olive-grove-2026',
            'password_confirmation' => 'olive-grove-2026',
        ])
        ->assertRedirect(route('admin.users.index'))
        ->assertInertiaFlash('toast.message', 'Nuria Castell was added.');

    $user = User::query()->where('email', 'nuria@castell.example')->firstOrFail();
    expect($user->role)->toBe(Role::Editor)->and($user->hasVerifiedEmail())->toBeTrue();
});

it('validates the create form', function () {
    User::factory()->create(['email' => 'taken@verso.example']);

    $this->actingAs($this->admin)
        ->post(route('admin.users.store'), [
            'name' => '',
            'email' => 'taken@verso.example',
            'role' => 'overlord',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors(['name', 'email', 'role', 'password']);
});

it('invites a user by email', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.users.invite'), ['name' => 'Hugo Lima', 'email' => 'hugo@lima.example', 'role' => 'contributor'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Invitation sent to hugo@lima.example.');

    $user = User::query()->where('email', 'hugo@lima.example')->firstOrFail();
    expect($user->role)->toBe(Role::Contributor);
    Notification::assertSentTo($user, UserInvitation::class, fn (UserInvitation $notification) => $notification->invitedBy === 'Alicia Ferrer');
});

it('validates the invite form', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.users.invite'), ['name' => 'Hugo Lima', 'email' => 'not-an-email', 'role' => ''])
        ->assertSessionHasErrors(['email', 'role']);
});

it('sends an invitation again', function () {
    Notification::fake();
    $this->actingAs($this->admin)->post(route('admin.users.invite'), ['name' => 'Hugo Lima', 'email' => 'hugo@lima.example', 'role' => 'author']);
    $user = User::query()->where('email', 'hugo@lima.example')->firstOrFail();

    $this->actingAs($this->admin)
        ->post(route('admin.users.invitation.resend', $user))
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Invitation sent again to hugo@lima.example.');

    Notification::assertSentToTimes($user, UserInvitation::class, 2);
});

it('does not resend for active users', function () {
    $user = User::factory()->author()->create();

    $this->actingAs($this->admin)->post(route('admin.users.invitation.resend', $user))->assertNotFound();
});

it('changes the role of a user', function () {
    $user = User::factory()->author()->create(['name' => 'Iker Soto']);

    $this->actingAs($this->admin)
        ->put(route('admin.users.role', $user), ['role' => 'editor'])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Iker Soto is now Editor.');

    expect($user->fresh()->role)->toBe(Role::Editor);
});

it('validates the role', function () {
    $user = User::factory()->author()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.role', $user), ['role' => 'overlord'])
        ->assertSessionHasErrors('role');
});

it('keeps the last admin', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.users.index'))
        ->put(route('admin.users.role', $this->admin), ['role' => 'editor'])
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors(['role' => 'The site needs at least one admin. Make another user an admin first.']);

    expect($this->admin->fresh()->role)->toBe(Role::Admin);
});

it('lets an admin step down when another admin exists', function () {
    User::factory()->admin()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.users.role', $this->admin), ['role' => 'editor'])
        ->assertSessionHasNoErrors();

    expect($this->admin->fresh()->role)->toBe(Role::Editor);
});

it('deletes a user and moves the content', function () {
    $leaving = User::factory()->author()->create(['name' => 'Bruno Silva']);
    $receiver = User::factory()->editor()->create();
    $posts = Entry::factory()->count(2)->by($leaving)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $leaving), ['reassign_to' => $receiver->id])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'Bruno Silva was deleted.');

    expect(User::query()->find($leaving->id))->toBeNull()
        ->and(Entry::query()->where('author_id', $receiver->id)->count())->toBe(2)
        ->and($posts->first()->fresh())->not->toBeNull();
});

it('asks who receives the content', function () {
    $leaving = User::factory()->author()->create();
    Entry::factory()->by($leaving)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $leaving))
        ->assertSessionHasErrors(['reassign_to' => 'Choose who will receive the content of this user.']);

    expect(User::query()->find($leaving->id))->not->toBeNull();
});

it('rejects giving content to the deleted user or a missing user', function () {
    $leaving = User::factory()->author()->create();
    Entry::factory()->by($leaving)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $leaving), ['reassign_to' => $leaving->id])
        ->assertSessionHasErrors('reassign_to');

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $leaving), ['reassign_to' => 999999])
        ->assertSessionHasErrors('reassign_to');
});

it('deletes a user without content and without a receiver', function () {
    $reader = User::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $reader))
        ->assertSessionHasNoErrors();

    expect(User::query()->find($reader->id))->toBeNull();
});

it('does not let admins delete themselves here', function () {
    $this->actingAs($this->admin)
        ->delete(route('admin.users.destroy', $this->admin))
        ->assertForbidden();
});

it('shows a nav item and a dashboard stat to admins only', function () {
    Notification::fake();
    $this->actingAs($this->admin)->post(route('admin.users.invite'), ['name' => 'Hugo Lima', 'email' => 'hugo@lima.example', 'role' => 'author']);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav', fn ($nav) => collect($nav)->contains('key', 'users'))
            ->where('stats', fn ($stats) => collect($stats)->firstWhere('key', 'invitations')['value'] === 1)
        );

    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav', fn ($nav) => ! collect($nav)->contains('key', 'users'))
        );

    expect(app(Invitations::class))->toBeInstanceOf(Invitations::class);
});
