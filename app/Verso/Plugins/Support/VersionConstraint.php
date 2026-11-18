<?php

namespace App\Verso\Plugins\Support;

// Checks a version against a simple constraint like "^0.1", "~1.2", ">=1.0 <2.0" or "1.0 || 2.0".
final class VersionConstraint
{
    // Turn Composer style constraints like ">=8.2, <9" or "^8.2|^9" into the form satisfies() reads.
    public static function normalizeConstraint(string $constraint): string
    {
        $constraint = preg_replace('/(?<!\|)\|(?!\|)/', '||', $constraint) ?? $constraint;

        return trim(str_replace(',', ' ', $constraint));
    }

    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (explode('||', $constraint) as $alternative) {
            if (self::satisfiesAll($version, $alternative)) {
                return true;
            }
        }

        return false;
    }

    // True when the constraint can be read by satisfies().
    public static function isValid(string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (explode('||', $constraint) as $alternative) {
            $parts = preg_split('/\s+/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if ($parts === []) {
                return false;
            }

            foreach ($parts as $part) {
                if (preg_match('/^(\^|~|>=|<=|>|<|=)?v?\d+(\.\d+){0,2}$/', $part) !== 1) {
                    return false;
                }
            }
        }

        return true;
    }

    // Every part of a space separated list must match.
    protected static function satisfiesAll(string $version, string $constraint): bool
    {
        $parts = preg_split('/\s+/', trim($constraint), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return false;
        }

        foreach ($parts as $part) {
            if (! self::satisfiesOne($version, $part)) {
                return false;
            }
        }

        return true;
    }

    protected static function satisfiesOne(string $version, string $part): bool
    {
        if (preg_match('/^(\^|~|>=|<=|>|<|=)?v?(\d+(?:\.\d+){0,2})$/', $part, $match) !== 1) {
            return false;
        }

        $operator = $match[1];
        $target = self::normalize($match[2]);
        $version = self::normalize($version);

        return match ($operator) {
            '^' => version_compare($version, $target, '>=') && version_compare($version, self::nextCaret($match[2]), '<'),
            '~' => version_compare($version, $target, '>=') && version_compare($version, self::nextTilde($match[2]), '<'),
            '>=' => version_compare($version, $target, '>='),
            '<=' => version_compare($version, $target, '<='),
            '>' => version_compare($version, $target, '>'),
            '<' => version_compare($version, $target, '<'),
            default => version_compare($version, $target, '=='),
        };
    }

    // "1.2" becomes "1.2.0" so version_compare works as expected.
    protected static function normalize(string $version): string
    {
        $numbers = array_map('intval', explode('.', ltrim($version, 'v')));

        return implode('.', array_pad(array_slice($numbers, 0, 3), 3, 0));
    }

    // First version that "^x.y.z" does not allow. For 0.x versions the minor is the breaking part.
    protected static function nextCaret(string $version): string
    {
        [$major, $minor, $patch] = array_map('intval', explode('.', self::normalize($version)));

        if ($major > 0) {
            return ($major + 1).'.0.0';
        }

        if ($minor > 0) {
            return '0.'.($minor + 1).'.0';
        }

        return '0.0.'.($patch + 1);
    }

    // First version that "~x.y" or "~x.y.z" does not allow.
    protected static function nextTilde(string $version): string
    {
        $count = count(explode('.', $version));
        [$major, $minor] = array_map('intval', explode('.', self::normalize($version)));

        return $count >= 3 ? $major.'.'.($minor + 1).'.0' : ($major + 1).'.0.0';
    }
}
