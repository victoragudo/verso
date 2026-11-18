<?php

namespace App\Verso\Easy\Styles;

use App\Verso\I18n\Translate;

// WCAG contrast checks for a site palette. resources/js/lib/easy/contrast.ts does the same, tests/Fixtures/contrast.json keeps both in line.
class ContrastChecker
{
    // WCAG AA: normal text needs 4.5:1, large text and buttons need 3:1.
    public const BODY_TEXT = 4.5;

    public const LARGE_TEXT = 3.0;

    // Share of the way to black or white that one "Fix it" step moves a color.
    protected const FIX_STEP = 0.02;

    /**
     * Color pairs to check: which role is read on which, the minimum ratio, the role a fix changes, and the plain warning.
     * The warning is an ICU message with "darker" or "lighter" as its direction, shared with contrast.ts.
     *
     * @var array<string, array{foreground: string, background: string, minimum: float, fix: string, message: string}>
     */
    public const CHECKS = [
        'text-background' => [
            'foreground' => 'text', 'background' => 'background', 'minimum' => self::BODY_TEXT, 'fix' => 'text',
            'message' => '{direction, select, darker {The text is hard to read on this background. Try a darker text color.} other {The text is hard to read on this background. Try a lighter text color.}}',
        ],
        'muted-background' => [
            'foreground' => 'muted', 'background' => 'background', 'minimum' => self::BODY_TEXT, 'fix' => 'muted',
            'message' => '{direction, select, darker {Quiet text, like dates and captions, is hard to read on this background. Try a darker quiet text color.} other {Quiet text, like dates and captions, is hard to read on this background. Try a lighter quiet text color.}}',
        ],
        'accent-background' => [
            'foreground' => 'accent', 'background' => 'background', 'minimum' => self::LARGE_TEXT, 'fix' => 'accent',
            'message' => '{direction, select, darker {Links and buttons are hard to see on this background. Try a darker accent color.} other {Links and buttons are hard to see on this background. Try a lighter accent color.}}',
        ],
        'text-surface' => [
            'foreground' => 'text', 'background' => 'surface', 'minimum' => self::BODY_TEXT, 'fix' => 'surface',
            'message' => '{direction, select, darker {Text on panels is hard to read. Try a darker panel color.} other {Text on panels is hard to read. Try a lighter panel color.}}',
        ],
    ];

    protected const HEX = '/^#([0-9a-f]{6}|[0-9a-f]{3})$/i';

    public function isColor(string $value): bool
    {
        return preg_match(self::HEX, $value) === 1;
    }

    // WCAG relative luminance of a hex color, from 0 (black) to 1 (white).
    public function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $this->rgb($hex));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    // Contrast ratio of two colors, from 1 (same) to 21 (black on white).
    public function ratio(string $first, string $second): float
    {
        $a = $this->luminance($first);
        $b = $this->luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public function passes(string $foreground, string $background, float $minimum = self::BODY_TEXT): bool
    {
        return $this->ratio($foreground, $background) >= $minimum;
    }

    /**
     * Warnings for the pairs of a palette that are hard to read. Missing roles are skipped,
     * and a color already flagged once is not flagged again (fixing it first often fixes the rest).
     *
     * @param  array<string, string>  $colors  role => hex
     * @return list<array{key: string, foreground: string, background: string, ratio: float, minimum: float, fix: string, direction: string, message: string}>
     */
    public function review(array $colors): array
    {
        $warnings = [];

        foreach (self::CHECKS as $key => $check) {
            $foreground = $colors[$check['foreground']] ?? null;
            $background = $colors[$check['background']] ?? null;

            if (! is_string($foreground) || ! is_string($background) || ! $this->isColor($foreground) || ! $this->isColor($background)) {
                continue;
            }

            $ratio = $this->ratio($foreground, $background);

            if ($ratio >= $check['minimum'] || in_array($check['foreground'], array_column($warnings, 'foreground'), true)) {
                continue;
            }

            $other = $check['fix'] === $check['foreground'] ? $background : $foreground;
            $direction = $this->direction($other);

            $warnings[] = [
                'key' => $key,
                'foreground' => $check['foreground'],
                'background' => $check['background'],
                'ratio' => round($ratio, 2),
                'minimum' => $check['minimum'],
                'fix' => $check['fix'],
                'direction' => $direction,
                'message' => Translate::icu($check['message'], ['direction' => $direction]),
            ];
        }

        return $warnings;
    }

    // The color moved toward black or white, step by step, until it reaches the minimum ratio against the other color.
    public function fix(string $color, string $other, float $minimum): string
    {
        $target = $this->direction($other) === 'darker' ? [0, 0, 0] : [255, 255, 255];
        $start = $this->rgb($color);
        $steps = (int) round(1 / self::FIX_STEP);

        for ($step = 0; $step <= $steps; $step++) {
            $amount = $step * self::FIX_STEP;
            $mixed = $this->hex(array_map(fn (int $from, int $to): int => (int) round($from + ($to - $from) * $amount), $start, $target));

            if ($this->ratio($mixed, $other) >= $minimum) {
                return $mixed;
            }
        }

        return $this->hex($target);
    }

    // "darker" when black stands out more on the other color than white does, else "lighter".
    public function direction(string $other): string
    {
        return $this->ratio('#000000', $other) >= $this->ratio('#ffffff', $other) ? 'darker' : 'lighter';
    }

    /**
     * @return array{int, int, int}
     */
    protected function rgb(string $hex): array
    {
        $hex = ltrim(strtolower($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /**
     * @param  array<int, int>  $rgb
     */
    protected function hex(array $rgb): string
    {
        return '#'.implode('', array_map(fn (int $channel): string => str_pad(dechex(max(0, min(255, $channel))), 2, '0', STR_PAD_LEFT), $rgb));
    }
}
