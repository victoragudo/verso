<?php

namespace App\Verso\I18n\Support;

// CLDR plural forms of the admin languages, so plurals work without the intl extension.
class PluralRules
{
    public static function select(string $locale, int|float $number, bool $ordinal = false): string
    {
        $language = strtolower(explode('-', str_replace('_', '-', $locale))[0]);
        $n = abs($number);
        $i = (int) floor($n);
        $isInteger = $n == $i;

        if ($ordinal) {
            return self::ordinal($language, $i, $isInteger);
        }

        return match ($language) {
            'ja', 'zh', 'ko', 'id' => 'other',
            'fr', 'pt' => $i === 0 || $i === 1 ? 'one' : self::largeOther($language, $i, $isInteger),
            'hi', 'bn' => $i === 0 || $n == 1 ? 'one' : 'other',
            'ru' => self::russian($i, $isInteger),
            'ar' => self::arabic($n, $isInteger),
            'es', 'it' => $n == 1 ? 'one' : self::largeOther($language, $i, $isInteger),
            default => $n == 1 && $isInteger ? 'one' : 'other',
        };
    }

    // French, Spanish, Italian and Portuguese use "many" for round millions.
    protected static function largeOther(string $language, int $i, bool $isInteger): string
    {
        return $isInteger && $i !== 0 && $i % 1_000_000 === 0 ? 'many' : 'other';
    }

    protected static function russian(int $i, bool $isInteger): string
    {
        if (! $isInteger) {
            return 'other';
        }

        $mod10 = $i % 10;
        $mod100 = $i % 100;

        return match (true) {
            $mod10 === 1 && $mod100 !== 11 => 'one',
            $mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14) => 'few',
            default => 'many',
        };
    }

    protected static function arabic(float|int $n, bool $isInteger): string
    {
        if (! $isInteger) {
            return 'other';
        }

        $mod100 = ((int) $n) % 100;

        return match (true) {
            $n == 0 => 'zero',
            $n == 1 => 'one',
            $n == 2 => 'two',
            $mod100 >= 3 && $mod100 <= 10 => 'few',
            $mod100 >= 11 => 'many',
            default => 'other',
        };
    }

    protected static function ordinal(string $language, int $i, bool $isInteger): string
    {
        if (! $isInteger) {
            return 'other';
        }

        if ($language === 'en') {
            $mod10 = $i % 10;
            $mod100 = $i % 100;

            return match (true) {
                $mod10 === 1 && $mod100 !== 11 => 'one',
                $mod10 === 2 && $mod100 !== 12 => 'two',
                $mod10 === 3 && $mod100 !== 13 => 'few',
                default => 'other',
            };
        }

        return match ($language) {
            'fr' => $i === 1 ? 'one' : 'other',
            'it' => in_array($i, [8, 11, 80, 800], true) ? 'many' : 'other',
            default => 'other',
        };
    }
}
