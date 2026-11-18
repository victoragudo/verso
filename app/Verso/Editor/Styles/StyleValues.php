<?php

namespace App\Verso\Editor\Styles;

// Checks for the style values that are more than one word: gradients, background pictures and focal points.
// resources/js/builder/style-values.ts does the same in the editor; tests/Fixtures/block-styles.json keeps them equal.
final class StyleValues
{
    // Most color stops one gradient can have.
    public const int MAX_STOPS = 5;

    // A design token like "var:color.accent".
    public const string TOKEN = '/^var:([a-z][a-z0-9]*)\.([a-z0-9][a-z0-9-]{0,40})$/';

    // A hex color like #1f6f5c or #1f6f5c80.
    public const string HEX = '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    // The start of a gradient: its kind and its angle or shape.
    protected const string GRADIENT = '/^(linear|radial)-gradient\(\s*([0-9]{1,3}deg|circle|ellipse)\s*,(.+)\)$/i';

    // One color stop: a color, then an optional place in percent.
    protected const string STOP = '/^\s*(#[0-9a-f]{3,8}|var:[a-z][a-z0-9]*\.[a-z0-9][a-z0-9-]{0,40})(?:\s+([0-9]{1,3})%)?\s*$/i';

    // A picture address: a path on this site or a full http(s) address, with safe characters only,
    // so it can never close the url("...") it goes into.
    protected const string IMAGE = '/^(https?:\/\/[a-z0-9.-]+(:[0-9]{1,5})?)?\/[A-Za-z0-9\/_.~%-]{1,500}(\?[A-Za-z0-9=&_.%-]{0,300})?$/i';

    // A focal point: left and top in percent, like "30% 70%".
    protected const string FOCAL = '/^([0-9]{1,3})%\s+([0-9]{1,3})%$/';

    // Lowest and highest stacking order a block may ask for.
    public const int Z_MIN = -10;

    public const int Z_MAX = 10000;

    // The CSS of a gradient like "linear-gradient(135deg, #1f6f5c 0%, var:color.accent 100%)", or null.
    public static function gradient(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 400 || preg_match(self::GRADIENT, trim($value), $match) !== 1) {
            return null;
        }

        $kind = strtolower($match[1]);
        $shape = strtolower($match[2]);

        if (($kind === 'linear') !== str_ends_with($shape, 'deg') || ($kind === 'linear' && (int) $shape > 360)) {
            return null;
        }

        $stops = explode(',', $match[3]);

        if (count($stops) < 2 || count($stops) > self::MAX_STOPS) {
            return null;
        }

        $css = [];

        foreach ($stops as $stop) {
            if (preg_match(self::STOP, $stop, $part) !== 1) {
                return null;
            }

            $color = self::color($part[1]);

            if ($color === null || (isset($part[2]) && (int) $part[2] > 100)) {
                return null;
            }

            $css[] = $color.(isset($part[2]) ? ' '.(int) $part[2].'%' : '');
        }

        return $kind.'-gradient('.$shape.', '.implode(', ', $css).')';
    }

    // The CSS of a background picture address, or null when the address is not safe.
    public static function image(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::IMAGE, $value) === 1 ? 'url("'.$value.'")' : null;
    }

    // The CSS of a focal point like "30% 70%", or null.
    public static function focal(mixed $value): ?string
    {
        if (! is_string($value) || preg_match(self::FOCAL, trim($value), $match) !== 1 || (int) $match[1] > 100 || (int) $match[2] > 100) {
            return null;
        }

        return (int) $match[1].'% '.(int) $match[2].'%';
    }

    // A whole number for z-index, or null.
    public static function zIndex(mixed $value): ?string
    {
        return is_int($value) && $value >= self::Z_MIN && $value <= self::Z_MAX ? (string) $value : null;
    }

    // A hex color in lower case or a token as a CSS variable, or null.
    protected static function color(string $color): ?string
    {
        if (preg_match(self::TOKEN, $color, $token) === 1) {
            return 'var(--verso-'.$token[1].'-'.$token[2].')';
        }

        return preg_match(self::HEX, $color) === 1 ? strtolower($color) : null;
    }
}
