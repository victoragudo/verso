<?php

namespace App\Verso\Theme\Templates;

use Illuminate\Support\Str;

// A ready-made group of blocks a theme ships, inserted from the editor "Patterns" button.
final readonly class BlockPattern
{
    // Pattern names: lowercase letters, digits, dashes and underscores.
    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    // Category used when the pattern file gives none.
    public const DEFAULT_CATEGORY = 'General';

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @param  list<string>  $keywords
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $category,
        public array $blocks,
        public string $description = '',
        public array $keywords = [],
    ) {}

    /**
     * Build a pattern from a decoded JSON file. Returns null when it is broken.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data, string $fallbackName): ?self
    {
        $name = is_string($data['name'] ?? null) ? trim($data['name']) : $fallbackName;
        $blocks = $data['blocks'] ?? null;

        if (preg_match(self::NAME_PATTERN, $name) !== 1 || ! is_array($blocks) || $blocks === [] || ! self::validBlocks($blocks)) {
            return null;
        }

        /** @var list<array<string, mixed>> $blocks */
        $blocks = array_values($blocks);

        return new self(
            name: $name,
            title: self::text($data, 'title') ?: Str::headline($name),
            category: self::text($data, 'category') ?: self::DEFAULT_CATEGORY,
            blocks: $blocks,
            description: self::text($data, 'description'),
            keywords: is_array($data['keywords'] ?? null) ? array_values(array_filter($data['keywords'], is_string(...))) : [],
        );
    }

    public function withText(string $title, string $category, string $description): self
    {
        return new self(
            name: $this->name,
            title: $title,
            category: $category,
            blocks: $this->blocks,
            description: $description,
            keywords: $this->keywords,
        );
    }

    /**
     * Block types used by the pattern, nested ones included.
     *
     * @return list<string>
     */
    public function blockTypes(): array
    {
        return array_values(array_unique(self::typesOf($this->blocks)));
    }

    /**
     * @return array{name: string, title: string, category: string, description: string, keywords: list<string>, blocks: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'blocks' => $this->blocks,
        ];
    }

    // Every block must be an object with a type, and children must be a list of blocks too.
    /** @param array<mixed> $blocks */
    protected static function validBlocks(array $blocks): bool
    {
        if (! array_is_list($blocks)) {
            return false;
        }

        foreach ($blocks as $block) {
            if (! is_array($block) || ! is_string($block['type'] ?? null) || $block['type'] === '') {
                return false;
            }

            if (array_key_exists('children', $block) && (! is_array($block['children']) || ! self::validBlocks($block['children']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $blocks
     * @return list<string>
     */
    protected static function typesOf(array $blocks): array
    {
        $types = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $types[] = (string) ($block['type'] ?? '');
            array_push($types, ...self::typesOf((array) ($block['children'] ?? [])));
        }

        return $types;
    }

    /** @param array<mixed> $data */
    protected static function text(array $data, string $key): string
    {
        return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
    }
}
