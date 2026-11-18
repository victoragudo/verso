<?php

use App\Models\User;
use App\Verso\Users\Support\Invitations;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->invitations = app(Invitations::class);
});

it('reads the expiry from config', function () {
    config()->set('verso.users.invitation_expires_hours', 12);
    app()->forgetInstance(Invitations::class);

    expect(app(Invitations::class)->expiresHours())->toBe(12);
});

it('marks and clears an invitation', function () {
    $user = User::factory()->create(['name' => 'Sofia Marques']);

    expect($this->invitations->isPending($user))->toBeFalse();

    $this->invitations->markInvited($user);
    $user->save();

    expect($this->invitations->isPending($user->fresh()))->toBeTrue()
        ->and($this->invitations->invitedAt($user->fresh()))->toBeInstanceOf(Carbon::class);

    $this->invitations->clear($user);
    $user->save();

    expect($this->invitations->isPending($user->fresh()))->toBeFalse();
});

it('builds a signed link with a token', function () {
    $user = User::factory()->create();
    $this->invitations->markInvited($user);
    $user->save();

    $url = $this->invitations->url($user);

    expect($url)->toContain('/invitation/'.$user->id.'/')
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=');
});

it('accepts only the token of the current invitation', function () {
    $this->travelTo(now()->startOfMinute());
    $user = User::factory()->create();
    $this->invitations->markInvited($user);
    $user->save();
    $token = basename((string) parse_url($this->invitations->url($user), PHP_URL_PATH));

    expect($this->invitations->isValid($user, $token))->toBeTrue()
        ->and($this->invitations->isValid($user, 'made-up-token'))->toBeFalse();

    $this->travel(5)->minutes();
    $this->invitations->markInvited($user);
    $user->save();

    expect($this->invitations->isValid($user->fresh(), $token))->toBeFalse();
});

it('rejects any token when nobody was invited', function () {
    $user = User::factory()->create();

    expect($this->invitations->isValid($user, 'anything'))->toBeFalse();
});
