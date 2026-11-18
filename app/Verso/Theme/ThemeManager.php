<?php

namespace App\Verso\Theme;

use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\ManifestCache;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\View\FileViewFinder;
use InvalidArgumentException;
use RuntimeException;

// Finds installed themes, knows the active one and points the "theme" view namespace at it.
class ThemeManager
{
    // View namespace used by public pages, like "theme::home".
    public const NAMESPACE = 'theme';

    // Theme slugs are folder names: lowercase letters, digits, dashes and underscores.
    public const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    // Stylesheet of a theme, printed inline by the layout.
    public const STYLESHEET = 'assets/theme.css';

    // Folder of the public theme files served by the theme.asset route.
    public const ASSETS = 'assets';

    // Views every site needs. A theme without them uses the fallback theme views.
    public const REQUIRED_VIEWS = ['layouts/app', 'home', 'entry', 'archive', 'taxonomy', 'search', '404'];

    /** @var array<string, Theme>|null */
    protected ?array $themes = null;

    // Theme shown instead of the active one, for the customizer preview.
    protected ?string $previewSlug = null;

    /** @var list<string> */
    protected array $pluginViewPaths = [];

    public function __construct(
        protected Settings $settings,
        protected ViewFactory $views,
        protected string $basePath,
        protected string $fallbackSlug = 'default',
        protected ?ManifestCache $manifests = null,
    ) {}

    // Full path of the themes folder from config. Relative paths start at the project root.
    public static function configuredPath(): string
    {
        $path = (string) config('verso.themes.path');

        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1
            ? rtrim($path, '/\\')
            : base_path($path);
    }

    /**
     * Every valid theme, sorted by name. Folders without a valid theme.json are skipped.
     *
     * @return array<string, Theme>
     */
    public function all(): array
    {
        return $this->themes ??= $this->discover();
    }

