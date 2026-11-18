<?php

namespace Verso\Plugins\Shop\Support;

use Illuminate\Support\Number;
use InvalidArgumentException;

// Turns amounts in minor units (like cents) into text and back.
class Money
{
    /**
     * @param  list<string>  $zeroDecimalCurrencies
     */
    public function __construct(
        protected array $zeroDecimalCurrencies,
        protected string $locale = 'en',
    ) {}

    // Digits after the decimal point: 0 for JPY, 2 for EUR.
    public function decimals(string $currency): int
    {
        return in_array(strtoupper($currency), $this->zeroDecimalCurrencies, true) ? 0 : 2;
    }

    // A price for people, like "€12.50".
    public function format(int $minor, string $currency): string
    {
        $decimals = $this->decimals($currency);
        $amount = $minor / (10 ** $decimals);

        return (string) Number::currency($amount, in: strtoupper($currency), locale: $this->locale, precision: $decimals);
    }

    public function toMajor(int $minor, string $currency): float
    {
        return $minor / (10 ** $this->decimals($currency));
    }

    // A plain decimal string for forms, like "12.50".
    public function toDecimal(int $minor, string $currency): string
    {
        $decimals = $this->decimals($currency);

        if ($decimals === 0) {
            return (string) $minor;
        }

        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);
        $unit = 10 ** $decimals;

        return $sign.intdiv($minor, $unit).'.'.str_pad((string) ($minor % $unit), $decimals, '0', STR_PAD_LEFT);
    }

    // Read a typed amount like "12.5" or "1,299.99" into minor units. Extra digits are rounded half up.
    public function toMinor(string|int|float $amount, string $currency): int
    {
        $text = str_replace([',', ' '], '', trim((string) $amount));

        if (preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $text, $parts) !== 1 || ($parts[2] === '' && ($parts[3] ?? '') === '')) {
            throw new InvalidArgumentException("The amount \"{$amount}\" is not a number.");
        }

        $decimals = $this->decimals($currency);
        $fraction = $parts[3] ?? '';
        $kept = str_pad(substr($fraction, 0, $decimals), $decimals, '0');
        $minor = (int) ($parts[2] === '' ? '0' : $parts[2]) * (10 ** $decimals) + (int) ($kept === '' ? '0' : $kept);

        if ((int) ($fraction[$decimals] ?? '0') >= 5) {
            $minor++;
        }

        return $parts[1] === '-' ? -$minor : $minor;
    }
}
