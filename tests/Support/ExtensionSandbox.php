<?php

namespace Tests\Support;

use App\Verso\Plugins\PluginDiscovery;
use App\Verso\Plugins\PluginManager;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\File;

// A throwaway folder with its own plugins, themes and extension storage, for install tests.
class ExtensionSandbox
{
    public function __construct(public readonly string $root) {}

    public static function create(): self
    {
        $sandbox = new self(storage_path('framework/testing/extensions-'.uniqid()));

        File::ensureDirectoryExists($sandbox->pluginsPath());
        File::copyDirectory(base_path('themes/default'), $sandbox->themesPath().'/default');

        config([
            'verso.plugins.paths' => [$sandbox->pluginsPath()],
            'verso.themes.path' => str_replace(base_path().'/', '', $sandbox->themesPath()),
            'verso.extensions.storage_path' => $sandbox->root.'/storage',
        ]);

        app()->forgetInstance(PluginDiscovery::class);
        app()->forgetInstance(PluginManager::class);
        app()->forgetInstance(ThemeManager::class);

        return $sandbox;
    }

    public function pluginsPath(): string
    {
        return $this->root.'/plugins';
    }

    public function themesPath(): string
    {
        return $this->root.'/themes';
    }

    public function zipPath(string $name): string
    {
        return $this->root.'/zips/'.$name.'.zip';
    }

    public function destroy(): void
    {
        File::deleteDirectory($this->root);
    }
}
