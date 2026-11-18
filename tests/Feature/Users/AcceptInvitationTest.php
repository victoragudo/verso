<?php

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Actions\InviteUser;
use App\Verso\Users\Notifications\UserInvitation;
use App\Verso\Users\Support\Invitations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    User::factory()->admin()->create();
    $this->invited = app(InviteUser::class)->handle(['name' => 'Valeria Campos', 'email' => 'valeria@campos.example', 'role' => Role::Author]);
    $this->link = app(Invitations::class)->url($this->invited);
});

it('shows the accept page', function () {
    $this->get($this->link)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/accept-invitation')
            ->where('name', 'Valeria Campos')
            ->where('email', 'valeria@campos.example')
            ->where('submitUrl', $this->link)
            ->has('passwordRules')
        );
});

it('sets the password, signs in and opens the admin', function () {
    $this->post($this->link, ['password' => 'sunny-terrace-88', 'password_confirmation' => 'sunny-terrace-88'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($this->invited);
    $user = $this->invited->fresh();
    expect(Hash::check('sunny-terrace-88', $user->password))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(app(Invitations::class)->isPending($user))->toBeFalse();
});

it('validates the password', function () {
    $this->post($this->link, ['password' => 'sunny-terrace-88', 'password_confirmation' => 'cloudy'])
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});

it('works only once', function () {
    $this->post($this->link, ['password' => 'sunny-terrace-88', 'password_confirmation' => 'sunny-terrace-88']);
    auth()->logout();

    $this->get($this->link)->assertForbidden();
});

it('rejects links without a valid signature', function () {
    $this->get(route('invitation.show', ['user' => $this->invited->id, 'token' => 'guess']))->assertForbidden();
    $this->get($this->link.'x')->assertForbidden();
});

it('rejects expired links', function () {
    $this->travel((int) config('verso.users.invitation_expires_hours') + 1)->hours();

    $this->get($this->link)->assertForbidden();
});

it('rejects old links after a resend', function () {
    $this->travel(1)->minutes();
    $this->actingAs(User::query()->where('role', Role::Admin)->firstOrFail())
        ->post(route('admin.users.invitation.resend', $this->invited));
    auth()->logout();

    $this->get($this->link)->assertForbidden();
    Notification::assertSentToTimes($this->invited, UserInvitation::class, 2);
});
