<?php

namespace App\Verso\Core\Css;

// Checks a CSS size value against a rule and gives it back in one clean form, or null when it is not allowed.
// It reads numbers with units ("1.5rem"), keywords ("auto") and custom values made of calc(), clamp(), min(), max()
// and var(--name). Anything else (url(), text, ";", "{", "}") is refused, so the value is safe inside a CSS rule.
// resources/js/lib/css-value.ts is the same code in TypeScript; tests/Fixtures/css-values.json checks both.
class CssValueParser
{
    // A number with an optional unit, like 24px, -0.5rem, .75em, 1.2 or 50%.
    protected const string PLAIN = '/^([+-]?)(\d{1,6}(?:\.\d{1,4})?|\.\d{1,4})([a-zA-Z]{1,5}|%)?$/';

    protected const string NUMBER = '/\G(\d{1,6}(?:\.\d{1,4})?|\.\d{1,4})([a-zA-Z]{1,5}|%)?/';

    protected const string FUNCTION = '/\G(calc|clamp|min|max|var)\(/i';

    protected const string CUSTOM_PROPERTY = '/\G--[a-zA-Z_][a-zA-Z0-9_-]{0,59}/';

    /** @var list<array{0: string, 1: string, 2?: string}> tokens of the custom value being read: [type, text, unit] */
    protected array $tokens = [];

    protected int $position = 0;

    protected CssValueRule $rule;

    // True when the value is allowed by the rule.
    public function valid(mixed $value, CssValueRule $rule): bool
    {
        return $this->normalize($value, $rule) !== null;
    }

    // The clean CSS value, or null when it is not allowed. Old plain numbers on a length become pixels, like 24 => "24px".
    public function normalize(mixed $value, CssValueRule $rule): ?string
    {
        if (is_int($value) || (is_float($value) && is_finite($value))) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = trim($value);

        if ($text === '' || strlen($text) > CssUnits::MAX_LENGTH) {
            return null;
        }

        if (in_array(strtolower($text), $rule->keywords, true)) {
            return strtolower($text);
        }

        if (preg_match(self::PLAIN, $text, $match) === 1) {
            return $this->plain($match[1], $match[2], $match[3] ?? '', $rule);
        }

        return $rule->custom ? $this->custom($text, $rule) : null;
    }

    protected function plain(string $sign, string $number, string $unit, CssValueRule $rule): ?string
    {
        $number = str_starts_with($number, '.') ? '0'.$number : $number;
        $zero = (float) $number === 0.0;
        $sign = $sign === '-' && ! $zero ? '-' : '';

        if ($sign === '-' && ! $rule->negative) {
            return null;
        }

        if ($unit === '') {
            return match (true) {
                $rule->unitless => $sign.$number,
                $zero && $rule->type === CssValueType::Length => '0',
                $rule->type === CssValueType::Length && $rule->allowsUnit('px') => $sign.$number.'px',
                default => null,
            };
        }

        $unit = CssUnits::canonical($unit);

        return $unit !== null && $rule->allowsUnit($unit) ? $sign.$number.$unit : null;
    }

    // A custom value: one function call like clamp(1rem, 2vw + 1rem, 3rem), in a clean form.
    protected function custom(string $text, CssValueRule $rule): ?string
    {
        $tokens = $this->tokenize($text);

        if ($tokens === null || $tokens === [] || $tokens[0][0] !== 'function') {
            return null;
        }

        $this->tokens = $tokens;
        $this->position = 0;
        $this->rule = $rule;
        $css = $this->call(1);

        return $css !== null && $this->finished() ? $css : null;
    }

    // True when every token of the custom value was read.
    protected function finished(): bool
    {
        return $this->position === count($this->tokens);
    }

