<?php

namespace App\Verso\Editor\Support;

// One block read from the stored JSON, with typed helpers for its attributes.
final readonly class BlockData
{
    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<array<mixed>>  $children
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $attrs = [],
        public string $content = '',
        public array $children = [],
        public int $depth = 0,
    ) {}

    // Builds a block from raw stored data. Wrong values become safe defaults.
    /** @param array<mixed> $block */
    public static function from(array $block, int $depth = 0): self
    {
        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $children = is_array($block['children'] ?? null) ? $block['children'] : [];

        return new self(
            id: is_scalar($block['id'] ?? null) ? (string) $block['id'] : '',
            type: is_string($block['type'] ?? null) ? $block['type'] : '',
            attrs: array_filter($attrs, is_string(...), ARRAY_FILTER_USE_KEY),
            content: is_string($block['content'] ?? null) ? $block['content'] : '',
            children: array_values(array_filter($children, is_array(...))),
            depth: $depth,
        );
    }

    public function attr(string $key, mixed $default = null): mixed
    {
        return $this->attrs[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->attrs[$key] ?? null;

        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : $default;
    }

    public function int(string $key, ?int $default = null): ?int
    {
        $value = $this->attrs[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->attrs[$key] ?? null;

        if ($value === null || is_bool($value)) {
            return $value ?? $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * A list attribute, or an empty list when it is missing or not a list.
     *
     * @return list<mixed>
     */
    public function list(string $key): array
    {
        $value = $this->attrs[$key] ?? null;

        return is_array($value) ? array_values($value) : [];
    }
}
