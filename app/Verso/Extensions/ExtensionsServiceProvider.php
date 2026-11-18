<?php

namespace App\Verso\Extensions;

use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Extensions\Actions\DeleteExtensionData;
use App\Verso\Extensions\Console\SandboxPluginCommand;
use App\Verso\Extensions\Console\SandboxThemeCommand;
use App\Verso\Extensions\Console\VerifyPluginCommand;
use App\Verso\Extensions\Console\VerifyThemeCommand;
use App\Verso\Extensions\Packages\PackageExtractor;
use App\Verso\Extensions\Packages\PackageInspector;
use App\Verso\Extensions\Packages\PackageRules;
use App\Verso\Extensions\Registry\RegistryClient;
use App\Verso\Extensions\Verification\CapabilityMap;
use App\Verso\Extensions\Verification\CodeScanner;
use App\Verso\Extensions\Verification\Sandbox\SandboxRunner;
use App\Verso\Extensions\Verification\Scanners\AssetScanner;
use App\Verso\Extensions\Verification\Scanners\PhpScanner;
use App\Verso\Extensions\Verification\Scanners\ThemeScanner;
use App\Verso\Plugins\Actions\DisablePlugin;
use App\Verso\Plugins\Actions\EnablePlugin;
use App\Verso\Plugins\PluginManager;
use App\Verso\Theme\Actions\ActivateTheme;
use App\Verso\Theme\ThemeManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

// Boots the extensions module: install, update and remove plugins and themes from zips or a registry.
class ExtensionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PackageRules::class, fn (): PackageRules => PackageRules::fromConfig());

        $this->app->bind(ExtensionInstaller::class, fn (Application $app): ExtensionInstaller => new ExtensionInstaller(
            inspector: $app->make(PackageInspector::class),
            extractor: $app->make(PackageExtractor::class),
            plugins: $app->make(PluginManager::class),
            themes: $app->make(ThemeManager::class),
            enablePlugin: $app->make(EnablePlugin::class),
            disablePlugin: $app->make(DisablePlugin::class),
            activateTheme: $app->make(ActivateTheme::class),
            files: $app->make(Filesystem::class),
            storagePath: rtrim((string) config('verso.extensions.storage_path'), '/\\'),
            stagingTtlMinutes: max(1, (int) config('verso.extensions.staging_ttl_minutes')),
            backupsKept: max(0, (int) config('verso.extensions.backups_kept')),
            deleteData: $app->make(DeleteExtensionData::class),
        ));

        $this->app->bind(RegistryClient::class, fn (Application $app): RegistryClient => new RegistryClient(
            cache: $app->make('cache')->store(),
            files: $app->make(Filesystem::class),
            url: trim((string) config('verso.extensions.registry.url')),
            timeout: max(1, (int) config('verso.extensions.registry.timeout')),
            cacheTtl: max(0, (int) config('verso.extensions.registry.cache_ttl')),
            cacheKey: (string) config('verso.extensions.registry.cache_key'),
            downloadSchemes: array_values(array_map(strval(...), (array) config('verso.extensions.registry.download_schemes'))),
            maxDownloadBytes: max(1, (int) config('verso.extensions.max_upload_kb')) * 1024,
        ));

        $this->registerVerification();
    }

    // Bind the plugin and theme verifiers, their scanners and the sandbox with values from config.
    protected function registerVerification(): void
    {
        $this->app->bind(PhpScanner::class, fn (Application $app): PhpScanner => new PhpScanner(
            capabilities: $app->make(CapabilityMap::class),
            encodedStringLength: max(32, (int) config('verso.extensions.verification.encoded_string_length')),
        ));

        $this->app->bind(SandboxRunner::class, fn (Application $app): SandboxRunner => new SandboxRunner(
            files: $app->make(Filesystem::class),
            workPath: rtrim((string) config('verso.extensions.storage_path'), '/\\').DIRECTORY_SEPARATOR.'sandbox',
            enabled: (bool) config('verso.extensions.verification.sandbox.enabled'),
            timeout: max(1, (int) config('verso.extensions.verification.sandbox.timeout')),
            phpBinary: (string) config('verso.extensions.verification.sandbox.php_binary'),
            maxOutput: max(200, (int) config('verso.extensions.verification.sandbox.max_output')),
        ));

        $this->app->bind(ThemeScanner::class, fn (): ThemeScanner => new ThemeScanner(
            fontHosts: array_values(array_map(fn (mixed $host): string => strtolower(trim((string) $host)), (array) config('verso.extensions.verification.themes.remote_font_hosts'))),
        ));

        $this->app->bind(CodeScanner::class, fn (Application $app): CodeScanner => new CodeScanner(
            php: $app->make(PhpScanner::class),
            assets: $app->make(AssetScanner::class),
            maxFileBytes: max(1, (int) config('verso.extensions.verification.max_file_kb')) * 1024,
            scriptExtensions: array_values(array_map(fn (mixed $extension): string => strtolower((string) $extension), (array) config('verso.extensions.verification.script_extensions'))),
        ));
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/extensions.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([VerifyPluginCommand::class, SandboxPluginCommand::class, VerifyThemeCommand::class, SandboxThemeCommand::class]);
        }
    }
}
