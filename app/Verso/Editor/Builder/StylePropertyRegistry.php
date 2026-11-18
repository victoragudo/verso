<?php

namespace App\Verso\Editor\Builder;

use App\Verso\Core\Css\CssValueRule;

/**
 * Keeps the style options of the Styles panel. The style schema and compiler read them.
 *
 * @extends Registry<StyleProperty>
 */
class StylePropertyRegistry extends Registry
{
    public function register(StyleProperty $property): void
    {
        $this->put($property->path, $property);
    }

    /** @return list<StyleProperty> */
    public function all(): array
    {
        return array_values($this->items);
    }

    /**
     * Path => [CSS property, value kind, value rule], the shape the style schema uses.
     *
     * @return array<string, array{0: string, 1: string, 2: CssValueRule|null}>
     */
    public function options(): array
    {
        return array_map(fn (StyleProperty $property): array => [$property->cssProperty, $property->kind, $property->value], $this->items);
    }

    /** @return list<array{path: string, kind: string, css: string, label: string, group: string, control: string, options: list<string>, value: array<string, mixed>|null}> */
    public function toArray(): array
    {
        return array_map(fn (StyleProperty $property): array => $property->toArray(), $this->all());
    }
}
