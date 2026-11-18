<?php

namespace App\Verso\Easy\Styles;

use InvalidArgumentException;

// A ready look for the whole site, like "Calm" or "Bold": corners, spacing and a suggested font pair.
final readonly class StylePreset
{
    // Keys look like "calm" or "harbor-bold".
    public const KEY_PATTERN = '/^[a-z][a-z0-9-]{0,40}$/';

    /**
     * @param  array<string, string>  $tokens  global style token id ("radius.base") => value ("12px")
     * @param  string|null  $fontPair  key of the font pair picked together with this preset
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $tokens = [],
        public ?string $fontPair = null,
        public int $order = 100,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("The style preset key \"{$key}\" must start with a letter and use only a-z, 0-9 and -.");
        }
    }

    /**
     * A preset from theme.json data (easy.presets.<key>). Missing parts come from $base, when given.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(string $key, array $data, ?self $base = null): self
    {
        $tokens = array_filter(is_array($data['tokens'] ?? null) ? $data['tokens'] : [], fn (mixed $value, mixed $id): bool => is_string($id) && is_string($value), ARRAY_FILTER_USE_BOTH);

        return new self(
            key: $key,
            label: is_string($data['label'] ?? null) && $data['label'] !== '' ? mb_substr($data['label'], 0, 60) : ($base->label ?? ucfirst($key)),
            description: is_string($data['description'] ?? null) ? mb_substr($data['description'], 0, 200) : ($base->description ?? ''),
            tokens: [...($base->tokens ?? []), ...$tokens],
            fontPair: is_string($data['fontPair'] ?? null) ? $data['fontPair'] : $base?->fontPair,
            order: is_int($data['order'] ?? null) ? $data['order'] : ($base->order ?? 100),
        );
    }

    /**
     * The same preset with other token values.
     *
     * @param  array<string, string>  $tokens
     */
    public function withTokens(array $tokens): self
    {
        return new self($this->key, $this->label, $this->description, $tokens, $this->fontPair, $this->order);
    }

    /**
     * @return array{key: string, label: string, description: string, tokens: array<string, string>, fontPair: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'tokens' => $this->tokens,
            'fontPair' => $this->fontPair,
        ];
    }
}
