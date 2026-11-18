<?php

namespace App\Verso\Extensions\Enums;

use App\Verso\Core\Enums\Capability;
use App\Verso\Theme\ThemeManager;

// The two kinds of packages an admin can install: plugins and themes.
enum ExtensionKind: string
{
    case Plugin = 'plugin';
    case Theme = 'theme';

    // Name of the manifest file at the top of the package.
    public function manifestName(): string
    {
        return match ($this) {
            self::Plugin => (string) config('verso.plugins.manifest'),
            self::Theme => 'theme.json',
        };
    }

    // Folder where packages of this kind are installed.
    public function installPath(): string
    {
        return match ($this) {
            self::Plugin => rtrim((string) (array_values((array) config('verso.plugins.paths'))[0] ?? base_path('plugins')), '/\\'),
            self::Theme => ThemeManager::configuredPath(),
        };
    }

    // Capability a user needs to install, update or remove this kind.
    public function capability(): Capability
    {
        return match ($this) {
            self::Plugin => Capability::InstallPlugins,
            self::Theme => Capability::ManageThemes,
        };
    }

    /**
     * Folders where PHP files may live inside a package of this kind.
     *
     * @return list<string>
     */
    public function phpFolders(): array
    {
        $folders = match ($this) {
            self::Plugin => config('verso.extensions.plugin_php_folders'),
            self::Theme => config('verso.extensions.theme_php_folders'),
        };

        return array_values(array_map(fn (mixed $folder): string => trim((string) $folder, '/'), (array) $folders));
    }

    public function label(): string
    {
        return match ($this) {
            self::Plugin => __('Plugin'),
            self::Theme => __('Theme'),
        };
    }
}
