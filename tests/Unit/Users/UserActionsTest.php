<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Core\Enums\Role;
use App\Verso\Users\Actions\AcceptInvitation;
use App\Verso\Users\Actions\ChangeUserRole;
use App\Verso\Users\Actions\CreateUser;
use App\Verso\Users\Actions\DeleteUser;
use App\Verso\Users\Actions\InviteUser;
use App\Verso\Users\Actions\SendInvitation;
use App\Verso\Users\Exceptions\LastAdminException;
use App\Verso\Users\Notifications\UserInvitation;
use App\Verso\Users\Support\Invitations;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('creates a verified user with a role and password', function () {
    $user = app(CreateUser::class)->handle([
        'name' => 'Elena Navarro',
        'email' => 'elena@cafeprensa.es',
        'role' => Role::Editor,
        'password' => 'morning-espresso-42',
    ]);

    expect($user->exists)->toBeTrue()
        ->and($user->role)->toBe(Role::Editor)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('morning-espresso-42', $user->password))->toBeTrue();
});

it('invites a user and emails a link', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create(['name' => 'Ines Duarte']);

    $user = app(InviteUser::class)->handle([
        'name' => 'Tomás Pereira',
        'email' => 'tomas@quintadorio.pt',
        'role' => Role::Author,
    ], $admin);

    expect($user->role)->toBe(Role::Author)
        ->and($user->email_verified_at)->toBeNull()
        ->and(app(Invitations::class)->isPending($user))->toBeTrue();

    Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user) {
        $mail = $notification->toMail($user);

        return str_contains($notification->url, '/invitation/'.$user->id.'/')
            && $notification->invitedBy === 'Ines Duarte'
            && str_contains((string) $mail->introLines[0], 'Ines Duarte')
            && $mail->actionUrl === $notification->url;
    });
});

it('sends the invitation again with a new link', function () {
    Notification::fake();
    $user = app(InviteUser::class)->handle(['name' => 'Rui Costa', 'email' => 'rui@barcos.pt', 'role' => Role::Contributor]);

    $this->travel(2)->minutes();
    app(SendInvitation::class)->handle($user);

    Notification::assertSentToTimes($user, UserInvitation::class, 2);
});

it('writes a mail without an inviter name', function () {
    $mail = (new UserInvitation('https://verso.example/invitation/1/abc', 48))->toMail(new User);

    expect((string) $mail->introLines[0])->toStartWith('You have been invited')
        ->and($mail->outroLines[0])->toContain('48 hours')
        ->and((new UserInvitation('https://verso.example', 1))->via(new User))->toBe(['mail']);
});

it('accepts an invitation', function () {
    Notification::fake();
    $user = app(InviteUser::class)->handle(['name' => 'Lucía Romero', 'email' => 'lucia@huerta.es', 'role' => Role::Author]);

    app(AcceptInvitation::class)->handle($user, 'tomato-garden-2026');

    $user->refresh();
    expect(app(Invitations::class)->isPending($user))->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('tomato-garden-2026', $user->password))->toBeTrue();
});

it('refuses to accept when there is no invitation', function () {
    app(AcceptInvitation::class)->handle(User::factory()->create(), 'tomato-garden-2026');
})->throws(LogicException::class);

it('changes a role', function () {
    $user = User::factory()->author()->create();

    app(ChangeUserRole::class)->handle($user, Role::Editor);

    expect($user->fresh()->role)->toBe(Role::Editor);
});

it('refuses to demote the last admin', function () {
    $admin = User::factory()->admin()->create();

    app(ChangeUserRole::class)->handle($admin, Role::Subscriber);
})->throws(LastAdminException::class);

it('deletes a user and gives the content to another user', function () {
    $leaving = User::factory()->author()->create(['name' => 'Bruno Silva']);
    $staying = User::factory()->editor()->create(['name' => 'Clara Mendes']);
    $posts = Entry::factory()->count(3)->by($leaving)->create();

    $moved = app(DeleteUser::class)->handle($leaving, $staying);

    expect($moved)->toBe(3)
        ->and(User::query()->find($leaving->id))->toBeNull()
        ->and($posts->every(fn (Entry $post) => $post->fresh()->author_id === $staying->id))->toBeTrue();
});

it('deletes a user without content', function () {
    $reader = User::factory()->create();

    expect(app(DeleteUser::class)->handle($reader))->toBe(0)
        ->and(User::query()->find($reader->id))->toBeNull();
});

it('refuses to give content to the deleted user', function () {
    $user = User::factory()->author()->create();

    app(DeleteUser::class)->handle($user, $user);
})->throws(InvalidArgumentException::class);

it('keeps the content when the delete fails', function () {
    $admin = User::factory()->admin()->create();
    $editor = User::factory()->editor()->create();
    $post = Entry::factory()->by($admin)->create();

    expect(fn () => app(DeleteUser::class)->handle($admin, $editor))->toThrow(LastAdminException::class);
    expect($post->fresh()->author_id)->toBe($admin->id);
});
