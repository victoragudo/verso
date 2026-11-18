<?php

namespace App\Verso\Theme\Customizer;

use App\Verso\Core\Settings\Settings;
use App\Verso\I18n\Translate;
use App\Verso\Media\Models\Media;
use App\Verso\Theme\Theme;

// Reads and saves theme settings (theme.<slug>.<key>) and turns them into CSS custom properties.
class ThemeCustomizer
{
    // Prefix of every theme setting key.
    public const SETTING_PREFIX = 'theme.';

    protected ?string $previewSlug = null;

    /** @var array<string, mixed> */
    protected array $previewValues = [];

    /**
     * @param  array<string, string>  $fontStacks  font key => CSS font stack
     */
    public function __construct(
        protected Settings $settings,
        protected array $fontStacks,
    ) {}

    public function schema(Theme $theme): ThemeSettingsSchema
    {
        return ThemeSettingsSchema::fromConfig($theme->settings);
    }

    // The settings key of one field, like "theme.default.accent_color".
    public function settingKey(Theme $theme, string $field): string
    {
        return self::SETTING_PREFIX.$theme->slug.'.'.$field;
    }

    /**
     * The value of every field: a preview value, the saved value or the default, in that order.
     *
     * @return array<string, mixed>
     */
    public function values(Theme $theme): array
    {
        $values = [];
        $preview = $this->previewSlug === $theme->slug ? $this->previewValues : [];

        foreach ($this->schema($theme)->fields as $key => $field) {
            $saved = $this->settings->get($this->settingKey($theme, $key));
            $value = array_key_exists($key, $preview) ? $field->cast($preview[$key]) : $saved;

            $values[$key] = $field->accepts($value) ? $value : $field->default;
        }

        return $values;
    }

    /**
     * Save checked form values. A null value goes back to the default.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(Theme $theme, array $values): void
    {
        foreach ($this->schema($theme)->fields as $key => $field) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $field->cast($values[$key]);

            if ($value === null || $value === $field->default) {
                $this->settings->forget($this->settingKey($theme, $key));
            } elseif ($field->accepts($value)) {
                $this->settings->set($this->settingKey($theme, $key), $value);
            }
        }
    }

    // A :root rule with the CSS variables that differ from the theme defaults.
    public function css(Theme $theme): string
    {
        $values = $this->values($theme);
        $declarations = [];

        foreach ($this->schema($theme)->fields as $key => $field) {
            if ($values[$key] === $field->default) {
                continue;
            }

            $css = $field->cssValue($values[$key], $this->fontStacks);

            if ($css !== null) {
                $declarations[] = "{$field->css}: {$css};";
            }
        }

        return $declarations === [] ? '' : ':root { '.implode(' ', $declarations).' }';
    }

    // The media item chosen in the first media field (the logo), or null.
    public function logo(Theme $theme): ?Media
    {
        $key = $this->logoField($theme);
        $value = $key === null ? null : $this->values($theme)[$key];

        return is_int($value) ? Media::query()->find($value) : null;
    }

    // Key of the first media field, which holds the logo, or null when the theme has none.
    public function logoField(Theme $theme): ?string
    {
        foreach ($this->schema($theme)->fields as $key => $field) {
            if ($field->type === ThemeSettingType::Media) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Use draft values for this request only, for the live preview.
     *
     * @param  array<mixed>  $values
     */
    public function usePreview(Theme $theme, array $values): void
    {
        $this->previewSlug = $theme->slug;
        $this->previewValues = array_filter($values, fn (mixed $value, mixed $key): bool => is_string($key), ARRAY_FILTER_USE_BOTH);
    }

    // Adds one draft value to the preview values of this request, like the logo picked in "Make it yours".
    public function previewValue(Theme $theme, string $key, mixed $value): void
    {
        $this->usePreview($theme, [...($this->previewSlug === $theme->slug ? $this->previewValues : []), $key => $value]);
    }

    /**
     * Safe font list labels for the admin form.
     *
     * @return array<string, string>
     */
    public static function fontLabels(): array
    {
        $labels = [];

        foreach ((array) config('verso.themes.fonts') as $key => $font) {
            $labels[(string) $key] = is_array($font) && is_string($font['label'] ?? null) ? Translate::text($font['label']) : (string) $key;
        }

        return $labels;
    }
}
