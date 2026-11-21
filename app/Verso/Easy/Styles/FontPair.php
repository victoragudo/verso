<?php

namespace App\Verso\Easy\Styles;

use InvalidArgumentException;

// Two fonts that look good together: one for headings and one for the text. Keys come from config('verso.themes.fonts').
final readonly class FontPair
{
    // Keys look like "classic" or "modern-serif".
    public const KEY_PATTERN = '/^[a-z][a-z0-9-]{0,40}$/';

    public const DEFAULT_SAMPLE = 'Fresh bread every morning, baked by the harbor.';

    public function __construct(
        public string $key,
        public string $label,
        public string $heading,
        public string $body,
        public string $sample = self::DEFAULT_SAMPLE,
        public int $order = 100,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("The font pair key \"{$key}\" must start with a letter and use only a-z, 0-9 and -.");
        }
    }

    /**
     * A font pair from theme.json data (easy.fontPairs.<key>). Returns null when a font is missing.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(string $key, array $data): ?self
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1 || ! is_string($data['heading'] ?? null) || ! is_string($data['body'] ?? null)) {
            return null;
        }

        return new self(
            key: $key,
            label: is_string($data['label'] ?? null) && $data['label'] !== '' ? mb_substr($data['label'], 0, 60) : ucfirst($key),
            heading: $data['heading'],
            body: $data['body'],
            sample: is_string($data['sample'] ?? null) && $data['sample'] !== '' ? mb_substr($data['sample'], 0, 120) : self::DEFAULT_SAMPLE,
            order: is_int($data['order'] ?? null) ? $data['order'] : 100,
        );
    }

    /**
     * @return array{key: string, label: string, heading: string, body: string, sample: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'heading' => $this->heading,
            'body' => $this->body,
            'sample' => $this->sample,
        ];
    }
}
