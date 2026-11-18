<?php

namespace App\Verso\Theme\Templates;

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Str;

// Styles a theme offers for a part, like a "Centered" header or a "Four columns" footer, from "parts" in theme.json.
// The default style uses parts/<name>; another style uses parts/<name>-<style>. The choice is saved per theme.
class PartVariants
{
    // Settings key prefix of the chosen styles, followed by the theme slug.
    public const SETTING = 'theme.part_variants.';

    /** @var array<string, string> styles shown in this request only, for the preview */
    protected array $previews = [];

    /** @var array<string, string> styles the page being shown asks for, like a minimal header on a landing page */
    protected array $overrides = [];

    public function __construct(
        protected ThemeManager $themes,
        protected Settings $settings,
        protected TemplateParts $parts,
        protected ThemeText $text,
    ) {}

    /**
     * Parts with styles, like ["header" => ["label" => "Header", "default" => "classic", "variants" => [...]]].
     * A style is listed only when the theme has its file.
     *
     * @return array<string, array{label: string, default: string, variants: array<string, array{key: string, label: string, description: string, part: string}>}>
     */
    public function definitions(Theme $theme): array
    {
        $available = $this->parts->available($theme);
        $definitions = [];

        foreach ($theme->parts as $name => $config) {
            if (! is_string($name) || preg_match(TemplateParts::NAME_PATTERN, $name) !== 1 || ! is_array($config) || ! is_array($config['variants'] ?? null)) {
                continue;
            }

            $default = is_string($config['default'] ?? null) ? $config['default'] : (string) array_key_first($config['variants']);
            $variants = [];

            foreach ($config['variants'] as $key => $variant) {
                $part = $key === $default ? $name : "{$name}-{$key}";

                if (! is_string($key) || preg_match(TemplateParts::NAME_PATTERN, $key) !== 1 || ! in_array($part, $available, true)) {
                    continue;
                }

                $variants[$key] = [
                    'key' => $key,
                    'label' => is_string($variant['label'] ?? null) ? $this->text->text($theme, $variant['label']) : Str::headline($key),
                    'description' => is_string($variant['description'] ?? null) ? $this->text->text($theme, $variant['description']) : '',
                    'part' => $part,
                ];
            }

            if (isset($variants[$default]) && count($variants) > 1) {
                $definitions[$name] = [
                    'label' => is_string($config['label'] ?? null) ? $this->text->text($theme, $config['label']) : Str::headline($name),
                    'default' => $default,
                    'variants' => $variants,
                ];
            }
        }

        return $definitions;
    }

    // The chosen style of a part, or the default one.
    public function chosen(Theme $theme, string $name): ?string
    {
        $definition = $this->definitions($theme)[$name] ?? null;

        if ($definition === null) {
            return null;
        }

        $choice = $this->previews[$name] ?? $this->overrides[$name] ?? $this->saved($theme)[$name] ?? null;

        return is_string($choice) && isset($definition['variants'][$choice]) ? $choice : $definition['default'];
    }

    /**
     * Every part with styles and its chosen style.
     *
     * @return array<string, string>
     */
    public function choices(Theme $theme): array
    {
        $choices = [];

        foreach (array_keys($this->definitions($theme)) as $name) {
            $choices[$name] = (string) $this->chosen($theme, $name);
        }

        return $choices;
    }

    // The variant name @templatePart should use for a part, or null for the plain part file.
    public function variantFor(string $name): ?string
    {
        $theme = $this->themes->active();

        if ($theme->parts === [] || ! isset($theme->parts[$name])) {
            return null;
        }

        $chosen = $this->chosen($theme, $name);
        $definition = $this->definitions($theme)[$name] ?? null;

        return $chosen === null || $definition === null || $chosen === $definition['default'] ? null : $chosen;
    }

    // Saves the style of a part. False when the theme does not offer it.
    public function choose(Theme $theme, string $name, string $variant): bool
    {
        if (! isset($this->definitions($theme)[$name]['variants'][$variant])) {
            return false;
        }

        $this->settings->set(self::SETTING.$theme->slug, [...$this->saved($theme), $name => $variant]);

        return true;
    }

    /**
     * Uses these styles for the page shown in this request, like ["header" => "minimal"]. Unknown styles are ignored.
     *
     * @param  array<string, string>  $styles
     */
    public function override(array $styles): void
    {
        $this->overrides = $styles;
    }

    // Shows a style in this request only, for the preview.
    public function preview(string $name, string $variant): void
    {
        $this->previews[$name] = $variant;
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
