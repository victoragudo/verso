<?php

use App\Models\User;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardStat;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;

it('returns visible stats sorted by order', function () {
    $dashboard = new Dashboard;
    $dashboard->addStat(new DashboardStat(key: 'drafts', label: 'Drafts', count: fn () => 4, order: 20));
    $dashboard->addStat(new DashboardStat(key: 'posts', label: 'Published posts', count: fn () => 12, order: 10, route: 'admin.dashboard', parameters: ['tab' => 'posts'], icon: 'posts'));
    $dashboard->addStat(new DashboardStat(key: 'users', label: 'Users', count: fn () => 3, capability: Capability::ManageUsers));

    $stats = $dashboard->statsFor(User::factory()->make(['role' => Role::Author]));

    expect($stats)->toBe([
        ['key' => 'posts', 'label' => 'Published posts', 'value' => 12, 'href' => '/admin?tab=posts', 'icon' => 'posts'],
        ['key' => 'drafts', 'label' => 'Drafts', 'value' => 4, 'href' => null, 'icon' => null],
    ]);
});

it('only counts stats the user can see', function () {
    $counted = false;
    $dashboard = new Dashboard;
    $dashboard->addStat(new DashboardStat(key: 'users', label: 'Users', count: function () use (&$counted) {
        $counted = true;

        return 1;
    }, capability: Capability::ManageUsers));

    $dashboard->statsFor(User::factory()->make(['role' => Role::Contributor]));

    expect($counted)->toBeFalse();
});
