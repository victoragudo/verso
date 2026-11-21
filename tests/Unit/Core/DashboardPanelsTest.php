<?php

use App\Models\User;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardPanel;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;

it('returns visible panels sorted by order with their items', function () {
    $dashboard = new Dashboard;
    $dashboard->addPanel(new DashboardPanel(key: 'comments', label: 'Pending comments', items: fn (User $user) => [], order: 20, capability: Capability::ModerateComments));
    $dashboard->addPanel(new DashboardPanel(
        key: 'recent',
        label: 'Recent entries',
        items: fn (User $user) => [
            ['key' => 'entry-4', 'title' => 'Spring planting notes', 'meta' => 'Post', 'href' => '/admin', 'status' => 'draft'],
        ],
        icon: 'entries',
        emptyText: 'No entries yet.',
        order: 10,
        route: 'admin.dashboard',
    ));

    $panels = $dashboard->panelsFor(User::factory()->make(['role' => Role::Author]));

    expect($panels)->toBe([[
        'key' => 'recent',
        'label' => 'Recent entries',
        'icon' => 'entries',
        'emptyText' => 'No entries yet.',
        'href' => '/admin',
        'items' => [
            ['key' => 'entry-4', 'title' => 'Spring planting notes', 'meta' => 'Post', 'href' => '/admin', 'status' => 'draft'],
        ],
    ]]);
});

it('does not load items for hidden panels', function () {
    $loaded = false;
    $dashboard = new Dashboard;
    $dashboard->addPanel(new DashboardPanel(key: 'comments', label: 'Pending comments', items: function (User $user) use (&$loaded) {
        $loaded = true;

        return [];
    }, capability: Capability::ModerateComments));

    expect($dashboard->panelsFor(User::factory()->make(['role' => Role::Author])))->toBe([])
        ->and($loaded)->toBeFalse();
});

it('replaces a panel with the same key', function () {
    $dashboard = new Dashboard;
    $dashboard->addPanel(new DashboardPanel(key: 'recent', label: 'Recent entries', items: fn (User $user) => []));
    $dashboard->addPanel(new DashboardPanel(key: 'recent', label: 'Latest writing', items: fn (User $user) => []));

    $panels = $dashboard->panelsFor(User::factory()->make(['role' => Role::Admin]));

    expect($panels)->toHaveCount(1)
        ->and($panels[0]['label'])->toBe('Latest writing');
});

it('shows a translated empty text when a panel sets none', function () {
    app()->setLocale('es');
    $dashboard = new Dashboard;
    $dashboard->addPanel(new DashboardPanel(key: 'drafts', label: 'Recent entries', items: fn (User $user) => []));

    expect($dashboard->panelsFor(User::factory()->make(['role' => Role::Admin]))[0]['emptyText'])->toBe('Aún no hay nada aquí.');
});
