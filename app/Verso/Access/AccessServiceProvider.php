<?php

namespace App\Verso\Access;

use App\Models\User;
use App\Verso\Access\Capabilities\CapabilityRegistry;
use App\Verso\Access\Capabilities\ManifestAccess;
use App\Verso\Access\Http\Middleware\ApplyViewAs;
use App\Verso\Access\Permissions\EntryPermissions;
use App\Verso\Access\Permissions\UserPermissions;
use App\Verso\Access\Roles\RoleDefinition;
use App\Verso\Access\Roles\RoleRegistry;
use App\Verso\Access\Roles\Roles;
use App\Verso\Access\Support\AccessAbilities;
use App\Verso\Access\Support\ViewAs;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Theme\ThemeManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Throwable;

// Roles and permissions: the capability registry, roles, per user grants, the roles admin and "view as role".
class AccessServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CapabilityRegistry::class);
        $this->app->singleton(RoleRegistry::class);
        $this->app->singleton(Roles::class);
        $this->app->singleton(UserPermissions::class);
        $this->app->singleton(EntryPermissions::class);
        $this->app->singleton(ManifestAccess::class);
        $this->app->scoped(ViewAs::class);
    }

    public function boot(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('verso.view-as', ApplyViewAs::class);

        Route::bind('role', fn (string $value): RoleDefinition => $this->app->make(Roles::class)->find($value) ?? abort(404));

        // Saved users and deleted users never keep old answers in memory.
        User::saved(fn (User $user) => $user->refreshAccess());
        User::deleted(fn (User $user) => $this->app->make(UserPermissions::class)->forget($user));

        AccessAbilities::define();
        AdminRoutes::load(base_path('routes/verso/access.php'));

        $this->app->booted(function (): void {
            $this->registerThemeAccess();

            AdminNav::add(new NavItem(key: 'users.roles', label: 'Roles', route: 'admin.roles.index', icon: 'users', order: 20, capability: Capability::ManageRoles, group: NavGroup::Administration, parent: 'users', keywords: ['permissions', 'capabilities', 'access', 'roles', 'permisos', 'roles y permisos']));
        });
    }

    // The active theme may declare capabilities and roles in theme.json, like a plugin.
    protected function registerThemeAccess(): void
    {
        try {
            $theme = $this->app->make(ThemeManager::class)->active();
        } catch (Throwable) {
            return;
        }

        $this->app->make(ManifestAccess::class)->registerTheme($theme);
    }
}
