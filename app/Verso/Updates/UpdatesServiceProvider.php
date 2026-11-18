<?php

namespace App\Verso\Updates;

use App\Models\User;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardNotice;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Updates\Console\UpdateCommand;
use App\Verso\Updates\Feed\ReleaseFeed;
use App\Verso\Updates\Feed\UpdateChecker;
use App\Verso\Updates\Jobs\CheckForUpdates;
use App\Verso\Updates\Support\UpdateSettings;
use App\Verso\Updates\Updater\ArtisanRunner;
use App\Verso\Updates\Updater\CoreFiles;
use App\Verso\Updates\Updater\CoreUpdater;
use App\Verso\Updates\Updater\DatabaseBackup;
use App\Verso\Updates\Updater\HealthCheck;
use App\Verso\Updates\Updater\PackageDownloader;
use App\Verso\Updates\Updater\PackageUnpacker;
use App\Verso\Updates\Updater\PackageVerifier;
use App\Verso\Updates\Updater\UpdateLock;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

// Boots core updates: the release feed check, the updater, the Updates page and the verso:update command.
class UpdatesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReleaseFeed::class, fn (): ReleaseFeed => new ReleaseFeed(
            url: trim((string) config('verso.updates.feed_url')),
            timeout: max(1, (int) config('verso.updates.feed_timeout')),
            assetName: (string) config('verso.updates.asset_name'),
            checksumSuffix: (string) config('verso.updates.checksum_suffix'),
            signatureSuffix: (string) config('verso.updates.signature_suffix'),
            securityMarker: (string) config('verso.updates.security_marker'),
            downloadSchemes: $this->stringList(config('verso.updates.download_schemes')),
        ));

        $this->app->bind(UpdateChecker::class, fn (Application $app): UpdateChecker => new UpdateChecker(
            feed: $app->make(ReleaseFeed::class),
            cache: $app->make('cache')->store(),
            settings: $app->make(UpdateSettings::class),
            currentVersion: (string) config('verso.version'),
            cacheKey: (string) config('verso.updates.cache_key'),
            cacheTtl: max(60, (int) config('verso.updates.cache_ttl')),
        ));

        $this->app->bind(PackageDownloader::class, fn (Application $app): PackageDownloader => new PackageDownloader(
            files: $app->make(Filesystem::class),
            timeout: max(1, (int) config('verso.updates.download_timeout')),
            maxBytes: max(1, (int) config('verso.updates.max_download_kb')) * 1024,
        ));

        $this->app->bind(PackageVerifier::class, fn (): PackageVerifier => new PackageVerifier((string) config('verso.updates.public_key')));

        $this->app->bind(DatabaseBackup::class, fn (Application $app): DatabaseBackup => new DatabaseBackup(
            db: $app->make(DatabaseManager::class),
            files: $app->make(Filesystem::class),
            connection: (string) config('database.default'),
            binaries: array_map(strval(...), (array) config('verso.updates.binaries')),
            timeout: max(1, (int) config('verso.updates.database_timeout')),
        ));

        $this->app->bind(CoreFiles::class, fn (Application $app): CoreFiles => new CoreFiles(
            files: $app->make(Filesystem::class),
            keepPaths: [...$this->stringList(config('verso.updates.keep_paths')), ...$this->sqliteKeepPath($app)],
            replacePaths: $this->stringList(config('verso.updates.replace_paths')),
        ));

        $this->app->bind(ArtisanRunner::class, fn (): ArtisanRunner => new ArtisanRunner(
            installPath: $this->installPath(),
            phpBinary: (string) config('verso.updates.php_binary'),
            timeout: max(1, (int) config('verso.updates.command_timeout')),
        ));

        $this->app->bind(HealthCheck::class, fn (): HealthCheck => new HealthCheck(
            baseUrl: (string) (config('verso.updates.health_check.base_url') ?: config('app.url')),
            paths: $this->stringList(config('verso.updates.health_check.paths')),
            timeout: max(1, (int) config('verso.updates.health_check.timeout')),
        ));

        $this->app->bind(UpdateLock::class, fn (Application $app): UpdateLock => new UpdateLock(
            $app->make(Filesystem::class),
            $this->storagePath().'/update.lock',
        ));

        $this->app->bind(CoreUpdater::class, fn (Application $app): CoreUpdater => new CoreUpdater(
            downloader: $app->make(PackageDownloader::class),
            verifier: $app->make(PackageVerifier::class),
            unpacker: $app->make(PackageUnpacker::class),
            coreFiles: $app->make(CoreFiles::class),
            database: $app->make(DatabaseBackup::class),
            artisan: $app->make(ArtisanRunner::class),
            health: $app->make(HealthCheck::class),
            lock: $app->make(UpdateLock::class),
            files: $app->make(Filesystem::class),
            installPath: $this->installPath(),
            storagePath: $this->storagePath(),
            currentVersion: (string) config('verso.version'),
            backupsKept: max(1, (int) config('verso.updates.backups_kept')),
            maintenanceRetry: max(1, (int) config('verso.updates.maintenance_retry')),
        ));
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/updates.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([UpdateCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->job(new CheckForUpdates)
                ->cron((string) config('verso.updates.check_schedule'))
                ->name('verso:check-for-updates')
                ->withoutOverlapping();
        });

        $this->app->booted(function (): void {
            AdminNav::add(new NavItem(
                key: 'updates',
                label: 'Updates',
                route: 'admin.updates.index',
                icon: 'updates',
                order: 40,
                capability: Capability::ManageUpdates,
                group: NavGroup::Administration,
                keywords: ['upgrade', 'version', 'actualizaciones'],
            ));

            $this->app->make(Dashboard::class)->addNotice(new DashboardNotice(
                key: 'core-update',
                resolve: fn (User $user): ?array => $this->updateNotice(),
                capability: Capability::ManageUpdates,
                order: 10,
                icon: 'updates',
            ));
        });
    }

    /**
     * The dashboard notice when a newer release is known. It reads the cache only.
     *
     * @return array{title: string, description: string|null, href: string|null, action: string|null, tone: string}|null
     */
    protected function updateNotice(): ?array
    {
        $check = $this->app->make(UpdateChecker::class)->status();
        $release = $check->availableRelease();

        if ($release === null) {
            return null;
        }

        return [
            'title' => __('Verso :version is available', ['version' => $release->version]),
            'description' => $release->security
                ? __('This release fixes security problems. You run Verso :version.', ['version' => $check->currentVersion])
                : __('You run Verso :version. Read what is new and update in one click.', ['version' => $check->currentVersion]),
            'href' => route('admin.updates.index', absolute: false),
            'action' => __('See the update'),
            'tone' => $release->security ? 'warning' : 'info',
        ];
    }

    protected function installPath(): string
    {
        return rtrim((string) config('verso.updates.install_path'), '/\\');
    }

    protected function storagePath(): string
    {
        return rtrim((string) config('verso.updates.storage_path'), '/\\');
    }

    // Keep the SQLite file when it lives inside the install folder, wherever it is.
    /** @return list<string> */
    protected function sqliteKeepPath(Application $app): array
    {
        $file = $app->make(DatabaseBackup::class)->sqliteFile();
        $root = (string) realpath($this->installPath());
        $real = $file === null ? false : realpath($file);

        if ($real === false || $root === '' || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            return [];
        }

        return [str_replace('\\', '/', substr($real, strlen($root) + 1))];
    }

    /** @return list<string> */
    protected function stringList(mixed $value): array
    {
        return array_values(array_map(strval(...), (array) $value));
    }
}
