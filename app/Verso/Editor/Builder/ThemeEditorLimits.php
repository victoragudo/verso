<?php

namespace App\Verso\Editor\Builder;

use App\Verso\Core\Css\CssUnits;
use App\Verso\Core\Css\CssValueParser;
use App\Verso\Core\Css\CssValueRule;
use App\Verso\Theme\ThemeManager;

// Limits the active theme puts on the editor through "editor" in theme.json, so people stay inside the theme design.
// Example: {"editor": {"blocks": ["paragraph", "heading"], "colors": ["#1f6f5c"], "fonts": ["body"], "spacing": ["1rem", "2rem"], "units": ["px", "rem", "%"]}}
class ThemeEditorLimits
{
    // A hex color like #1f6f5c.
    protected const string HEX = '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    // A short name like "paragraph", "body" or "my-plugin/card".
    protected const string NAME = '/^[a-z0-9][a-z0-9\/_-]{0,80}$/';

    public function __construct(protected ThemeManager $themes, protected CssValueParser $values) {}

    /** @return list<string>|null block names the theme allows, null when it allows every block */
    public function blocks(): ?array
    {
        return $this->list('blocks', self::NAME);
    }

    /** @return list<string>|null */
    public function colors(): ?array
    {
        return $this->list('colors', self::HEX);
    }

    /** @return list<string>|null */
    public function fonts(): ?array
    {
        return $this->list('fonts', self::NAME);
    }

    /** @return list<string>|null spacing suggestions, any safe size value */
    public function spacing(): ?array
    {
        $values = $this->raw('spacing');

        if ($values === null) {
            return null;
        }

        $rule = new CssValueRule(negative: false);

        return array_values(array_filter(array_map(fn (string $value): ?string => $this->values->normalize($value, $rule), $values)));
    }

    /** @return list<string>|null units the unit picker offers, null when every unit is allowed */
    public function units(): ?array
    {
        $values = $this->raw('units');

        return $values === null ? null : array_values(array_unique(array_filter(array_map(CssUnits::canonical(...), $values))));
    }

    /** @return array{blocks: list<string>|null, colors: list<string>|null, fonts: list<string>|null, spacing: list<string>|null, units: list<string>|null} */
    public function toArray(): array
    {
        return ['blocks' => $this->blocks(), 'colors' => $this->colors(), 'fonts' => $this->fonts(), 'spacing' => $this->spacing(), 'units' => $this->units()];
    }

    /** @return list<string>|null the valid values of one list, null when theme.json does not set it */
    protected function list(string $key, string $pattern): ?array
    {
        $values = $this->raw($key);

        return $values === null ? null : array_values(array_filter($values, fn (string $value): bool => preg_match($pattern, $value) === 1));
    }

    /** @return list<string>|null the trimmed text values of one list, null when theme.json does not set it */
    protected function raw(string $key): ?array
    {
        $values = $this->themes->active()->editor[$key] ?? null;

        if (! is_array($values)) {
            return null;
        }

        return array_values(array_map(fn (mixed $value): string => is_string($value) ? trim($value) : '', $values));
    }
}