    /** @return list<array{0: string, 1: string, 2?: string}>|null */
    protected function tokenize(string $text): ?array
    {
        $tokens = [];
        $offset = 0;
        $length = strlen($text);

        while ($offset < $length) {
            $char = $text[$offset];
            $previous = $tokens === [] ? null : $tokens[count($tokens) - 1][0];
            $next = $text[$offset + 1] ?? '';

            if (ctype_space($char)) {
                $offset++;
            } elseif (preg_match(self::FUNCTION, $text, $match, 0, $offset) === 1) {
                $tokens[] = ['function', strtolower($match[1])];
                $offset += strlen($match[0]);
            } elseif (preg_match(self::CUSTOM_PROPERTY, $text, $match, 0, $offset) === 1) {
                $tokens[] = ['property', $match[0]];
                $offset += strlen($match[0]);
            } elseif (($char === '-' || $char === '+') && (ctype_digit($next) || $next === '.') && in_array($previous, [null, 'operator', 'open', 'comma', 'function'], true)) {
                if (preg_match(self::NUMBER, $text, $match, 0, $offset + 1) !== 1) {
                    return null;
                }

                $tokens[] = ['number', ($char === '-' ? '-' : '').$match[1], $match[2] ?? ''];
                $offset += 1 + strlen($match[0]);
            } elseif (preg_match(self::NUMBER, $text, $match, 0, $offset) === 1) {
                $tokens[] = ['number', $match[1], $match[2] ?? ''];
                $offset += strlen($match[0]);
            } elseif (in_array($char, ['+', '-', '*', '/'], true)) {
                $tokens[] = ['operator', $char];
                $offset++;
            } elseif ($char === '(' || $char === ')' || $char === ',') {
                $tokens[] = [$char === ',' ? 'comma' : ($char === '(' ? 'open' : 'close'), $char];
                $offset++;
            } else {
                return null;
            }
        }

        return $tokens;
    }

    /** @return array{0: string, 1: string, 2?: string}|null */
    protected function peek(): ?array
    {
        return $this->tokens[$this->position] ?? null;
    }

    protected function take(string $type): bool
    {
        if (($this->peek()[0] ?? null) !== $type) {
            return false;
        }

        $this->position++;

        return true;
    }

    // A function call: calc(a), clamp(a, b, c), min(a, ...), max(a, ...) or var(--name[, a]).
    protected function call(int $depth): ?string
    {
        $token = $this->peek();

        if ($depth > CssUnits::MAX_DEPTH || $token === null || $token[0] !== 'function') {
            return null;
        }

        $this->position++;
        $name = $token[1];

        if ($name === 'var') {
            $property = $this->peek();

            if (! $this->take('property') || $property === null) {
                return null;
            }

            $fallback = null;

            if ($this->take('comma')) {
                $fallback = $this->sum($depth + 1);

                if ($fallback === null) {
                    return null;
                }
            }

            return $this->take('close') ? 'var('.$property[1].($fallback !== null ? ', '.$fallback : '').')' : null;
        }

        $arguments = [];

        do {
            $argument = $this->sum($depth + 1);

            if ($argument === null) {
                return null;
            }

            $arguments[] = $argument;
        } while ($this->take('comma'));

        $count = count($arguments);
        $fits = match ($name) {
            'calc' => $count === 1,
            'clamp' => $count === 3,
            default => true,
        };

        return $fits && $this->take('close') ? $name.'('.implode(', ', $arguments).')' : null;
    }

    // Terms joined with + and -.
    protected function sum(int $depth): ?string
    {
        $css = $this->product($depth);

        while ($css !== null && in_array($this->peek()[1] ?? null, ['+', '-'], true) && $this->peek()[0] === 'operator') {
            $operator = $this->tokens[$this->position++][1];
            $right = $this->product($depth);
            $css = $right === null ? null : $css.' '.$operator.' '.$right;
        }

        return $css;
    }

    // Terms joined with * and /.
    protected function product(int $depth): ?string
    {
        $css = $this->term($depth);

        while ($css !== null && in_array($this->peek()[1] ?? null, ['*', '/'], true) && $this->peek()[0] === 'operator') {
            $operator = $this->tokens[$this->position++][1];
            $right = $this->term($depth);
            $css = $right === null ? null : $css.' '.$operator.' '.$right;
        }

        return $css;
    }

    // A number, a function call or a sum in brackets.
    protected function term(int $depth): ?string
    {
        $token = $this->peek();

        if ($token === null) {
            return null;
        }

        if ($token[0] === 'function') {
            return $this->call($depth);
        }

        if ($token[0] === 'open') {
            $this->position++;
            $inner = $depth >= CssUnits::MAX_DEPTH ? null : $this->sum($depth + 1);

            return $inner !== null && $this->take('close') ? '('.$inner.')' : null;
        }

        if ($token[0] !== 'number') {
            return null;
        }

        $this->position++;
        $number = $token[1];
        $number = preg_replace('/^(-?)\./', '${1}0.', $number) ?? $number;
        $unit = $token[2] ?? '';

        if ($unit === '') {
            return $number;
        }

        $unit = CssUnits::canonical($unit);

        return $unit !== null && $this->rule->allowsUnit($unit) ? $number.$unit : null;
    }
}
