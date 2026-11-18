<?php

namespace App\Verso\System;

use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\System\Config\ConfigOverlay;
use App\Verso\System\Config\ConfigRegistry;
use App\Verso\System\Config\CoreParameters;
use App\Verso\System\Health\PermissionFixer;
use App\Verso\System\Health\PermissionInspector;
use App\Verso\System\Health\ProcessIdentity;
use App\Verso\System\Server\ManagedIniFile;
use App\Verso\System\Server\PhpIni;
use App\Verso\System\Server\RuntimeLimits;
use App\Verso\System\Server\ServerAdvisor;
use App\Verso\System\Server\UploadLimits;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

// Boots System settings (the config registry and its overlay), PHP limits and the Server health page.
class SystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConfigRegistry::class, function (): ConfigRegistry {
            $registry = new ConfigRegistry;
            (new CoreParameters)->register($registry);

            return $registry;
        });
        $this->app->singleton(ConfigOverlay::class, fn (Application $app): ConfigOverlay => new ConfigOverlay(
            config: $app->make('config'),
            registry: $app->make(ConfigRegistry::class),
            settings: $app->make(Settings::class),
        ));
        $this->app->singleton(PhpIni::class);
        $this->app->singleton(ProcessIdentity::class, fn (): ProcessIdentity => ProcessIdentity::current());

        $this->app->bind(UploadLimits::class, fn (Application $app): UploadLimits => new UploadLimits(
            ini: $app->make(PhpIni::class),
            overheadKb: max(0, (int) config('verso.system.server.upload_overhead_kb')),
            webServerMb: max(0, (int) config('verso.system.server.web_server_body_mb')),
        ));
        $this->app->bind(RuntimeLimits::class, fn (Application $app): RuntimeLimits => new RuntimeLimits(
            ini: $app->make(PhpIni::class),
            memoryMb: (int) config('verso.system.heavy_tasks.memory_mb'),
            timeSeconds: (int) config('verso.system.heavy_tasks.time_limit'),
        ));
        $this->app->bind(ServerAdvisor::class, fn (Application $app): ServerAdvisor => new ServerAdvisor(
            ini: $app->make(PhpIni::class),
            registry: $app->make(ConfigRegistry::class),
            config: $app->make('config'),
            serverSoftware: $this->serverValue('SERVER_SOFTWARE'),
        ));
        $this->app->bind(ManagedIniFile::class, fn (Application $app): ManagedIniFile => new ManagedIniFile(
            publicPath: public_path(),
            backupPath: (string) config('verso.system.backup_path'),
            userIniName: $app->make(PhpIni::class)->userIniFilename(),
        ));
        $this->app->bind(PermissionInspector::class, fn (Application $app): PermissionInspector => new PermissionInspector(
            basePath: base_path(),
            writable: $this->stringList(config('verso.system.health.writable')),
            optional: $this->stringList(config('verso.system.health.optional')),
            scan: $this->stringList(config('verso.system.health.scan')),
            scanLimit: max(1, (int) config('verso.system.health.scan_limit')),
            identity: $app->make(ProcessIdentity::class),
            envFile: ltrim(str_replace(base_path(), '', $app->environmentFilePath()), '/\\'),
            debug: (bool) config('app.debug'),
            production: $app->isProduction(),
            documentRoot: $this->serverValue('DOCUMENT_ROOT'),
        ));
        $this->app->bind(PermissionFixer::class, fn (Application $app): PermissionFixer => new PermissionFixer(
            inspector: $app->make(PermissionInspector::class),
            linkStorage: fn (): bool => Artisan::call('storage:link') === 0,
        ));

        // Runs after the plugin providers are registered (PluginsServiceProvider is listed first), before any boot().
        $this->app->booting(fn () => $this->app->make(ConfigOverlay::class)->boot());
    }

    public function boot(): void
    {
        AdminRoutes::load(base_path('routes/verso/system.php'));

        $this->app->booted(function (): void {
            // "System" only groups its two pages and opens the first one the user may see.
            AdminNav::add(new NavItem(key: 'system', label: 'System', route: '', icon: 'customize', order: 50, capability: Capability::ManageSystem, group: NavGroup::Administration));
            AdminNav::add(new NavItem(key: 'system-settings', label: 'System settings', route: 'admin.system.settings.index', icon: 'customize', order: 10, capability: Capability::ManageSystem, group: NavGroup::Administration, parent: 'system', keywords: ['php', 'limits', 'environment', 'config', 'sistema']));
            AdminNav::add(new NavItem(key: 'server-health', label: 'Server health', route: 'admin.system.health.index', icon: 'shield', order: 20, capability: Capability::ManageSystem, group: NavGroup::Administration, parent: 'system', keywords: ['status', 'checks', 'diagnostics', 'servidor', 'salud']));
        });
    }

    // A $_SERVER value of the current web request, or "" in the console.
    protected function serverValue(string $name): string
    {
        if ($this->app->runningInConsole()) {
            return '';
        }

        $value = $this->app->make(Request::class)->server->get($name);

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        return array_values(array_map(strval(...), (array) $value));
    }
}
