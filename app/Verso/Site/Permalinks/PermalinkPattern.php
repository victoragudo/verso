<?php

namespace App\Verso\Site\Permalinks;

// A URL structure like "/blog/{slug}" or "/{year}/{month}/{slug}". It builds paths and matches them back.
final readonly class PermalinkPattern
{
    // Tokens and the text each one matches in a URL.
    public const TOKENS = [
        'slug' => '[^/]+',
        'id' => '[0-9]+',
        'year' => '[0-9]{4}',
        'month' => '[0-9]{2}',
        'day' => '[0-9]{2}',
        'category' => '[^/]+',
    ];

    // In a hierarchical type (like pages) {slug} holds the whole path, like "about/team".
    protected const NESTED_SLUG = '[^/]+(?:/[^/]+)*';

    public string $pattern;

    public function __construct(string $pattern)
    {
        $this->pattern = self::normalize($pattern);
    }

    // Keeps one leading slash, no trailing slash and no empty parts: " blog//{slug}/ " becomes "/blog/{slug}".
    public static function normalize(string $pattern): string
    {
        $parts = array_filter(explode('/', trim($pattern)), fn (string $part): bool => trim($part) !== '');

        return '/'.implode('/', array_map(trim(...), $parts));
    }

    /** @return list<string> the parts between slashes */
    public function segments(): array
    {
        return $this->pattern === '/' ? [] : explode('/', ltrim($this->pattern, '/'));
    }

    /** @return list<string> every {token} name, in order */
    public function tokens(): array
    {
        preg_match_all('/\{([^}]*)\}/', $this->pattern, $matches);

        return $matches[1];
    }

    public function has(string $token): bool
    {
        return in_array($token, $this->tokens(), true);
    }

    /** @return list<string> tokens that Verso does not know */
    public function unknownTokens(): array
    {
        return array_values(array_diff($this->tokens(), array_keys(self::TOKENS)));
    }

    // The fixed start before the first token, like "blog" for "/blog/{slug}". Empty when the pattern starts with a token.
    public function staticPrefix(): string
    {
        $prefix = [];

        foreach ($this->segments() as $segment) {
            if (str_contains($segment, '{')) {
                break;
            }

            $prefix[] = $segment;
        }

        return implode('/', $prefix);
    }

    // The first part of the path, like "blog", when it holds no token.
    public function firstStaticSegment(): ?string
    {
        $first = $this->segments()[0] ?? null;

        return $first === null || str_contains($first, '{') ? null : $first;
    }

    // More fixed parts first, so "/blog/{slug}" is tried before "/{slug}".
    public function specificity(): int
    {
        $fixed = count(array_filter($this->segments(), fn (string $segment): bool => ! str_contains($segment, '{')));

        return $fixed * 10 + count($this->segments());
    }

    // A regular expression for a path without the leading slash.
    public function regex(bool $hierarchical = false): string
    {
        $parts = preg_split('/(\{[a-z]+\})/', ltrim($this->pattern, '/'), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '';

        foreach ($parts as $part) {
            $token = preg_match('/^\{([a-z]+)\}$/', $part, $match) === 1 ? $match[1] : null;

            $regex .= $token === null
                ? preg_quote($part, '#')
                : '(?P<'.$token.'>'.($hierarchical && $token === 'slug' ? self::NESTED_SLUG : (self::TOKENS[$token] ?? '[^/]+')).')';
        }

        return '#^'.$regex.'$#';
    }

    /**
     * The token values of a path, or null when the path does not fit.
     *
     * @return array<string, string>|null
     */
    public function match(string $path, bool $hierarchical = false): ?array
    {
        if (preg_match($this->regex($hierarchical), trim($path, '/'), $matches) !== 1) {
            return null;
        }

        return array_filter($matches, fn (int|string $key): bool => is_string($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * The path with every token replaced, like "/2026/11/harbor-life".
     *
     * @param  array<string, string>  $values
     */
    public function build(array $values): string
    {
        return (string) preg_replace_callback(
            '/\{([a-z]+)\}/',
            fn (array $match): string => $values[$match[1]] ?? '',
            $this->pattern,
        );
    }
}
