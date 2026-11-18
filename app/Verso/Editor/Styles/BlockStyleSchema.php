<?php

namespace App\Verso\Editor\Styles;

use App\Verso\Core\Css\CssValueParser;
use App\Verso\Core\Css\CssValueRule;
use App\Verso\Editor\Builder\StyleProperty;
use App\Verso\Editor\Builder\StylePropertyRegistry;
use App\Verso\I18n\Translate;

// The style options every block can carry in attrs.design ("style" is taken by the button look). Each option maps to one CSS property and one value kind.
// A value is a plain value, or responsive: {"base": ..., "tablet": ..., "mobile": ...}.
class BlockStyleSchema
{
    // Attr that holds the style options of a block.
    public const ATTR = 'design';

    // Attr that holds the admin only custom CSS of a block.
    public const CUSTOM_CSS_ATTR = 'customCss';

    // Attr that names a saved style preset, like "card". The preset styles go under the block's own styles.
    public const PRESET_ATTR = 'preset';

    // Option path that hides a block, like {"visibility": {"hidden": true}}.
    public const HIDDEN_PATH = 'visibility.hidden';

    // Screen sizes a responsive value can name. "base" is every screen.
    public const DEVICES = ['base', 'tablet', 'mobile'];

    // Option path in attrs.design => [CSS property, value kind]. Size kinds get their value rule from CssValueRule::forProperty().
    public const OPTIONS = [
        'spacing.margin.top' => ['margin-top', 'length'],
        'spacing.margin.right' => ['margin-right', 'length'],
        'spacing.margin.bottom' => ['margin-bottom', 'length'],
        'spacing.margin.left' => ['margin-left', 'length'],
        'spacing.padding.top' => ['padding-top', 'length'],
        'spacing.padding.right' => ['padding-right', 'length'],
        'spacing.padding.bottom' => ['padding-bottom', 'length'],
        'spacing.padding.left' => ['padding-left', 'length'],
        'size.width' => ['width', 'length'],
        'size.minWidth' => ['min-width', 'length'],
        'size.maxWidth' => ['max-width', 'length'],
        'size.height' => ['height', 'length'],
        'size.minHeight' => ['min-height', 'length'],
        'size.maxHeight' => ['max-height', 'length'],
        'layout.display' => ['display', 'display'],
        'layout.direction' => ['flex-direction', 'direction'],
        'layout.wrap' => ['flex-wrap', 'wrap'],
        'layout.gap' => ['gap', 'length'],
        'layout.align' => ['align-items', 'align'],
        'layout.justify' => ['justify-content', 'justify'],
        'layout.columns' => ['grid-template-columns', 'columns'],
        'typography.fontFamily' => ['font-family', 'token'],
        'typography.fontSize' => ['font-size', 'length'],
        'typography.fontWeight' => ['font-weight', 'weight'],
        'typography.lineHeight' => ['line-height', 'length'],
        'typography.letterSpacing' => ['letter-spacing', 'length'],
        'typography.transform' => ['text-transform', 'transform'],
        'color.text' => ['color', 'color'],
        'color.background' => ['background-color', 'color'],
        'color.gradient' => ['background-image', 'gradient'],
        'color.overlay' => ['box-shadow', 'overlay'],
        'background.image' => ['background-image', 'image'],
        'background.position' => ['background-position', 'focal'],
        'background.size' => ['background-size', 'bgSize'],
        'background.repeat' => ['background-repeat', 'bgRepeat'],
        'background.attachment' => ['background-attachment', 'bgAttachment'],
        'border.width' => ['border-width', 'length'],
        'border.style' => ['border-style', 'borderStyle'],
        'border.radius' => ['border-radius', 'length'],
        'border.color' => ['border-color', 'color'],
        'shadow' => ['box-shadow', 'token'],
        'position.sticky' => ['position', 'sticky'],
        'position.top' => ['top', 'length'],
        'position.right' => ['right', 'length'],
        'position.bottom' => ['bottom', 'length'],
        'position.left' => ['left', 'length'],
        'position.zIndex' => ['z-index', 'zIndex'],
        'visibility.hidden' => ['display', 'hidden'],
    ];

    // Fixed choices of the keyword kinds. Keys are the stored words, values the CSS words.
    public const KEYWORDS = [
        'display' => ['block' => 'block', 'flex' => 'flex', 'grid' => 'grid'],
        'direction' => ['row' => 'row', 'column' => 'column', 'row-reverse' => 'row-reverse', 'column-reverse' => 'column-reverse'],
        'wrap' => ['wrap' => 'wrap', 'nowrap' => 'nowrap'],
        'align' => ['start' => 'start', 'center' => 'center', 'end' => 'end', 'stretch' => 'stretch', 'baseline' => 'baseline'],
        'justify' => ['start' => 'start', 'center' => 'center', 'end' => 'end', 'between' => 'space-between', 'around' => 'space-around', 'evenly' => 'space-evenly'],
        'transform' => ['none' => 'none', 'uppercase' => 'uppercase', 'lowercase' => 'lowercase', 'capitalize' => 'capitalize'],
        'borderStyle' => ['none' => 'none', 'solid' => 'solid', 'dashed' => 'dashed', 'dotted' => 'dotted'],
        'bgSize' => ['cover' => 'cover', 'contain' => 'contain', 'auto' => 'auto'],
        'bgRepeat' => ['no-repeat' => 'no-repeat', 'repeat' => 'repeat', 'repeat-x' => 'repeat-x', 'repeat-y' => 'repeat-y'],
        'bgAttachment' => ['scroll' => 'scroll', 'fixed' => 'fixed'],
    ];

