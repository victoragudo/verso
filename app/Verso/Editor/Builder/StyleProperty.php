<?php

namespace App\Verso\Editor\Builder;

use App\Verso\Core\Css\CssValueRule;
use App\Verso\Editor\Styles\BlockStyleSchema;
use InvalidArgumentException;

// One style option of the Styles panel, like "spacing.padding.top".
// The path is where the value lives in attrs.design. The kind says which values are safe.
// Size kinds (length, angle, time) also carry a value rule: the units, keywords and plain numbers they accept.
final readonly class StyleProperty
{
    // Value kinds that need no fixed list of words.
    public const array FREE_KINDS = ['length', 'angle', 'time', 'color', 'overlay', 'number', 'weight', 'columns', 'sticky', 'hidden', 'token', 'gradient', 'image', 'focal', 'zIndex'];

    // Kinds checked by a value rule (App\Verso\Core\Css\CssValueRule).
    public const array VALUE_KINDS = ['length', 'angle', 'time'];

    // Units, keywords and numbers a size value accepts. Null for kinds that are not sizes.
    public ?CssValueRule $value;

    // Allowed CSS property names: lower case words joined by dashes.
    protected const string CSS_PROPERTY = '/^-?[a-z]+(-[a-z]+)*$/';

    // Allowed paths: one to three camelCase words joined by dots.
    protected const string PATH = '/^[a-z][a-zA-Z0-9]*(\.[a-z][a-zA-Z0-9]*){0,2}$/';

    public function __construct(
        public string $path,
        public string $cssProperty,
        public string $kind,
        public string $label,
        public string $group,
        public string $control,
        ?CssValueRule $value = null,
    ) {
        if (preg_match(self::PATH, $path) !== 1) {
            throw new InvalidArgumentException("The style path [{$path}] is not valid.");
        }

        if (preg_match(self::CSS_PROPERTY, $cssProperty) !== 1) {
            throw new InvalidArgumentException("The CSS property [{$cssProperty}] is not valid.");
        }

        if (! in_array($kind, self::FREE_KINDS, true) && ! isset(BlockStyleSchema::KEYWORDS[$kind])) {
            throw new InvalidArgumentException("The style kind [{$kind}] is unknown.");
        }

        $this->value = in_array($kind, self::VALUE_KINDS, true) ? $value ?? CssValueRule::forProperty($cssProperty, $kind) : null;
    }

    // A property for a core option, with the control guessed from its kind and the label, when none is given, from its path.
    public static function core(string $path, string $cssProperty, string $kind, ?string $label = null): self
    {
        $words = explode('.', $path);

        return new self(
            path: $path,
            cssProperty: $cssProperty,
            kind: $kind,
            label: $label ?? self::labelFromPath($words),
            group: $words[0],
            control: self::controlFor($kind),
        );
    }

    /** @param  list<string>  $words */
    protected static function labelFromPath(array $words): string
    {
        $last = (string) end($words);
        $label = ucfirst(strtolower((string) preg_replace('/(?<!^)[A-Z]/', ' $0', $last)));

        return count($words) === 3 ? ucfirst($words[1]).' '.strtolower($label) : $label;
    }

    // The editor control that fits a value kind.
    public static function controlFor(string $kind): string
    {
        return match ($kind) {
            'length', 'angle', 'time' => 'length',
            'color', 'overlay' => 'color',
            'number', 'weight', 'columns', 'zIndex' => 'number',
            'gradient' => 'gradient',
            'image' => 'image',
            'focal' => 'focal',
            'sticky', 'hidden' => 'toggle',
            'token' => 'token',
            default => 'select',
        };
    }

    /** @return list<string> the stored words of a keyword kind, empty for other kinds */
    public function options(): array
    {
        return array_keys(BlockStyleSchema::KEYWORDS[$this->kind] ?? []);
    }

    /** @return array{path: string, kind: string, css: string, label: string, group: string, control: string, options: list<string>, value: array<string, mixed>|null} */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'kind' => $this->kind,
            'css' => $this->cssProperty,
            'label' => $this->label,
            'group' => $this->group,
            'control' => $this->control,
            'options' => $this->options(),
            'value' => $this->value?->toArray(),
        ];
    }
}
