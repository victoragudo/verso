<?php

namespace App\Verso\Theme\Templates;

use App\Verso\Content\Models\Entry;
use App\Verso\Theme\Support\ThemeText;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Closure;

// The page templates of the active theme (with its parents), and the one chosen for an entry.
class PageTemplates
{
    // Key in entries.meta that holds the chosen template name.
    public const META_KEY = 'template';

    public function __construct(
        protected ThemeManager $themes,
        protected ThemeText $text,
    ) {}

    /**
     * Every valid template of a theme, in theme.json order.
     *
     * @return list<PageTemplate>
     */
    public function all(?Theme $theme = null): array
    {
        $theme ??= $this->themes->active();
        $templates = [];

        foreach ($theme->templates as $name => $config) {
            $template = PageTemplate::fromManifest((string) $name, $config);

            if ($template !== null) {
                $templates[] = $template->withText(
                    $this->text->text($theme, $template->label),
                    $this->text->text($theme, $template->description),
                );
            }
        }

        return $templates;
    }

    /**
     * Templates that can be picked for one content type.
     *
     * @return list<PageTemplate>
     */
    public function forType(string $type, ?Theme $theme = null): array
    {
        return array_values(array_filter($this->all($theme), fn (PageTemplate $template): bool => $template->appliesTo($type)));
    }

    public function find(string $type, string $name): ?PageTemplate
    {
        foreach ($this->forType($type) as $template) {
            if ($template->name === $name) {
                return $template;
            }
        }

        return null;
    }

    // The template saved on the entry, when the active theme still offers it for the entry type.
    public function forEntry(Entry $entry): ?PageTemplate
    {
        $name = $entry->meta[self::META_KEY] ?? null;

        return is_string($name) && $name !== '' ? $this->find($entry->type, $name) : null;
    }

    /**
     * Options for the editor select.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    public function options(string $type): array
    {
        return array_map(fn (PageTemplate $template): array => $template->toOption(), $this->forType($type));
    }

    /**
     * Validation rules for meta.template. The value already stored is kept even when the theme no longer offers it.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $type, ?Entry $entry = null): array
    {
        $names = array_map(fn (PageTemplate $template): string => $template->name, $this->forType($type));
        $stored = $entry?->meta[self::META_KEY] ?? null;

        return [
            'meta.'.self::META_KEY => [
                'nullable',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($names, $stored): void {
                    if (! in_array($value, $names, true) && $value !== $stored) {
                        $fail(__('Choose a template offered by the active theme.'));
                    }
                },
            ],
        ];
    }
}