    // A design token like "var:spacing.md" or "var:color.accent". It becomes var(--verso-spacing-md).
    protected const TOKEN = '/^var:([a-z][a-z0-9]*)\.([a-z0-9][a-z0-9-]{0,40})$/';

    // Value kinds that may use a design token.
    protected const TOKEN_KINDS = ['length', 'angle', 'time', 'color', 'overlay', 'number', 'token', 'gradient'];

    // A hex color like #1f6f5c or #1f6f5c80.
    protected const HEX = '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    protected CssValueParser $values;

    // With a registry, the options come from it, so plugins can add their own. Without one, the core options are used.
    public function __construct(protected ?StylePropertyRegistry $properties = null)
    {
        $this->values = new CssValueParser;
    }

    /**
     * Every style option: path => [CSS property, value kind, value rule of size kinds].
     *
     * @return array<string, array{0: string, 1: string, 2: CssValueRule|null}>
     */
    public function options(): array
    {
        return $this->properties?->options() ?? array_map(
            fn (array $option): array => [$option[0], $option[1], in_array($option[1], StyleProperty::VALUE_KINDS, true) ? CssValueRule::forProperty($option[0], $option[1]) : null],
            self::OPTIONS,
        );
    }

    /**
     * Problems with a style object, as "path: message". Empty when it is fine.
     *
     * @return list<string>
     */
    public function errors(mixed $style): array
    {
        if (! is_array($style) || ($style !== [] && array_is_list($style))) {
            return [Translate::text('style: must be an object.')];
        }

        $errors = [];

        foreach ($this->unknownPaths($style) as $path) {
            $errors[] = Translate::text(':path: is not a style option.', ['path' => $path]);
        }

        foreach ($this->options() as $path => [, $kind, $rule]) {
            $value = data_get($style, $path);

            if ($value === null) {
                continue;
            }

            foreach ($this->byDevice($value) as $device => $single) {
                if ($device === '') {
                    $errors[] = Translate::text(':path: screen sizes must be base, tablet or mobile.', ['path' => $path]);

                    break;
                }

                if ($this->css($kind, $single, $rule) === null) {
                    $errors[] = Translate::text(':path: the value is not allowed.', ['path' => $path]);

                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * A value split by screen size. A plain value is the "base" value. An empty key means an unknown size.
     *
     * @return array<string, mixed>
     */
    public function byDevice(mixed $value): array
    {
        if (! is_array($value)) {
            return ['base' => $value];
        }

        $values = [];

        foreach ($value as $device => $single) {
            $values[in_array($device, self::DEVICES, true) ? $device : ''] = $single;
        }

        return $values;
    }

    // The safe CSS value for a stored value, or null when it is not allowed. Size kinds use their value rule.
    public function css(string $kind, mixed $value, ?CssValueRule $rule = null): ?string
    {
        if (in_array($kind, self::TOKEN_KINDS, true) && is_string($value) && preg_match(self::TOKEN, $value, $token) === 1) {
            return 'var(--verso-'.$token[1].'-'.$token[2].')';
        }

        return match ($kind) {
            'length', 'angle', 'time' => $this->values->normalize($value, $rule ?? CssValueRule::forProperty('', $kind)),
            'color' => is_string($value) && preg_match(self::HEX, $value) === 1 ? strtolower($value) : null,
            'overlay' => is_string($value) && preg_match(self::HEX, $value) === 1 ? strtolower($value) : null,
            'number' => is_int($value) || is_float($value) ? ($value > 0 && $value <= 4 ? (string) $value : null) : null,
            'weight' => is_int($value) && $value >= 100 && $value <= 900 && $value % 100 === 0 ? (string) $value : null,
            'columns' => is_int($value) && $value >= 1 && $value <= 12 ? "repeat({$value}, minmax(0, 1fr))" : null,
            'sticky' => is_bool($value) ? ($value ? 'sticky' : 'static') : null,
            'hidden' => is_bool($value) ? ($value ? 'none' : '') : null,
            'gradient' => StyleValues::gradient($value),
            'image' => StyleValues::image($value),
            'focal' => StyleValues::focal($value),
            'zIndex' => StyleValues::zIndex($value),
            'token' => null,
            default => is_string($value) ? (self::KEYWORDS[$kind][$value] ?? null) : null,
        };
    }

    /**
     * Paths in the style object that are not options, like "spacing.margins".
     *
     * @param  array<mixed>  $style
     * @return list<string>
     */
    protected function unknownPaths(array $style, string $prefix = ''): array
    {
        $unknown = [];

        foreach ($style as $key => $value) {
            $path = $prefix.$key;

            if (isset($this->options()[$path])) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value) && $this->isGroup($path)) {
                $unknown = [...$unknown, ...$this->unknownPaths($value, $path.'.')];

                continue;
            }

            $unknown[] = $path;
        }

        return $unknown;
    }

    // True when some option starts with this path, like "spacing" or "spacing.margin".
    protected function isGroup(string $path): bool
    {
        foreach (array_keys($this->options()) as $option) {
            if (str_starts_with($option, $path.'.')) {
                return true;
            }
        }

        return false;
    }
}
