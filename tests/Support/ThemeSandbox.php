<?php

namespace Tests\Support;

use App\Verso\Core\Settings\Settings;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;

// A throwaway themes folder with a copy of the default theme, for template and child theme tests.
class ThemeSandbox
{
    public function __construct(public readonly string $root) {}

    public static function create(): self
    {
        $sandbox = new self(storage_path('framework/testing/themes-'.uniqid()));

        File::copyDirectory(base_path('themes/default'), $sandbox->root.'/default');

        config(['verso.themes.path' => str_replace(base_path().'/', '', $sandbox->root)]);
        app()->forgetInstance(ThemeManager::class);

        return $sandbox;
    }

    /**
     * Write a theme folder with a theme.json and the given files.
     *
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $files  path inside the theme => content
     */
    public function theme(string $slug, array $manifest = [], array $files = []): self
    {
        File::ensureDirectoryExists($this->root.'/'.$slug.'/views');
        File::put($this->root.'/'.$slug.'/theme.json', json_encode(['name' => ucwords(str_replace('-', ' ', $slug)), ...$manifest]));

        return $this->files($slug, $files);
    }

    /**
     * @param  array<string, string>  $files  path inside the theme => content
     */
    public function files(string $slug, array $files): self
    {
        foreach ($files as $file => $content) {
            File::ensureDirectoryExists(dirname($this->root.'/'.$slug.'/'.$file));
            File::put($this->root.'/'.$slug.'/'.$file, $content);
        }

        app(ThemeManager::class)->refresh();

        return $this;
    }

    // Make a theme the active one and point the theme views at it.
    public function activate(string $slug): self
    {
        $themes = app(ThemeManager::class);
        $themes->refresh();
        app(Settings::class)->set('theme.active', $slug);
        $themes->registerViews();

        return $this;
    }

    public function destroy(): void
    {
        File::deleteDirectory($this->root);
        app()->forgetInstance(ThemeManager::class);
    }
}
