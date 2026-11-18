<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests to login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

it('requires a verified email', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

it('shows the user count to admins', function () {
    User::factory()->count(2)->create();
    $admin = User::factory()->admin()->create(['name' => 'Carmen Vidal']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/core/dashboard')
            ->where('stats', fn ($stats) => collect($stats)->firstWhere('key', 'users')['value'] === 3)
        );
});

it('hides the user count from authors', function () {
    $this->actingAs(User::factory()->author()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats', fn ($stats) => collect($stats)->firstWhere('key', 'users') === null)
        );
});

it('shares the admin nav with inertia', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav.0', [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'href' => '/admin',
                'icon' => 'dashboard',
                'group' => 'dashboard',
                'parent' => null,
                'keywords' => ['home', 'overview', 'start', 'inicio', 'escritorio'],
                'plugin' => null,
                'isNew' => false,
            ])
        );
});

it('groups the built-in nav items into sidebar sections', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('versoNav', function ($nav) {
                $groups = collect($nav)->pluck('group', 'key');

                return $groups['dashboard'] === 'dashboard'
                    && $groups['entries.post'] === 'content'
                    && $groups['media'] === 'content'
                    && $groups['comments'] === 'content'
                    && $groups['site.editor'] === 'design'
                    && $groups['site.menus'] === 'design'
                    && $groups['themes'] === 'design'
                    && $groups['editor.code'] === 'design'
                    && $groups['users'] === 'administration'
                    && $groups['plugins'] === 'administration'
                    && $groups['importer'] === 'administration'
                    && $groups['updates'] === 'administration'
                    && $groups['system'] === 'administration'
                    && $groups['site.settings'] === 'settings'
                    && $groups['site.permalinks'] === 'settings'
                    && $groups['editor.settings'] === 'settings'
                    && $groups['fields'] === 'settings';
            })
        );
});

it('shares the site name with the admin', function () {
    app(Settings::class)->set('site.name', 'Harbor Notes');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('siteName', 'Harbor Notes'));
});
