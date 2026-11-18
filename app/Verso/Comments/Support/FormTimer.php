<?php

namespace App\Verso\Comments\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

// Time trap: the form carries the time it was shown. Bots send it too fast.
class FormTimer
{
    // Name of the hidden field that holds the token.
    public const FIELD = 'verso_form_time';

    public function __construct(
        protected int $minSeconds,
        protected int $maxSeconds,
    ) {}

    // An encrypted copy of the current time, for a hidden form field.
    public function token(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    // Seconds since the form was shown, or null when the token is broken.
    public function elapsed(?string $token): ?int
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $shownAt = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($shownAt) ? now()->getTimestamp() - (int) $shownAt : null;
    }

    public function isTooFast(?string $token): bool
    {
        $elapsed = $this->elapsed($token);

        return $elapsed !== null && $elapsed < $this->minSeconds;
    }

    // True when the token is missing, broken or too old.
    public function isExpired(?string $token): bool
    {
        $elapsed = $this->elapsed($token);

        return $elapsed === null || $elapsed > $this->maxSeconds;
    }
}
