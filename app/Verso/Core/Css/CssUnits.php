<?php

namespace App\Verso\Core\Css;

// Every CSS unit and keyword Verso knows, in groups. resources/js/lib/css-value.ts has the same lists;
// tests/Fixtures/css-values.json is checked by both sides so they never drift.
final class CssUnits
{
    // Units most people use, shown first in the unit picker.
    public const array COMMON = ['px', 'rem', 'em', '%', 'vw', 'vh'];

    // Length units by group, in the order the unit picker shows them.
    public const array LENGTH_GROUPS = [
        'absolute' => ['px', 'cm', 'mm', 'Q', 'in', 'pc', 'pt'],
        'font' => ['em', 'rem', 'ex', 'rex', 'cap', 'rcap', 'ch', 'rch', 'ic', 'ric', 'lh', 'rlh'],
        'viewport' => [
            'vw', 'vh', 'vi', 'vb', 'vmin', 'vmax',
            'svw', 'svh', 'svi', 'svb', 'svmin', 'svmax',
            'lvw', 'lvh', 'lvi', 'lvb', 'lvmin', 'lvmax',
            'dvw', 'dvh', 'dvi', 'dvb', 'dvmin', 'dvmax',
        ],
        'container' => ['cqw', 'cqh', 'cqi', 'cqb', 'cqmin', 'cqmax'],
        'percent' => ['%'],
    ];

    public const array ANGLE = ['deg', 'rad', 'grad', 'turn'];

    public const array TIME = ['s', 'ms'];

    // Keywords a property may allow next to its units.
    public const array KEYWORDS = ['auto', 'fit-content', 'min-content', 'max-content', 'none', 'normal'];

    // Functions allowed in a custom value.
    public const array FUNCTIONS = ['calc', 'clamp', 'min', 'max', 'var'];

    // Longest custom value, in characters.
    public const int MAX_LENGTH = 200;

    // Deepest nesting of functions and brackets in a custom value.
    public const int MAX_DEPTH = 6;

    /** @return list<string> every length unit, % included */
    public static function lengths(): array
    {
        return array_merge(...array_values(self::LENGTH_GROUPS));
    }

    /** @return list<string> the units of a value type */
    public static function forType(CssValueType $type): array
    {
        return match ($type) {
            CssValueType::Length => self::lengths(),
            CssValueType::Angle => self::ANGLE,
            CssValueType::Time => self::TIME,
            CssValueType::Number => [],
        };
    }

    /** @return list<string> every unit of every type */
    public static function all(): array
    {
        return [...self::lengths(), ...self::ANGLE, ...self::TIME];
    }

    // The unit as CSS writes it, like "Q" for "q" or "px" for "PX". Null when it is not a unit.
    public static function canonical(string $unit): ?string
    {
        foreach (self::all() as $known) {
            if (strcasecmp($known, $unit) === 0) {
                return $known;
            }
        }

        return null;
    }
}
