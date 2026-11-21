<?php

namespace App\Verso\Easy\Styles;

use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Customizer\ThemeSettingType;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;

// What "Make it yours" can change on one theme: presets, font pairs and color roles, cut down to the tokens the theme declares.
class ThemeStyleOptions
{
    // Palette roles and the color token each one fills.
    public const COLOR_ROLES = ['accent', 'background', 'text', 'muted', 'surface'];

    // Font tokens a pair fills, first declared one wins: headings, then text.
    public const HEADING_TOKENS = ['font.heading', 'font.ui'];

    public const BODY_TOKENS = ['font.body'];

    public function __construct(
        protected StylePresetRegistry $presets,
        protected FontPairRegistry $fontPairs,
        protected GlobalStyles $globalStyles,
        protected ThemeCustomizer $customizer,
        protected ThemeManager $themes,
        protected ThemeText $themeText,
    ) {}

    /**
     * A preset in the admin language. Theme presets use the theme's translations, the others the registry ones.
     *
     * @return array{key: string, label: string, description: string, tokens: array<string, string>, fontPair: string|null}
     */
    public function presetForBrowser(Theme $theme, StylePreset $preset): array
    {
        $fromTheme = isset($this->themeList($theme, 'presets')[$preset->key]);

        return [
            ...$preset->toArray(),
            'label' => $fromTheme ? $this->themeText->text($theme, $preset->label) : $this->presets->text($preset->key, $preset->label),
            'description' => $fromTheme ? $this->themeText->text($theme, $preset->description) : $this->presets->text($preset->key, $preset->description),
        ];
    }

    // The name of a font pair in the admin language.
    public function fontPairLabel(Theme $theme, FontPair $pair): string
    {
        return isset($this->themeList($theme, 'fontPairs')[$pair->key])
            ? $this->themeText->text($theme, $pair->label)
            : $this->fontPairs->text($pair->key, $pair->label);
    }

    // The sample line of a font pair in the admin language.
    public function fontPairSample(Theme $theme, FontPair $pair): string
    {
        return isset($this->themeList($theme, 'fontPairs')[$pair->key])
            ? $this->themeText->text($theme, $pair->sample)
            : $this->fontPairs->text($pair->key, $pair->sample);
    }

    /**
     * Presets for this theme: the registry ones plus the theme.json ones (easy.presets), with only the tokens the theme accepts.
     *
     * @return array<string, StylePreset>
     */
    public function presets(Theme $theme): array
    {
        $presets = $this->presets->all();

        foreach ($this->themeList($theme, 'presets') as $key => $data) {
            if (preg_match(StylePreset::KEY_PATTERN, $key) === 1) {
                $presets[$key] = StylePreset::fromArray($key, $data, $presets[$key] ?? null);
            }
        }

        $tokens = $this->globalStyles->tokens($theme);

        return StylePresetRegistry::sorted(array_map(
            fn (StylePreset $preset): StylePreset => $preset->withTokens(array_filter(
                $preset->tokens,
                fn (string $value, string $id): bool => $this->fits($theme, $tokens, $id, $value),
                ARRAY_FILTER_USE_BOTH,
            )),
            $presets,
        ));
    }

    /**
     * Font pairs for this theme: the registry ones plus the theme.json ones (easy.fontPairs), only when the theme accepts both fonts.
     *
     * @return array<string, FontPair>
     */
    public function fontPairs(Theme $theme): array
    {
        $pairs = $this->fontPairs->all();

        foreach ($this->themeList($theme, 'fontPairs') as $key => $data) {
            $pair = FontPair::fromArray($key, $data);

            if ($pair !== null) {
                $pairs[$key] = $pair;
            }
        }

        return FontPairRegistry::sorted(array_filter($pairs, fn (FontPair $pair): bool => $this->fontPairTokens($theme, $pair) !== []));
    }

