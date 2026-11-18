<?php

namespace App\Verso\Users\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

// Builds and checks the signed links that let invited users choose a password.
class Invitations
{
    public function __construct(
        protected int $expiresHours,
        protected string $secret,
    ) {}

    public function expiresHours(): int
    {
        return $this->expiresHours;
    }

    // Mark the user as invited now. A new date makes older links stop working.
    public function markInvited(User $user): void
    {
        $user->forceFill(['invited_at' => now()->format('Y-m-d H:i:s')]);
    }

    public function clear(User $user): void
    {
        $user->forceFill(['invited_at' => null]);
    }

    public function invitedAt(User $user): ?Carbon
    {
        $value = $user->getAttribute('invited_at');

        if ($value instanceof Carbon) {
            return $value;
        }

        return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
    }

    public function isPending(User $user): bool
    {
        return $this->invitedAt($user) !== null;
    }

    // A signed link that expires after the configured number of hours.
    public function url(User $user): string
    {
        return URL::temporarySignedRoute(
            'invitation.show',
            now()->addHours($this->expiresHours),
            ['user' => $user->getKey(), 'token' => $this->token($user)],
        );
    }

    // True when the token matches the current invitation of the user.
    public function isValid(User $user, string $token): bool
    {
        return $this->isPending($user) && hash_equals($this->token($user), $token);
    }

    protected function token(User $user): string
    {
        $invitedAt = $this->invitedAt($user)?->format('Y-m-d H:i:s') ?? '';

        return hash_hmac('sha256', $user->getKey().'|'.$user->email.'|'.$invitedAt, $this->secret);
    }
}
