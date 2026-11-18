<?php

namespace App\Verso\Theme\Templates;

use App\Verso\Editor\BlockRenderer;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Illuminate\View\Factory as ViewFactory;

// Reusable theme pieces in views/parts, like a header or a footer with variants ("footer-minimal").
class TemplateParts
{
    // Folder of parts inside the views folder of a theme.
    public const FOLDER = 'parts';

    // Folder of block parts (parts/<key>.json) at the root of a theme.
    public const BLOCKS_FOLDER = 'parts';

    // Part and variant names: lowercase letters, digits, dashes and underscores.
    public const NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    // HTML element that wraps an edited part, by part name. Other parts use a div.
    protected const WRAPPERS = ['header' => 'header', 'footer' => 'footer', 'sidebar' => 'aside'];

    public function __construct(
        protected TemplateResolver $resolver,
        protected ThemeManager $themes,
        protected SitePartStore $store,
        protected BlockRenderer $blocks,
        protected ViewFactory $views,
    ) {}

    /**
     * HTML of a part: the published Site Editor copy, else the theme file, checking the variant first.
     * Without a variant, the style chosen in the Site Editor is used.
     *
     * @param  array<string, mixed>  $data  data of the view that includes the part
     */
    public function render(array $data, string $name, ?string $variant = null): string
    {
        $override = app(TemplateOverrides::class)->part($name, $data, fn (): string => $this->renderTheme($data, $name, $variant));

        return $override ?? $this->renderTheme($data, $name, $variant);
    }

    /**
     * The part of the theme, or its Site Editor copy, without overrides.
     *
     * @param  array<string, mixed>  $data
     */
    protected function renderTheme(array $data, string $name, ?string $variant = null): string
    {
        $variant ??= app(PartVariants::class)->variantFor($name);
        $file = $this->view($name, $variant);
        $theme = $this->themes->active()->slug;

        foreach ($this->keys($name, $variant) as $key) {
            $blocks = $this->store->published($theme, $key);

            if ($blocks !== null) {
                return $this->renderBlocks($name, $key, $blocks);
            }

            if ($file !== null && $file === $this->resolver->viewName(self::FOLDER.'.'.$key)) {
                return $this->views->file($this->views->getFinder()->find($file), $data)->render();
            }
        }

        return '';
    }

    /**
     * Part keys to try, most specific first: ["footer-minimal", "footer"].
     *
     * @return list<string>
     */
    public function keys(string $name, ?string $variant = null): array
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return [];
        }

        $keys = [];

        if ($variant !== null && $variant !== '' && preg_match(self::NAME_PATTERN, $variant) === 1) {
            $keys[] = "{$name}-{$variant}";
        }

        $keys[] = $name;

        return $keys;
    }

    /**
     * Blocks of a part kept in a theme file, with the same wrapper as an edited part.
     *
     * @param  array<int, mixed>  $blocks
     */
    public function renderStored(string $name, string $key, array $blocks): string
    {
        return $this->renderBlocks($name, $key, $blocks);
    }

    /**
     * @param  array<int, mixed>  $blocks
     */
    protected function renderBlocks(string $name, string $key, array $blocks): string
    {
        $tag = self::WRAPPERS[$name] ?? 'div';

        $classes = 'site-part site-part-'.e($name).($key !== $name ? ' site-part-'.e($key) : '');

        return '<'.$tag.' class="'.$classes.'" data-site-part="'.e($key).'">'.$this->blocks->render($blocks).'</'.$tag.'>';
    }

    // The view to include for a part: parts.{name}-{variant}, then parts.{name}. Null when the theme has neither.
    public function view(string $name, ?string $variant = null): ?string
    {
        $names = array_map(fn (string $key): string => self::FOLDER.'.'.$key, $this->keys($name, $variant));

        if ($names === []) {
            return null;
        }

        $lookup = $this->resolver->resolve(new TemplateCandidates(TemplateContext::Part, $names));

        return $lookup->chosen === null ? null : $this->resolver->viewName($lookup->chosen);
    }

    /**
     * Part files a theme and its parents ship, like ["footer", "footer-minimal", "header"].
     *
     * @return list<string>
     */
    public function available(Theme $theme): array
    {
        $parts = [];

        foreach ($this->themes->lineage($theme) as $member) {
            foreach (glob($member->viewsPath().'/'.self::FOLDER.'/*.blade.php') ?: [] as $file) {
                $parts[] = basename($file, '.blade.php');
            }
        }

        $parts = array_values(array_unique($parts));
        sort($parts);

        return $parts;
    }
}
