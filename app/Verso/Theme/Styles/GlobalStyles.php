<?php

namespace App\Verso\Theme\Styles;

use App\Verso\Core\Css\CssValueParser;
use App\Verso\Core\Css\CssValueRule;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Customizer\ThemeSettingType;
use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Theme;
use Illuminate\Support\Str;

// Design tokens (colors, fonts, text sizes, spacing, corners, widths) declared under "styles" in theme.json.
// Each token becomes --verso-<group>-<key>, which block styles use as "var:<group>.<key>".
// A token linked to a customizer setting reads and writes that setting, so old customizer values are kept.
class GlobalStyles
{
    // Settings key prefix of the token values, followed by the theme slug.
    public const SETTING = 'theme.styles.';

    // Token groups, in the order the admin shows them, and the kind of value each holds.
    public const GROUPS = ['color' => 'color', 'font' => 'font', 'text' => 'length', 'spacing' => 'length', 'radius' => 'length', 'width' => 'length'];

    protected const KEY = '/^[a-z][a-z0-9-]{0,40}$/';

    protected const HEX = '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i';

    protected const CSS_VAR = '/^--[a-z][a-z0-9-]{0,59}$/';

    /** @var array<string, string> values shown in this request only, by "group.key" */
    protected array $previews = [];

    protected CssValueParser $parser;

    /**
     * @param  array<string, string>  $fontStacks  font key => CSS font stack, the safe self-hosted list
     */
    public function __construct(
        protected Settings $settings,
        protected ThemeCustomizer $customizer,
        protected array $fontStacks,
        protected ThemeText $text,
    ) {
        $this->parser = new CssValueParser;
    }

    /**
     * The tokens of a theme by "group.key".
     *
     * @return array<string, array{group: string, key: string, kind: string, label: string, help: string, default: string, css: string|null, use: string|null, setting: string|null}>
     */
    public function tokens(Theme $theme): array
    {
        $styles = $theme->styles;
        $fields = $this->customizer->schema($theme)->fields;
        $tokens = [];

        foreach (self::GROUPS as $group => $kind) {
            foreach (is_array($styles[$group] ?? null) ? $styles[$group] : [] as $key => $config) {
                if (! is_string($key) || preg_match(self::KEY, $key) !== 1 || ! is_array($config)) {
                    continue;
                }

                $setting = is_string($config['setting'] ?? null) && isset($fields[$config['setting']]) ? $config['setting'] : null;
                $default = $setting !== null ? $this->fromField($theme, $setting, $fields[$setting]->default) : $config['default'] ?? null;

                $default = is_string($default) ? $this->clean($kind, $default) : null;

                if ($default === null) {
                    continue;
                }

                $tokens["{$group}.{$key}"] = [
                    'group' => $group,
                    'key' => $key,
                    'kind' => $kind,
                    'label' => is_string($config['label'] ?? null) ? $this->text->text($theme, $config['label']) : Str::headline($key),
                    'help' => is_string($config['help'] ?? null) ? $this->text->text($theme, $config['help']) : '',
                    'default' => $default,
                    'css' => $this->cssVar($config['css'] ?? null),
                    'use' => $this->cssVar($config['use'] ?? null),
                    'setting' => $setting,
                ];
            }
        }

        return $tokens;
    }

    /**
     * Current value of every token: a preview value, the saved value or the default.
     *
     * @return array<string, string>
     */
    public function values(Theme $theme): array
    {
        $saved = $this->saved($theme);
        $customizer = $this->customizer->values($theme);
        $fields = $this->customizer->schema($theme)->fields;
        $values = [];

        foreach ($this->tokens($theme) as $id => $token) {
            $value = $token['setting'] !== null
                ? $this->fromField($theme, $token['setting'], $customizer[$token['setting']] ?? null, $fields[$token['setting']]->unit)
                : ($saved[$id] ?? null);
            $value = $this->previews[$id] ?? $value;

            $values[$id] = (is_string($value) ? $this->clean($token['kind'], $value) : null) ?? $token['default'];
        }

        return $values;
    }

    /**
     * Saves checked values. Unknown tokens and bad values are skipped; a default value removes the saved one.
     *
     * @param  array<mixed>  $values  by "group.key"
     * @return list<string> ids of tokens that were not saved because the value is not allowed
     */
    public function save(Theme $theme, array $values): array
    {
        $tokens = $this->tokens($theme);
        $saved = $this->saved($theme);
        $customizer = [];
        $rejected = [];

        foreach ($values as $id => $value) {
            $token = is_string($id) ? $tokens[$id] ?? null : null;

            if ($token === null) {
                continue;
            }

            $value = is_string($value) ? $this->clean($token['kind'], $value) : null;

            if ($value === null) {
                $rejected[] = (string) $id;

                continue;
            }

            if ($token['setting'] !== null) {
                $customizer[$token['setting']] = $this->toField($theme, $token['setting'], $value);
            } elseif ($value === $token['default']) {
                unset($saved[$id]);
            } else {
                $saved[$id] = $value;
            }
        }

        if ($customizer !== []) {
            $this->customizer->save($theme, $customizer);
        }

        $saved === [] ? $this->settings->forget(self::SETTING.$theme->slug) : $this->settings->set(self::SETTING.$theme->slug, $saved);

        return $rejected;
    }

