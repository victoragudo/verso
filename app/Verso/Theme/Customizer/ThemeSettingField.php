<?php

namespace App\Verso\Theme\Customizer;

use App\Verso\Core\Css\CssUnits;
use App\Verso\Core\Css\CssValueParser;
use App\Verso\Core\Css\CssValueRule;
use App\Verso\Media\Models\Media;
use Closure;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

// One customizer field from theme.json, like an accent color or the layout width.
final readonly class ThemeSettingField
{
    // Setting keys and CSS variable names a theme may use.
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,59}$/';

    public const CSS_PATTERN = '/^--[a-z][a-z0-9-]{0,59}$/';

    protected const COLOR_PATTERN = '/^#[0-9a-f]{6}$/i';

    /**
     * @param  list<string>  $options  font keys the field offers
     * @param  list<string>  $units  units a size or length field offers, every length unit when empty
     * @param  list<string>  $keywords  keywords a size or length field offers, like "none"
     */
    public function __construct(
        public string $key,
        public ThemeSettingType $type,
        public string $label,
        public mixed $default,
        public ?string $css = null,
        public string $help = '',
        public array $options = [],
        public int $min = 0,
        public int $max = 0,
        public string $unit = '',
        public int $maxLength = 0,
        public array $units = [],
        public array $keywords = [],
    ) {}

    /**
     * Build a field from theme.json data. Throws with a plain message when the data is wrong.
     *
     * @param  array<mixed>  $data
     * @param  list<string>  $fonts  keys of the safe font list
     */
    public static function fromArray(string $key, array $data, array $fonts, int $textMax): self
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException(__('The setting key ":key" must be lowercase letters, digits and underscores.', ['key' => $key]));
        }

        $type = ThemeSettingType::tryFrom((string) ($data['type'] ?? ''))
            ?? throw new InvalidArgumentException(__('The setting ":key" has an unknown type. Use color, font, length, media, size, text or toggle.', ['key' => $key]));

        $css = $data['css'] ?? null;

        if ($css !== null && (! is_string($css) || preg_match(self::CSS_PATTERN, $css) !== 1)) {
            throw new InvalidArgumentException(__('The setting ":key" has a CSS variable name that is not valid, like "--accent".', ['key' => $key]));
        }

        if ($css !== null && in_array($type, [ThemeSettingType::Media, ThemeSettingType::Text, ThemeSettingType::Toggle], true)) {
            throw new InvalidArgumentException(__('The setting ":key" cannot be a CSS variable.', ['key' => $key]));
        }

        $options = $type === ThemeSettingType::Font ? array_values(array_map(strval(...), (array) ($data['options'] ?? $fonts))) : [];

        if (array_diff($options, $fonts) !== []) {
            throw new InvalidArgumentException(__('The setting ":key" offers fonts that are not in the safe font list.', ['key' => $key]));
        }

        $sized = in_array($type, [ThemeSettingType::Size, ThemeSettingType::Length], true);
        $unit = CssUnits::canonical((string) ($data['unit'] ?? 'px')) ?? '';
        $units = array_map(strval(...), (array) ($data['units'] ?? []));
        $keywords = array_map(strval(...), (array) ($data['keywords'] ?? []));

        if ($sized && ! in_array($unit, CssUnits::lengths(), true)) {
            throw new InvalidArgumentException(__('The setting ":key" has an unknown unit. Use a CSS length unit like px, rem or %.', ['key' => $key]));
        }

        if ($sized && array_diff($units, CssUnits::lengths()) !== []) {
            throw new InvalidArgumentException(__('The setting ":key" lists units that are not CSS length units.', ['key' => $key]));
        }

        if ($sized && $units !== [] && ! in_array($unit, $units, true)) {
            throw new InvalidArgumentException(__('The setting ":key" must list its own unit in "units".', ['key' => $key]));
        }

        if ($sized && array_diff($keywords, CssUnits::KEYWORDS) !== []) {
            throw new InvalidArgumentException(__('The setting ":key" has keywords that are not allowed. Use :keywords.', ['key' => $key, 'keywords' => implode(', ', CssUnits::KEYWORDS)]));
        }

        $field = new self(
            key: $key,
            type: $type,
            label: is_string($data['label'] ?? null) && $data['label'] !== '' ? mb_substr($data['label'], 0, 80) : ucfirst(str_replace('_', ' ', $key)),
            default: $data['default'] ?? null,
            css: $css,
            help: is_string($data['help'] ?? null) ? mb_substr($data['help'], 0, 200) : '',
            options: $options,
            min: (int) ($data['min'] ?? 0),
            max: (int) ($data['max'] ?? 0),
            unit: $sized ? $unit : '',
            maxLength: $type === ThemeSettingType::Text ? min($textMax, max(1, (int) ($data['max_length'] ?? $textMax))) : 0,
            units: $sized ? array_values(array_intersect(CssUnits::lengths(), $units)) : [],
            keywords: $sized ? array_values(array_intersect(CssUnits::KEYWORDS, $keywords)) : [],
        );

        if (($type === ThemeSettingType::Size || ($type === ThemeSettingType::Length && $field->max !== 0)) && $field->min >= $field->max) {
            throw new InvalidArgumentException(__('The setting ":key" needs a min lower than its max.', ['key' => $key]));
        }

        if ($field->default !== null && ! $field->accepts($field->default)) {
            throw new InvalidArgumentException(__('The default value of the setting ":key" is not valid.', ['key' => $key]));
        }

        return $field;
    }

    /**
     * Validation rules for a value sent from the customizer form.
     *
     * @return list<mixed>
     */
    public function rules(): array
    {
        return match ($this->type) {
            ThemeSettingType::Color => ['nullable', 'string', 'regex:'.self::COLOR_PATTERN],
            ThemeSettingType::Font => ['nullable', 'string', Rule::in($this->options)],
            ThemeSettingType::Media => ['nullable', 'integer', Rule::exists('media', 'id')],
            ThemeSettingType::Size, ThemeSettingType::Length => ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->accepts($this->cast($value))) {
                    $fail($this->range() !== null
                        ? __('Use a size from :min to :max, or another CSS unit.', ['min' => $this->min.$this->unit, 'max' => $this->max.$this->unit])
                        : __('Use a CSS size like 16px, 1.5rem, 50% or clamp(1rem, 2vw, 2rem).'));
                }
            }],
            ThemeSettingType::Text => ['nullable', 'string', 'max:'.$this->maxLength],
            ThemeSettingType::Toggle => ['nullable', 'boolean'],
        };
    }

    // True when a stored or default value is safe to use for this field.
    public function accepts(mixed $value): bool
    {
        return match ($this->type) {
            ThemeSettingType::Color => is_string($value) && preg_match(self::COLOR_PATTERN, $value) === 1,
            ThemeSettingType::Font => is_string($value) && in_array($value, $this->options, true),
            ThemeSettingType::Media => is_int($value) && $value > 0,
            ThemeSettingType::Size, ThemeSettingType::Length => $this->acceptsLength($value),
            ThemeSettingType::Text => is_string($value) && mb_strlen($value) <= $this->maxLength,
            ThemeSettingType::Toggle => is_bool($value),
        };
    }

    // True for size and length fields, which take any CSS size.
    public function isLength(): bool
    {
        return $this->type === ThemeSettingType::Size || $this->type === ThemeSettingType::Length;
    }

    // Units, keywords and numbers a size or length field accepts.
    public function rule(): CssValueRule
    {
        return new CssValueRule(units: $this->units === [] ? null : $this->units, keywords: $this->keywords, negative: $this->min < 0);
    }

    // Turn a form value into the stored type, like "70" into 70. Sizes in the unit of the field stay whole numbers
    // like before; other sizes are kept as clean CSS text, like "1100px" or "clamp(40rem, 80vw, 72rem)".
    public function cast(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->isLength()) {
            return $this->castLength($value);
        }

        return match ($this->type) {
            ThemeSettingType::Media => is_numeric($value) ? (int) $value : $value,
            ThemeSettingType::Color => is_string($value) ? strtolower($value) : $value,
            ThemeSettingType::Toggle => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            default => $value,
        };
    }

    /**
     * The CSS value of the field, like "#9a3412" or "62rem". Null when the field has no CSS variable.
     *
     * @param  array<string, string>  $fontStacks
     */
    public function cssValue(mixed $value, array $fontStacks): ?string
    {
        if ($this->css === null || ! $this->accepts($value)) {
            return null;
        }

        return match ($this->type) {
            ThemeSettingType::Color => strtolower((string) $value),
            ThemeSettingType::Font => $fontStacks[(string) $value] ?? null,
            ThemeSettingType::Size, ThemeSettingType::Length => is_int($value) ? $value.$this->unit : (new CssValueParser)->normalize($value, $this->rule()),
            default => null,
        };
    }

    /** @return array{0: int, 1: int}|null the range in the unit of the field, null when the field has none */
    public function range(): ?array
    {
        return $this->max > $this->min ? [$this->min, $this->max] : null;
    }

    protected function castLength(mixed $value): mixed
    {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,6}$/', trim($value)) === 1)) {
            return (int) $value;
        }

        $css = is_numeric($value) ? (new CssValueParser)->normalize($value.$this->unit, $this->rule()) : (new CssValueParser)->normalize($value, $this->rule());

        if ($css === null) {
            return $value;
        }

        return preg_match('/^(-?\d{1,6})'.preg_quote($this->unit, '/').'$/', $css, $match) === 1 ? (int) $match[1] : $css;
    }

    // A whole number in the unit of the field must be inside the range. Other units and custom values cannot be compared.
    protected function acceptsLength(mixed $value): bool
    {
        $range = $this->range();

        if (is_int($value)) {
            return $range === null || ($value >= $range[0] && $value <= $range[1]);
        }

        if (! is_string($value) || (new CssValueParser)->normalize($value, $this->rule()) !== $value) {
            return false;
        }

        if ($range !== null && preg_match('/^(-?[\d.]+)'.preg_quote($this->unit, '/').'$/', $value, $match) === 1) {
            return (float) $match[1] >= $range[0] && (float) $match[1] <= $range[1];
        }

        return true;
    }

    // True when the value points at an existing media item.
    public function mediaExists(mixed $value): bool
    {
        return $this->type === ThemeSettingType::Media && is_int($value) && Media::query()->whereKey($value)->exists();
    }

    /**
     * Data for the admin customizer form.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'help' => $this->help,
            'default' => $this->default,
            'options' => $this->options,
            'min' => $this->min,
            'max' => $this->max,
            'unit' => $this->unit,
            'maxLength' => $this->maxLength,
            'value' => $this->isLength() ? $this->rule()->toArray() : null,
        ];
    }
}
