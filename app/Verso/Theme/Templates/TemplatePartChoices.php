<?php

namespace App\Verso\Theme\Templates;

use App\Verso\Content\Models\Entry;
use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Closure;

// Header and footer styles per page template and per entry, like a minimal header on landing pages.
// Order, last wins: the site wide style, the template in theme.json, the template choice saved in the
// Site Editor, then the choice saved on the entry.
class TemplatePartChoices
{
    // Key in entries.meta with the styles of one entry, like {"header": "minimal"}.
    public const META_KEY = 'parts';

    // Settings key prefix of the styles per template, followed by the theme slug.
    public const SETTING = 'theme.template_parts.';

    public function __construct(
        protected ThemeManager $themes,
        protected Settings $settings,
        protected PartVariants $variants,
        protected PageTemplates $templates,
    ) {}

    /**
     * Parts that have styles, with their choices, for the editor selects.
     *
     * @return list<array{name: string, label: string, default: string, options: list<array{value: string, label: string, description: string}>}>
     */
    public function options(?Theme $theme = null): array
    {
        $options = [];

        foreach ($this->variants->definitions($theme ?? $this->themes->active()) as $name => $definition) {
            $options[] = [
                'name' => $name,
                'label' => $definition['label'],
                'default' => $definition['default'],
                'options' => array_values(array_map(fn (array $variant): array => [
                    'value' => $variant['key'],
                    'label' => $variant['label'],
                    'description' => $variant['description'],
                ], $definition['variants'])),
            ];
        }

        return $options;
    }

    /**
     * The styles a page template uses: theme.json first, then the choice saved in the Site Editor.
     *
     * @return array<string, string>
     */
    public function forTemplate(Theme $theme, PageTemplate $template): array
    {
        return $this->valid($theme, [...$template->parts, ...($this->saved($theme)[$template->name] ?? [])]);
    }

    /**
     * The styles for one entry: its page template, then the entry's own choice.
     *
     * @return array<string, string>
     */
    public function forEntry(Entry $entry): array
    {
        $theme = $this->themes->active();
        $template = $this->templates->forEntry($entry);
        $own = PageTemplate::partsFrom($entry->meta[self::META_KEY] ?? null);

        return $this->valid($theme, [...($template !== null ? $this->forTemplate($theme, $template) : []), ...$own]);
    }

    // Makes the next part renders of this request use the styles of the entry.
    public function apply(Entry $entry): void
    {
        $this->variants->override($this->forEntry($entry));
    }

    // Goes back to the site wide styles.
    public function reset(): void
    {
        $this->variants->override([]);
    }

    /**
     * Saves the styles of one page template. An empty or unknown style removes that part from the choice.
     *
     * @param  array<mixed>  $styles
     */
    public function saveForTemplate(Theme $theme, string $template, array $styles): void
    {
        $saved = $this->saved($theme);
        $saved[$template] = $this->valid($theme, PageTemplate::partsFrom($styles));

        if ($saved[$template] === []) {
            unset($saved[$template]);
        }

        $saved === [] ? $this->settings->forget(self::SETTING.$theme->slug) : $this->settings->set(self::SETTING.$theme->slug, $saved);
    }

    /**
     * Validation rules for meta.parts of an entry. A style already stored is kept even when the theme no longer offers it.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(?Entry $entry = null): array
    {
        $definitions = $this->variants->definitions($this->themes->active());
        $stored = PageTemplate::partsFrom($entry?->meta[self::META_KEY] ?? null);
        $prefix = 'meta.'.self::META_KEY.'.';

        return [
            'meta.'.self::META_KEY => ['nullable', 'array'],
            $prefix.'*' => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($definitions, $stored, $prefix): void {
                    $part = substr($attribute, strlen($prefix));

                    if (! is_string($value)) {
                        return;
                    }

                    if (! isset($definitions[$part]['variants'][$value]) && ($stored[$part] ?? null) !== $value) {
                        $fail(__('Choose a header or footer style offered by the active theme.'));
                    }
                },
            ],
        ];
    }

    /**
     * Styles saved per template in the Site Editor.
     *
     * @return array<string, array<string, string>>
     */
    public function saved(Theme $theme): array
    {
        $saved = $this->settings->get(self::SETTING.$theme->slug);
        $clean = [];

        foreach (is_array($saved) ? $saved : [] as $template => $styles) {
            if (is_string($template)) {
                $clean[$template] = PageTemplate::partsFrom($styles);
            }
        }

        return $clean;
    }

    /**
     * Only styles the theme offers.
     *
     * @param  array<string, string>  $styles
     * @return array<string, string>
     */
    protected function valid(Theme $theme, array $styles): array
    {
        $definitions = $this->variants->definitions($theme);

        return array_filter($styles, fn (string $style, string $part): bool => isset($definitions[$part]['variants'][$style]), ARRAY_FILTER_USE_BOTH);
    }
}
