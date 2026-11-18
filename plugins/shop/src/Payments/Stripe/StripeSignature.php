<?php

namespace Verso\Plugins\Shop\Payments\Stripe;

// Checks the Stripe-Signature header of a webhook, like "t=1700000000,v1=5257a8...".
class StripeSignature
{
    public function __construct(protected int $tolerance) {}

    // True when one v1 signature matches the payload and the time is recent enough.
    public function isValid(string $payload, ?string $header, string $secret, ?int $now = null): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        [$timestamp, $signatures] = $this->parse($header);

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if ($this->tolerance > 0 && abs(($now ?? time()) - $timestamp) > $this->tolerance) {
            return false;
        }

        $expected = self::sign($payload, $timestamp, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    // The v1 signature Stripe sends for a payload and time.
    public static function sign(string $payload, int $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    // A full header value, handy for tests and local tools.
    public static function header(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.self::sign($payload, $timestamp, $secret);
    }

    /**
     * @return array{0: int|null, 1: list<string>}
     */
    protected function parse(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($name === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($name === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [$timestamp, $signatures];
    }
}
