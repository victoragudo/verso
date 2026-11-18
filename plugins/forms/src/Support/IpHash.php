<?php

namespace Verso\Plugins\Forms\Support;

// Turns an IP address into a keyed hash, so the raw address is never stored.
class IpHash
{
    public static function of(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
