<?php

namespace App\Verso\Extensions;

use App\Verso\Extensions\Actions\DeleteExtensionData;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Exceptions\ExtensionInstallFailed;
use App\Verso\Extensions\Exceptions\InvalidPackage;
use App\Verso\Extensions\Exceptions\StagedPackageNotFound;
use App\Verso\Extensions\Packages\PackageExtractor;
use App\Verso\Extensions\Packages\PackageInspector;
use App\Verso\Plugins\Actions\DisablePlugin;
use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\PluginManager;
use App\Verso\Theme\Actions\ActivateTheme;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

// Stages, installs, updates and removes plugins and themes. Updates keep a backup and roll back on failure.
class ExtensionInstaller
{
    // Name of the zip and of the details file inside a staging folder.
    protected const STAGED_ZIP = 'package.zip';

    protected const STAGED_META = 'meta.json';

    public function __construct(
        protected PackageInspector $inspector,
        protected PackageExtractor $extractor,
        protected PluginManager $plugins,
        protected ThemeManager $themes,
        protected EnablePlugin $enablePlugin,
        protected DisablePlugin $disablePlugin,
        protected ActivateTheme $activateTheme,
        protected Filesystem $files,
        protected string $storagePath,
        protected int $stagingTtlMinutes,
        protected int $backupsKept,
        protected ?DeleteExtensionData $deleteData = null,
    ) {}

    // Copy a zip to the staging folder after checking it. The admin must accept it before it is installed.
    public function stage(string $zipPath, ExtensionKind $kind, string $source = 'upload'): StagedPackage
    {
        $this->pruneStaging();

        $package = $this->inspector->inspect($zipPath, $kind);
        $token = (string) Str::uuid();
        $folder = $this->stagingFolder($token);

        $this->files->ensureDirectoryExists($folder, 0755, true);
        $this->files->copy($zipPath, $folder.DIRECTORY_SEPARATOR.self::STAGED_ZIP);
        $this->files->put($folder.DIRECTORY_SEPARATOR.self::STAGED_META, (string) json_encode([
            'kind' => $kind->value,
            'source' => $source,
            'staged_at' => CarbonImmutable::now()->toIso8601String(),
        ]));

        return $this->staged($token);
    }

