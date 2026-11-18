<?php

namespace App\Verso\Easy\Sections;

// The words, items, pictures and button of one ready section. Empty parts take the defaults of the section.
final readonly class SectionContent
{
    /**
     * @param  list<array<string, mixed>>  $items  cards, questions, people or prices, like ['title' => ..., 'text' => ...]
     * @param  list<array<string, mixed>>  $images  image attrs (mediaId, src, alt, width, height)
     * @param  array{label: string, url: string}|null  $button
     * @param  array<string, mixed>  $extra  values only one section uses, like an address or map coordinates
     */
    public function __construct(
        public string $heading = '',
        public string $text = '',
        public array $items = [],
        public array $images = [],
        public ?array $button = null,
        public array $extra = [],
    ) {}

    // Reads content from a plain array, like the one a starter site or a request gives.
    /** @param array<string, mixed> $data */
    public static function from(array $data): self
    {
        $button = is_array($data['button'] ?? null) ? $data['button'] : null;

        return new self(
            heading: is_string($data['heading'] ?? null) ? trim($data['heading']) : '',
            text: is_string($data['text'] ?? null) ? trim($data['text']) : '',
            items: self::rows($data['items'] ?? []),
            images: self::rows($data['images'] ?? []),
            button: is_string($button['label'] ?? null) && is_string($button['url'] ?? null) && trim($button['label']) !== ''
                ? ['label' => trim($button['label']), 'url' => trim($button['url'])]
                : null,
            extra: is_array($data['extra'] ?? null) ? $data['extra'] : [],
        );
    }

    // This content with every empty part filled from the defaults.
    public function withDefaults(self $defaults): self
    {
        return new self(
            heading: $this->heading !== '' ? $this->heading : $defaults->heading,
            text: $this->text !== '' ? $this->text : $defaults->text,
            items: $this->items !== [] ? $this->items : $defaults->items,
            images: $this->images !== [] ? $this->images : $defaults->images,
            button: $this->button ?? $defaults->button,
            extra: [...$defaults->extra, ...$this->extra],
        );
    }

    // A text value of the extra part, or the fallback.
    public function extraText(string $key, string $fallback = ''): string
    {
        $value = $this->extra[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : $fallback;
    }

    /**
     * @return array{heading: string, text: string, items: list<array<string, mixed>>, images: list<array<string, mixed>>, button: array{label: string, url: string}|null, extra: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'heading' => $this->heading,
            'text' => $this->text,
            'items' => $this->items,
            'images' => $this->images,
            'button' => $this->button,
            'extra' => $this->extra,
        ];
    }

    /**
     * Only the rows that are objects, as a list.
     *
     * @return list<array<string, mixed>>
     */
    protected static function rows(mixed $rows): array
    {
        $list = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $list[] = $row;
            }
        }

        return $list;
    }
}
