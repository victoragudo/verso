<?php

namespace App\Verso\Editor\CustomCode;

// Cleans a whole stylesheet written by an admin, so it can sit in a <style> tag safely.
// It keeps rules and @media, @supports and @container groups. It removes HTML, comments,
// @import and other at-rules that load or change things, and declarations with script-like values.
class StylesheetSanitizer
{
    // Words that must never be in a declaration value.
    protected const array BLOCKED = ['expression(', 'javascript:', 'vbscript:', 'behavior', '-moz-binding', 'data:text/html'];

    // At-rules people may use. Others, like @import or @charset, are removed.
    protected const array ALLOWED_AT_RULES = ['media', 'supports', 'container', 'keyframes', 'font-face', 'layer'];

    public function __construct(protected int $maxLength) {}

    public function clean(?string $css): string
    {
        $css = mb_substr((string) $css, 0, $this->maxLength);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $css = str_replace('<', '', $css);
        $css = (string) preg_replace('/\\\\(?![0-9a-f]{1,6}\s?)/i', '', $css);
        $css = (string) preg_replace_callback(
            '/@([a-z-]+)[^{;]*(;|\{)/i',
            fn (array $match): string => in_array(strtolower($match[1]), self::ALLOWED_AT_RULES, true) ? $match[0] : ($match[2] === '{' ? '@removed{' : ''),
            $css,
        );
        $css = (string) preg_replace('/@removed\{[^{}]*(\{[^{}]*\}[^{}]*)*\}/', '', $css);

        // Removes single declarations with dangerous values, keeping the rest of the rule.
        $css = (string) preg_replace_callback(
            '/(?<=[{;])\s*([-a-z]+\s*:[^;{}]*);?/i',
            fn (array $match): string => $this->blocked($match[1]) ? '' : $match[0],
            $css,
        );

        return trim($this->balanced($css));
    }

    protected function blocked(string $declaration): bool
    {
        $text = strtolower((string) preg_replace('/\s+/', '', $declaration));

        foreach (self::BLOCKED as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return preg_match('/url\(\s*["\']?\s*(?!https?:|\/|#|data:image\/)/i', $declaration) === 1;
    }

    // Drops the text after a stray closing brace and closes groups left open.
    protected function balanced(string $css): string
    {
        $depth = 0;
        $result = '';

        foreach (mb_str_split($css) as $char) {
            if ($char === '}') {
                if ($depth === 0) {
                    continue;
                }

                $depth--;
            } elseif ($char === '{') {
                $depth++;
            }

            $result .= $char;
        }

        return $result.str_repeat('}', $depth);
    }
}