    // A :root rule with every --verso-* token and the theme variables of edited tokens.
    // A token that names a theme variable points at it, so light and dark values keep working.
    public function css(Theme $theme): string
    {
        $values = $this->values($theme);
        $declarations = [];

        foreach ($this->tokens($theme) as $id => $token) {
            $value = $this->cssValue($token['kind'], $values[$id]);
            $edited = $values[$id] !== $token['default'];
            $read = $token['use'] ?? $token['css'];

            if ($token['css'] !== null && $edited && $token['setting'] === null) {
                $declarations[] = "{$token['css']}: {$value};";
            }

            $declarations[] = '--verso-'.$token['group'].'-'.$token['key'].': '.($read !== null ? "var({$read})" : $value).';';
        }

        return $declarations === [] ? '' : ':root { '.implode(' ', $declarations).' }';
    }

    /**
     * Shows these values in this request only, for the preview.
     *
     * @param  array<mixed>  $values  by "group.key"
     */
    public function preview(Theme $theme, array $values): void
    {
        $tokens = $this->tokens($theme);
        $customizer = [];

        foreach ($values as $id => $value) {
            $token = is_string($id) ? $tokens[$id] ?? null : null;

            $value = $token !== null && is_string($value) ? $this->clean($token['kind'], $value) : null;

            if ($token !== null && $value !== null) {
                $this->previews[$id] = $value;

                if ($token['setting'] !== null) {
                    $customizer[$token['setting']] = $this->toField($theme, $token['setting'], $value);
                }
            }
        }

        if ($customizer !== []) {
            $this->customizer->usePreview($theme, $customizer);
        }
    }

    /**
     * Font keys and labels of the safe self-hosted list, with their stacks for samples.
     *
     * @return list<array{value: string, label: string, stack: string}>
     */
    public function fonts(): array
    {
        $labels = ThemeCustomizer::fontLabels();
        $fonts = [];

        foreach ($this->fontStacks as $key => $stack) {
            $fonts[] = ['value' => $key, 'label' => $labels[$key] ?? $key, 'stack' => $stack];
        }

        return $fonts;
    }

    public function accepts(string $kind, string $value): bool
    {
        return $this->clean($kind, $value) !== null;
    }

    // The value in its clean form, like "clamp(1rem, 2vw, 3rem)", or null when it is not allowed.
    public function clean(string $kind, string $value): ?string
    {
        return match ($kind) {
            'color' => preg_match(self::HEX, $value) === 1 ? $value : null,
            'font' => isset($this->fontStacks[$value]) ? $value : null,
            default => $this->parser->normalize($value, $this->rule()),
        };
    }

    // Size tokens take any CSS length or custom value, but no negative numbers.
    public function rule(): CssValueRule
    {
        return new CssValueRule(negative: false);
    }

    protected function cssValue(string $kind, string $value): string
    {
        return $kind === 'font' ? $this->fontStacks[$value] : $value;
    }

    protected function cssVar(mixed $name): ?string
    {
        return is_string($name) && preg_match(self::CSS_VAR, $name) === 1 ? $name : null;
    }

    // A customizer value as a token value: colors and fonts as they are, sizes with their unit.
    protected function fromField(Theme $theme, string $setting, mixed $value, ?string $unit = null): ?string
    {
        $field = $this->customizer->schema($theme)->fields[$setting];

        return match ($field->type) {
            ThemeSettingType::Color, ThemeSettingType::Font => is_string($value) ? $value : null,
            ThemeSettingType::Size, ThemeSettingType::Length => is_int($value) ? $value.($unit ?? $field->unit) : (is_string($value) ? $value : null),
            default => null,
        };
    }

    // A token value as a customizer value. A size in the unit of the field is stored as a number, like before.
    protected function toField(Theme $theme, string $setting, string $value): mixed
    {
        $field = $this->customizer->schema($theme)->fields[$setting];

        return $field->isLength() ? $field->cast($value) : $value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function saved(Theme $theme): array
    {
        $saved = $this->settings->get(self::SETTING.$theme->slug);

        return is_array($saved) ? $saved : [];
    }
}