    public function find(string $slug): ?Theme
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return null;
        }

        return $this->all()[$slug] ?? null;
    }

    public function exists(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    // The theme chosen in settings, or the fallback theme when it is missing.
    public function active(): Theme
    {
        if ($this->previewSlug !== null && ($preview = $this->find($this->previewSlug)) !== null) {
            return $preview;
        }

        $slug = $this->settings->get('theme.active');

        return (is_string($slug) ? $this->find($slug) : null)
            ?? $this->fallback()
            ?? $this->firstInstalled()
            ?? throw new RuntimeException("No theme is installed in [{$this->basePath}].");
    }

    // The fallback theme, or null when it was removed.
    public function fallback(): ?Theme
    {
        return $this->find($this->fallbackSlug);
    }

    // The first installed theme by slug, used when both the chosen and the fallback theme are gone.
    protected function firstInstalled(): ?Theme
    {
        $themes = $this->all();
        ksort($themes);

        return $themes === [] ? null : reset($themes);
    }

    /**
     * The required views the theme and its parents do not have, so another theme must provide them.
     *
     * @return list<string>
     */
    public function missingRequiredViews(Theme $theme): array
    {
        $missing = [];

        foreach (self::REQUIRED_VIEWS as $view) {
            if ($this->locateFile($theme, 'views/'.$view.'.blade.php') === null) {
                $missing[] = $view;
            }
        }

        return $missing;
    }

    // Save a theme as the active one and use it right away.
    public function activate(string $slug): Theme
    {
        $theme = $this->find($slug) ?? throw new InvalidArgumentException(__('Unknown theme [:theme].', ['theme' => $slug]));

        if (($parent = $this->missingParent($theme)) !== null) {
            throw new InvalidArgumentException(__('The parent theme [:parent] of [:theme] is not installed.', ['parent' => $parent, 'theme' => $theme->slug]));
        }

        $this->settings->set('theme.active', $theme->slug);
        $this->registerViews();

        return $theme;
    }

    // Point "theme::" at the active theme views, then at the fallback theme views.
    public function registerViews(): void
    {
        $this->views->replaceNamespace(self::NAMESPACE, $this->viewPaths());

        $finder = $this->views->getFinder();

        if ($finder instanceof FileViewFinder) {
            $finder->flush();
        }
    }

    // Register only the fallback views. Safe to call before the database is ready.
    public function registerFallbackViews(): void
    {
        $this->views->replaceNamespace(self::NAMESPACE, array_values(array_unique([
            $this->basePath.'/'.$this->fallbackSlug.'/views',
            ...$this->pluginViewPaths,
        ])));
    }

    // Add plugin views after the theme views, so a theme can override any of them.
    public function addPluginViews(string $path): void
    {
        if (in_array($path, $this->pluginViewPaths, true)) {
            return;
        }

        $this->pluginViewPaths[] = $path;
        $this->views->addNamespace(self::NAMESPACE, $path);
    }

    /**
     * @return list<string>
     */
    public function pluginViewPaths(): array
    {
        return $this->pluginViewPaths;
    }

    /**
     * View folders in lookup order: the active theme, its parents, the fallback theme, then plugins.
     *
     * @return list<string>
     */
    public function viewPaths(): array
    {
        return array_values(array_unique([
            ...array_map(fn (Theme $theme): string => $theme->viewsPath(), $this->lineage($this->active())),
            ...(($fallback = $this->fallback()) !== null ? [$fallback->viewsPath()] : []),
            ...$this->pluginViewPaths,
        ]));
    }

    /**
     * The theme, then its parent, then the parent of the parent. Stops at a missing parent or a loop.
     *
     * @return non-empty-list<Theme>
     */
    public function lineage(Theme $theme): array
    {
        $lineage = [$theme];
        $seen = [$theme->slug => true];

        while ($theme->parent !== null && ! isset($seen[$theme->parent]) && ($parent = $this->find($theme->parent)) !== null) {
            $lineage[] = $parent;
            $seen[$parent->slug] = true;
            $theme = $parent;
        }

        return $lineage;
    }

    // The slug of a parent theme that is not installed (or that points back in a loop), or null.
    public function missingParent(Theme $theme): ?string
    {
        $lineage = $this->lineage($theme);

        return end($lineage)->parent;
    }

    // The CSS of the active theme and its parents (parents first), or of the fallback theme.
    public function styles(): string
    {
        $styles = [];

        foreach (array_reverse($this->lineage($this->active())) as $theme) {
            if ($theme->hasFile(self::STYLESHEET)) {
                $styles[] = (string) file_get_contents($theme->filePath(self::STYLESHEET));
            }
        }

        $fallback = $this->fallback();

        if ($styles === [] && $fallback !== null && $fallback->hasFile(self::STYLESHEET)) {
            $styles[] = (string) file_get_contents($fallback->filePath(self::STYLESHEET));
        }

        return implode("\n", $styles);
    }

    // Full path of a theme file, looked up in the theme and then in its parents. Null when none has it.
    public function locateFile(Theme $theme, string $file): ?string
    {
        foreach ($this->lineage($theme) as $candidate) {
            if ($candidate->hasFile($file)) {
                return $candidate->filePath($file);
            }
        }

        return null;
    }

    // Full path of a public asset (assets/<path>) of a theme or its parents. Null for unknown themes, odd paths or file types.
    public function assetFile(string $slug, string $path): ?string
    {
        $theme = $this->find($slug);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowed = array_map(strval(...), (array) config('verso.themes.assets.extensions'));

        if ($theme === null || str_contains($path, '..') || str_contains($path, '\\') || ! in_array($extension, $allowed, true)) {
            return null;
        }

        $file = $this->locateFile($theme, self::ASSETS.'/'.ltrim($path, '/'));
        $real = $file === null ? false : realpath($file);

        return $real !== false && str_starts_with($real, realpath($this->basePath).DIRECTORY_SEPARATOR) ? $real : null;
    }

    // URL of a public asset of the active theme, with the file time so a new version is fetched after a change.
    public function assetUrl(string $path): ?string
    {
        $theme = $this->active();
        $file = $this->assetFile($theme->slug, $path);

        if ($file === null) {
            return null;
        }

        return route('theme.asset', ['theme' => $theme->slug, 'path' => ltrim($path, '/')]).'?v='.filemtime($file);
    }

    // Show this theme instead of the active one for the rest of the request.
    public function usePreview(string $slug): void
    {
        $this->previewSlug = $slug;
        $this->registerViews();
    }

    // Forget the list of themes so the next call reads the folders again.
    public function refresh(): void
    {
        $this->themes = null;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /**
     * @return array<string, Theme>
     */
    protected function discover(): array
    {
        $directories = array_values(array_filter(
            glob($this->basePath.'/*', GLOB_ONLYDIR) ?: [],
            fn (string $directory): bool => preg_match(self::SLUG_PATTERN, basename($directory)) === 1,
        ));

        $files = array_map(fn (string $directory): string => $directory.'/theme.json', $directories);
        $read = fn (): array => $this->readManifests($directories);
        $manifests = $this->manifests?->remember('themes', $files, $read) ?? $read();

        $themes = [];

        foreach ($manifests as $directory => $manifest) {
            if (is_array($manifest)) {
                $theme = Theme::fromManifest((string) $directory, $manifest);
                $themes[$theme->slug] = $theme;
            }
        }

        uasort($themes, fn (Theme $a, Theme $b): int => strcasecmp($a->name, $b->name));

        return array_map(fn (Theme $theme): Theme => $this->inherit($theme, $themes), $themes);
    }

    /**
     * Add the settings and templates of every parent, from the oldest parent down to the theme.
     *
     * @param  array<string, Theme>  $themes
     */
    protected function inherit(Theme $theme, array $themes): Theme
    {
        $parents = [];
        $seen = [$theme->slug => true];
        $current = $theme;

        while ($current->parent !== null && ! isset($seen[$current->parent]) && isset($themes[$current->parent])) {
            $current = $themes[$current->parent];
            $seen[$current->slug] = true;
            $parents[] = $current;
        }

        if ($parents === []) {
            return $theme;
        }

        $base = array_pop($parents);

        foreach (array_reverse($parents) as $parent) {
            $base = $parent->inheriting($base);
        }

        return $theme->inheriting($base);
    }

    /**
     * The theme.json of each folder, or null when it is missing or broken.
     *
     * @param  list<string>  $directories
     * @return array<string, array<mixed>|null>
     */
    protected function readManifests(array $directories): array
    {
        $manifests = [];

        foreach ($directories as $directory) {
            try {
                $manifests[$directory] = Theme::readManifest($directory);
            } catch (InvalidArgumentException) {
                $manifests[$directory] = null;
            }
        }

        return $manifests;
    }
}
