<?php

namespace App\Verso\Easy\Starters;

use InvalidArgumentException;

// A complete starter site for one kind of site: pages, menus, settings, plugins and a style preset.
final readonly class StarterSite
{
    // Keys look like "restaurant" or "yoga-studio".
    public const KEY_PATTERN = '/^[a-z][a-z0-9-]{0,40}$/';

    /**
     * @param  list<StarterPage>  $pages  pages and posts, in menu order
     * @param  list<StarterMenuItem>  $primaryMenu
     * @param  list<StarterMenuItem>  $footerMenu
     * @param  array<string, mixed>  $settings  setting key => value, like site.tagline
     * @param  list<string>  $plugins  plugin slugs to turn on when they are installed
     * @param  list<StarterImage>  $images  sample pictures the sections name
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $icon,
        public array $pages,
        public array $primaryMenu = [],
        public array $footerMenu = [],
        public array $settings = [],
        public array $plugins = [],
        public string $stylePreset = 'calm',
        public array $images = [],
        public int $order = 100,
    ) {
        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new InvalidArgumentException("The starter site key \"{$key}\" must start with a letter and use only a-z, 0-9 and -.");
        }
    }

    // The page that becomes the home page, or null to keep the latest posts there.
    public function frontPage(): ?StarterPage
    {
        foreach ($this->pages as $page) {
            if ($page->front && $page->type === 'page') {
                return $page;
            }
        }

        return null;
    }

    public function image(string $name): ?StarterImage
    {
        foreach ($this->images as $image) {
            if ($image->name === $name) {
                return $image;
            }
        }

        return null;
    }

    /**
     * What the guided start shows about this starter.
     *
     * @return array{key: string, label: string, description: string, icon: string, stylePreset: string, plugins: list<string>, pages: list<array{title: string, slug: string, type: string, front: bool}>, sections: list<string>}
     */
    public function toArray(): array
    {
        $front = $this->frontPage() ?? $this->pages[0] ?? null;

        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'stylePreset' => $this->stylePreset,
            'plugins' => $this->plugins,
            'pages' => array_map(fn (StarterPage $page): array => $page->toArray(), $this->pages),
            'sections' => $front === null ? [] : array_map(fn (StarterSection $section): string => $section->key, $front->sections),
        ];
    }
}