    // A staged package by token. The zip is checked again, so a changed file is never trusted.
    public function staged(string $token): StagedPackage
    {
        if (! Str::isUuid($token)) {
            throw StagedPackageNotFound::token($token);
        }

        $folder = $this->stagingFolder($token);
        $zip = $folder.DIRECTORY_SEPARATOR.self::STAGED_ZIP;

        try {
            $meta = json_decode((string) @file_get_contents($folder.DIRECTORY_SEPARATOR.self::STAGED_META), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw StagedPackageNotFound::token($token);
        }

        $kind = is_array($meta) ? ExtensionKind::tryFrom((string) ($meta['kind'] ?? '')) : null;

        if ($kind === null || ! is_file($zip)) {
            throw StagedPackageNotFound::token($token);
        }

        $stagedAt = CarbonImmutable::parse((string) $meta['staged_at']);

        if ($stagedAt->addMinutes($this->stagingTtlMinutes)->isPast()) {
            $this->discard($token);

            throw StagedPackageNotFound::token($token);
        }

        $package = $this->inspector->inspect($zip, $kind);

        return new StagedPackage(
            token: $token,
            package: $package,
            zipPath: $zip,
            source: (string) ($meta['source'] ?? 'upload'),
            sha256: (string) hash_file('sha256', $zip),
            stagedAt: $stagedAt,
            installedVersion: $this->installedVersion($kind, $package->slug),
        );
    }

    // Forget a staged package without installing it.
    public function discard(string $token): void
    {
        if (Str::isUuid($token)) {
            $this->files->deleteDirectory($this->stagingFolder($token));
        }
    }

    // Install or update a staged package. Plugins that were enabled stay enabled and run their migrations.
    public function install(string $token, bool $activate = false): InstallResult
    {
        $staged = $this->staged($token);
        $package = $staged->package;
        $kind = $package->kind;

        if ($staged->isDowngrade()) {
            throw ExtensionInstallFailed::because(__('Version :version is older than the installed version :installed.', ['version' => $package->version, 'installed' => (string) $staged->installedVersion]));
        }

        $target = $this->targetPath($kind, $package->slug);
        $work = $this->storagePath.DIRECTORY_SEPARATOR.'tmp'.DIRECTORY_SEPARATOR.Str::uuid();
        $unpacked = $work.DIRECTORY_SEPARATOR.$package->slug;

        try {
            $this->extractor->extract($staged->zipPath, $package, $unpacked);
            $backup = $this->swap($kind, $package->slug, $staged->installedVersion, $unpacked, $target);
        } finally {
            $this->files->deleteDirectory($work);
        }

        try {
            $activated = $this->activate($kind, $package->slug, $activate);
        } catch (Throwable $exception) {
            $this->rollback($target, $backup);

            throw ExtensionInstallFailed::because(__(':name :version could not be enabled, so the change was rolled back. :error', ['name' => $package->name, 'version' => $package->version, 'error' => $exception->getMessage()]));
        }

        $this->pruneBackups($kind, $package->slug);
        $this->discard($token);

        return new InstallResult(
            kind: $kind,
            slug: $package->slug,
            name: $package->name,
            version: $package->version,
            previousVersion: $staged->installedVersion,
            activated: $activated,
        );
    }

    // Disable a plugin, then delete its files. Themes in use cannot be removed.
    // With $deleteData, the plugin tables and settings (or the theme settings) are deleted first. Without it they stay.
    public function remove(ExtensionKind $kind, string $slug, bool $deleteData = false): void
    {
        $reason = $this->removalProblem($kind, $slug);

        if ($reason !== null) {
            throw ExtensionInstallFailed::because($reason);
        }

        if ($deleteData && $this->deleteData !== null) {
            $this->deleteData->handle($kind, $slug);
        }

        if ($kind === ExtensionKind::Plugin) {
            $this->disablePlugin->handle($slug);
        }

        $this->files->deleteDirectory($this->targetPath($kind, $slug));
        $this->refresh();
    }

    // Why a plugin or theme cannot be removed, or null when it can.
    public function removalProblem(ExtensionKind $kind, string $slug): ?string
    {
        if ($this->installedVersion($kind, $slug) === null) {
            return $kind === ExtensionKind::Plugin
                ? __('The plugin ":slug" is not installed in :path.', ['slug' => $slug, 'path' => $kind->installPath()])
                : __('The theme ":slug" is not installed in :path.', ['slug' => $slug, 'path' => $kind->installPath()]);
        }

        if ($kind === ExtensionKind::Theme && $this->themes->active()->slug === $slug) {
            return __('This theme is in use. Activate another theme first.');
        }

        if ($kind === ExtensionKind::Theme && $this->themes->fallback()?->slug === $slug) {
            $missing = $this->themes->missingRequiredViews($this->themes->active());

            if ($missing !== []) {
                return __('The active theme still uses these views from this theme: :views.', ['views' => implode(', ', $missing)]);
            }
        }

        if ($kind === ExtensionKind::Theme && in_array($slug, array_map(fn (Theme $theme): string => $theme->slug, $this->themes->lineage($this->themes->active())), true)) {
            return __('The active theme is a child of this theme. Activate another theme first.');
        }

        return null;
    }

    // The installed version of a plugin or theme in the install folder, or null.
    public function installedVersion(ExtensionKind $kind, string $slug): ?string
    {
        $this->refresh();

        if ($kind === ExtensionKind::Plugin) {
            $plugin = $this->plugins->find($slug);

            return $plugin !== null && $this->isInside($plugin->path, $kind) ? ($plugin->manifest->version ?? '0.0.0') : null;
        }

        $theme = $this->themes->find($slug);

        return $theme !== null && $this->isInside($theme->path, $kind) ? $theme->version : null;
    }

    // Delete staged packages older than the staging time limit.
    public function pruneStaging(): void
    {
        $limit = CarbonImmutable::now()->subMinutes($this->stagingTtlMinutes)->getTimestamp();

        foreach ($this->folders($this->storagePath.DIRECTORY_SEPARATOR.'staging') as $folder) {
            if ((int) filemtime($folder) < $limit) {
                $this->files->deleteDirectory($folder);
            }
        }
    }

    // Move the old version to a backup folder and the new files into place. Returns the backup path.
    protected function swap(ExtensionKind $kind, string $slug, ?string $oldVersion, string $unpacked, string $target): ?string
    {
        $backup = null;

        if (is_dir($target)) {
            $backup = $this->backupFolder($kind, $slug).DIRECTORY_SEPARATOR.now()->format('YmdHisv').'-'.($oldVersion ?? 'unknown');
            $this->files->ensureDirectoryExists(dirname($backup), 0755, true);

            if (! $this->files->moveDirectory($target, $backup)) {
                throw ExtensionInstallFailed::because(__('The current version in :path could not be moved to a backup.', ['path' => $target]));
            }
        }

        $this->files->ensureDirectoryExists(dirname($target), 0755, true);

        if (! $this->files->moveDirectory($unpacked, $target)) {
            $this->rollback($target, $backup);

            throw ExtensionInstallFailed::because(__('The new files could not be moved to :path.', ['path' => $target]));
        }

        $this->refresh();

        return $backup;
    }

    // Check the new version works. Enable or activate it when asked or when the old version was in use.
    protected function activate(ExtensionKind $kind, string $slug, bool $activate): bool
    {
        if ($kind === ExtensionKind::Plugin) {
            $plugin = $this->plugins->get($slug);

            if (! $plugin->isValid()) {
                throw InvalidPackage::because($plugin->problems());
            }

            if ($activate || $plugin->enabled) {
                $this->enablePlugin->handle($slug);

                return true;
            }

            return false;
        }

        Theme::fromDirectory($this->targetPath($kind, $slug));

        if ($activate) {
            $this->activateTheme->handle($slug);

            return true;
        }

        if ($this->themes->active()->slug === $slug) {
            $this->themes->registerViews();

            return true;
        }

        return false;
    }

    // Put the backup back in place, or delete a failed fresh install.
    protected function rollback(string $target, ?string $backup): void
    {
        $this->files->deleteDirectory($target);

        if ($backup !== null) {
            $this->files->moveDirectory($backup, $target);
        }

        $this->refresh();
    }

    // Keep only the newest backups of a package.
    protected function pruneBackups(ExtensionKind $kind, string $slug): void
    {
        $backups = $this->folders($this->backupFolder($kind, $slug));
        sort($backups);

        foreach (array_slice($backups, 0, max(0, count($backups) - max(0, $this->backupsKept))) as $folder) {
            $this->files->deleteDirectory($folder);
        }
    }

    protected function targetPath(ExtensionKind $kind, string $slug): string
    {
        return $kind->installPath().DIRECTORY_SEPARATOR.$slug;
    }

    // Folder that holds the backups of one package.
    public function backupFolder(ExtensionKind $kind, string $slug): string
    {
        return $this->storagePath.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.$kind->value.DIRECTORY_SEPARATOR.$slug;
    }

    protected function stagingFolder(string $token): string
    {
        return $this->storagePath.DIRECTORY_SEPARATOR.'staging'.DIRECTORY_SEPARATOR.$token;
    }

    // True when the folder sits directly inside the install folder of its kind.
    protected function isInside(string $path, ExtensionKind $kind): bool
    {
        $parent = realpath(dirname($path));

        return $parent !== false && $parent === realpath($kind->installPath());
    }

    /**
     * @return list<string>
     */
    protected function folders(string $path): array
    {
        return is_dir($path) ? array_values($this->files->directories($path)) : [];
    }

    protected function refresh(): void
    {
        $this->plugins->refresh();
        $this->themes->refresh();
    }
}
