<?php

namespace App\Verso\Users;

use App\Models\User;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Routing\AdminRoutes;
use App\Verso\Users\Policies\UserPolicy;
use App\Verso\Users\Support\AdminGuard;
use App\Verso\Users\Support\ContentOwnership;
use App\Verso\Users\Support\Invitations;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

// Boots the users module: admin pages, invitations and the last admin rule.
class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminGuard::class);
        $this->app->singleton(ContentOwnership::class, fn (): ContentOwnership => new ContentOwnership(
            array_map(strval(...), (array) config('verso.users.reassign_columns')),
        ));
        $this->app->singleton(Invitations::class, fn (): Invitations => new Invitations(
            expiresHours: (int) config('verso.users.invitation_expires_hours'),
            secret: (string) config('app.key'),
        ));
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);

        $this->protectLastAdmin();

        AdminRoutes::load(base_path('routes/verso/users.php'));
        Route::group([], base_path('routes/verso/invitations.php'));

        $this->app->booted(function (): void {
            AdminNav::add(new NavItem(key: 'users', label: 'Users', route: 'admin.users.index', icon: 'users', order: 10, capability: Capability::ListUsers, group: NavGroup::Administration, keywords: ['people', 'accounts', 'roles', 'team', 'usuarios']));
            AdminNav::add(new NavItem(key: 'users.invitations', label: 'Invitations', route: 'admin.users.index', icon: 'users', order: 10, capability: Capability::CreateUsers, group: NavGroup::Administration, parent: 'users', keywords: ['invite', 'pending', 'invitaciones'], fragment: 'invitations'));
        });

        $this->app->make(Dashboard::class)->addStat(new DashboardStat(
            key: 'invitations',
            label: 'Pending invitations',
            count: fn (): int => User::query()->whereNotNull('invited_at')->count(),
            order: 91,
            capability: Capability::CreateUsers,
            route: 'admin.users.index',
            icon: 'users',
        ));
    }

    // Every save and delete checks the rule, also outside the users admin (like the profile page).
    protected function protectLastAdmin(): void
    {
        User::updating(function (User $user): void {
            if ($user->isDirty('role')) {
                $this->app->make(AdminGuard::class)->ensureCanChangeRole($user, $user->role);
            }
        });

        User::deleting(function (User $user): void {
            $this->app->make(AdminGuard::class)->ensureCanDelete($user);
        });
    }
}