    /**
     * Token values of a font pair on this theme, or [] when the theme cannot use it.
     *
     * @return array<string, string>
     */
    public function fontPairTokens(Theme $theme, FontPair $pair): array
    {
        $tokens = $this->globalStyles->tokens($theme);
        $body = $this->firstDeclared($tokens, self::BODY_TOKENS);
        $heading = $this->firstDeclared($tokens, self::HEADING_TOKENS);

        if ($body === null || ! $this->fits($theme, $tokens, $body, $pair->body)) {
            return [];
        }

        if ($heading !== null && ! $this->fits($theme, $tokens, $heading, $pair->heading)) {
            return [];
        }

        return $heading === null ? [$body => $pair->body] : [$heading => $pair->heading, $body => $pair->body];
    }

    /**
     * Color roles the theme declares, with their token id and label, like accent => color.accent.
     *
     * @return array<string, array{token: string, label: string}>
     */
    public function colorRoles(Theme $theme): array
    {
        $tokens = $this->globalStyles->tokens($theme);
        $roles = [];

        foreach (self::COLOR_ROLES as $role) {
            if (isset($tokens["color.{$role}"])) {
                $roles[$role] = ['token' => "color.{$role}", 'label' => $tokens["color.{$role}"]['label']];
            }
        }

        return $roles;
    }

    /**
     * Saved values of every color token of the theme, like color.text => #1b2420.
     *
     * @return array<string, string>
     */
    public function colorValues(Theme $theme): array
    {
        $values = $this->globalStyles->values($theme);
        $colors = [];

        foreach ($this->globalStyles->tokens($theme) as $id => $token) {
            if ($token['group'] === 'color') {
                $colors[$id] = $values[$id] ?? $token['default'];
            }
        }

        return $colors;
    }

    /**
     * The saved palette of the theme by color role, like text => #1b2420.
     *
     * @return array<string, string>
     */
    public function palette(Theme $theme): array
    {
        $values = $this->colorValues($theme);

        return array_filter(array_map(fn (array $role): ?string => $values[$role['token']] ?? null, $this->colorRoles($theme)));
    }

    /**
     * The font stack of every safe font, for samples.
     *
     * @return array<string, string>
     */
    public function fontStacks(): array
    {
        return array_column($this->globalStyles->fonts(), 'stack', 'value');
    }

    /**
     * Font files of the theme (easy.fontFiles: font key => file in assets), so the admin shows real samples.
     *
     * @return list<array{font: string, family: string, url: string}>
     */
    public function fontFaces(Theme $theme): array
    {
        $files = is_array($theme->easy['fontFiles'] ?? null) ? $theme->easy['fontFiles'] : [];
        $stacks = $this->fontStacks();
        $faces = [];

        foreach ($files as $font => $path) {
            $stack = is_string($font) ? $stacks[$font] ?? null : null;
            $url = $stack !== null && is_string($path) ? $this->themes->assetUrl($path) : null;

            if ($stack !== null && $url !== null) {
                $faces[] = ['font' => (string) $font, 'family' => trim(explode(',', $stack)[0], " '\""), 'url' => $url];
            }
        }

        return $faces;
    }

    /**
     * True when the theme has this token and accepts the value, including the limits of a linked customizer field.
     *
     * @param  array<string, array{kind: string, setting: string|null}>  $tokens
     */
    public function fits(Theme $theme, array $tokens, string $id, string $value): bool
    {
        $token = $tokens[$id] ?? null;

        if ($token === null || ! $this->globalStyles->accepts($token['kind'], $value)) {
            return false;
        }

        if ($token['setting'] === null) {
            return true;
        }

        $field = $this->customizer->schema($theme)->fields[$token['setting']] ?? null;

        return $field !== null && $field->accepts($field->type === ThemeSettingType::Size ? (int) round((float) $value) : $field->cast($value));
    }

    /**
     * @param  array<string, mixed>  $tokens
     * @param  list<string>  $ids
     */
    protected function firstDeclared(array $tokens, array $ids): ?string
    {
        foreach ($ids as $id) {
            if (isset($tokens[$id])) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Keyed items of the theme.json "easy" object, like easy.presets.
     *
     * @return array<string, array<mixed>>
     */
    protected function themeList(Theme $theme, string $name): array
    {
        $items = $theme->easy[$name] ?? null;

        return is_array($items) ? array_filter($items, fn (mixed $data, mixed $key): bool => is_string($key) && is_array($data), ARRAY_FILTER_USE_BOTH) : [];
    }
}
