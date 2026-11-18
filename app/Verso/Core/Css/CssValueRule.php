<?php

namespace App\Verso\Core\Css;

use InvalidArgumentException;

// Which values one CSS property accepts: the units, the keywords, plain numbers, negative numbers and custom values.
final readonly class CssValueRule
{
    // Keywords and limits of common properties. Other length properties accept any length.
    protected const array PROPERTIES = [
        'width' => ['keywords' => ['auto', 'fit-content', 'min-content', 'max-content'], 'negative' => false],
        'height' => ['keywords' => ['auto', 'fit-content', 'min-content', 'max-content'], 'negative' => false],
        'min-width' => ['keywords' => ['auto', 'fit-content', 'min-content', 'max-content'], 'negative' => false],
        'min-height' => ['keywords' => ['auto', 'fit-content', 'min-content', 'max-content'], 'negative' => false],
        'max-width' => ['keywords' => ['none', 'fit-content', 'min-content', 'max-content'], 'negative' => false],
        'max-height' => ['keywords' => ['none', 'fit-content', 'min-content', 'max-content'], 'negative' => false],
        'flex-basis' => ['keywords' => ['auto', 'fit-content', 'min-content', 'max-content'], 'negative' => false],
        'margin' => ['keywords' => ['auto']],
        'padding' => ['negative' => false],
        'gap' => ['keywords' => ['normal'], 'negative' => false],
        'row-gap' => ['keywords' => ['normal'], 'negative' => false],
        'column-gap' => ['keywords' => ['normal'], 'negative' => false],
        'font-size' => ['negative' => false],
        'line-height' => ['keywords' => ['normal'], 'negative' => false, 'unitless' => true],
        'letter-spacing' => ['keywords' => ['normal']],
        'word-spacing' => ['keywords' => ['normal']],
        'border-width' => ['negative' => false],
        'border-radius' => ['negative' => false],
        'outline-width' => ['negative' => false],
        'top' => ['keywords' => ['auto']],
        'right' => ['keywords' => ['auto']],
        'bottom' => ['keywords' => ['auto']],
        'left' => ['keywords' => ['auto']],
        'inset' => ['keywords' => ['auto']],
    ];

    /** @var list<string> */
    public array $units;

    /** @var list<string> */
    public array $keywords;

    /**
     * @param  list<string>|null  $units  allowed units, null for every unit of the type
     * @param  list<string>  $keywords  allowed keywords, from CssUnits::KEYWORDS
     */
    public function __construct(
        public CssValueType $type = CssValueType::Length,
        ?array $units = null,
        array $keywords = [],
        public bool $unitless = false,
        public bool $negative = true,
        public bool $custom = true,
    ) {
        $known = CssUnits::forType($type);
        $units ??= $known;

        if (array_diff($units, $known) !== []) {
            throw new InvalidArgumentException('Unknown '.$type->value.' units: '.implode(', ', array_diff($units, $known)).'.');
        }

        if (array_diff($keywords, CssUnits::KEYWORDS) !== []) {
            throw new InvalidArgumentException('Unknown keywords: '.implode(', ', array_diff($keywords, CssUnits::KEYWORDS)).'.');
        }

        $this->units = array_values(array_intersect($known, $units));
        $this->keywords = array_values(array_intersect(CssUnits::KEYWORDS, $keywords));
    }

    // The rule of a style kind and CSS property, like line-height that also takes plain numbers.
    public static function forProperty(string $cssProperty, string $kind = 'length'): self
    {
        $type = CssValueType::tryFrom($kind) ?? CssValueType::Length;

        if ($type !== CssValueType::Length) {
            return new self($type);
        }

        $base = (string) preg_replace('/-(top|right|bottom|left|block|inline|block-start|block-end|inline-start|inline-end)$/', '', $cssProperty);
        $base = str_starts_with($base, 'border-') && str_ends_with($base, '-width') ? 'border-width' : $base;
        $base = str_starts_with($base, 'border-') && str_ends_with($base, '-radius') ? 'border-radius' : $base;
        $settings = self::PROPERTIES[$cssProperty] ?? self::PROPERTIES[$base] ?? [];

        return new self(
            keywords: $settings['keywords'] ?? [],
            unitless: $settings['unitless'] ?? false,
            negative: $settings['negative'] ?? true,
        );
    }

    /**
     * A rule from stored or theme.json data. Unknown units and keywords are left out.
     *
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = CssValueType::tryFrom(is_string($data['type'] ?? null) ? $data['type'] : '') ?? CssValueType::Length;
        $known = CssUnits::forType($type);
        $units = is_array($data['units'] ?? null) ? array_values(array_intersect($known, array_map(strval(...), $data['units']))) : null;
        $keywords = is_array($data['keywords'] ?? null) ? array_values(array_intersect(CssUnits::KEYWORDS, array_map(strval(...), $data['keywords']))) : [];

        return new self(
            type: $type,
            units: $units,
            keywords: $keywords,
            unitless: (bool) ($data['unitless'] ?? $type === CssValueType::Number),
            negative: (bool) ($data['negative'] ?? true),
            custom: (bool) ($data['custom'] ?? true),
        );
    }

    /**
     * The same rule with fewer units, like a theme limit. Units not in the list are dropped.
     *
     * @param  list<string>  $units
     */
    public function onlyUnits(array $units): self
    {
        return new self($this->type, array_values(array_intersect($this->units, $units)), $this->keywords, $this->unitless, $this->negative, $this->custom);
    }

    public function allowsUnit(string $unit): bool
    {
        return in_array($unit, $this->units, true);
    }

    /** @return array{type: string, units: list<string>, keywords: list<string>, unitless: bool, negative: bool, custom: bool} */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'units' => $this->units,
            'keywords' => $this->keywords,
            'unitless' => $this->unitless,
            'negative' => $this->negative,
            'custom' => $this->custom,
        ];
    }
}
