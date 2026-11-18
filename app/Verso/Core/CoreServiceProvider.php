<?php

namespace App\Verso\Core;

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\AdminNavRegistry;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Http\Middleware\ApplyRedirects;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Core\Settings\Settings;
use App\Verso\Core\Support\ManifestCache;
use App\Verso\Core\Support\OptionalRoles;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

// Boots the core of Verso: roles, capabilities, admin nav and dashboard.
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminNavRegistry::class);
        $this->app->singleton(Dashboard::class);
        $this->app->singleton(OptionalRoles::class);
        $this->app->singleton(ManifestCache::class, fn (Application $app): ManifestCache => new ManifestCache(
            cache: config('verso.manifest_cache.enabled') ? $app->make('cache')->store(config('verso.manifest_cache.store') ?: null) : null,
            prefix: (string) config('verso.manifest_cache.prefix'),
            ttl: (int) config('verso.manifest_cache.ttl'),
        ));
        $this->app->singleton(Settings::class, fn (Application $app): Settings => new Settings(
            cache: $app->make('cache.store'),
            cacheKey: (string) config('verso.settings.cache_key'),
            cacheTtl: config('verso.settings.cache_ttl'),
            defaults: (array) config('verso.settings.defaults'),
        ));
    }

    public function boot(): void
    {
        $this->registerCapabilityGate();

        // Settings read before migrate:fresh would otherwise outlive the wiped table.
        Event::listen([DatabaseRefreshed::class, MigrationsEnded::class], fn () => $this->app->make(Settings::class)->forgetCache());

        $this->app->make(Router::class)->aliasMiddleware('verso.redirects', ApplyRedirects::class);

        AdminRoutes::load(base_path('routes/verso/core.php'));

        AdminNav::add(new NavItem(key: 'dashboard', label: 'Dashboard', route: 'admin.dashboard', icon: 'dashboard', order: 0, group: NavGroup::Dashboard, keywords: ['home', 'overview', 'start', 'inicio', 'escritorio']));

        $this->app->make(Dashboard::class)->addStat(new DashboardStat(
            key: 'users',
            label: 'Users',
            count: fn (): int => User::query()->count(),
            order: 90,
            capability: Capability::ListUsers,
            icon: 'users',
        ));
    }

    // Lets $user->can('publish_entries') work for every capability: core, content types and plugins.
    protected function registerCapabilityGate(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (Capability::fromAbility($ability) === null && ! $this->app->make(CapabilityRegistry::class)->has($ability)) {
                return null;
            }

            return $user->hasCapability($ability);
        });
    }
}
