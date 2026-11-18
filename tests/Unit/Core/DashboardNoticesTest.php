<?php

use App\Models\User;
use App\Verso\Core\Admin\Dashboard;
use App\Verso\Core\Admin\DashboardNotice;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;

it('returns visible notices with something to say, sorted by order', function () {
    $dashboard = new Dashboard;
    $dashboard->addNotice(new DashboardNotice(
        key: 'backup',
        resolve: fn (User $user) => ['title' => 'Last backup is 9 days old', 'description' => null, 'href' => null, 'action' => null, 'tone' => 'warning'],
        order: 20,
    ));
    $dashboard->addNotice(new DashboardNotice(
        key: 'update',
        resolve: fn (User $user) => ['title' => 'Verso 1.2.0 is available', 'description' => 'You run 1.1.4.', 'href' => '/admin/updates', 'action' => 'See the update', 'tone' => 'info'],
        order: 10,
        icon: 'updates',
    ));
    $dashboard->addNotice(new DashboardNotice(key: 'quiet', resolve: fn (User $user) => null));

    expect($dashboard->noticesFor(User::factory()->make(['role' => Role::Editor])))->toBe([
        ['key' => 'update', 'icon' => 'updates', 'title' => 'Verso 1.2.0 is available', 'description' => 'You run 1.1.4.', 'href' => '/admin/updates', 'action' => 'See the update', 'tone' => 'info'],
        ['key' => 'backup', 'icon' => null, 'title' => 'Last backup is 9 days old', 'description' => null, 'href' => null, 'action' => null, 'tone' => 'warning'],
    ]);
});

it('hides notices from users without the capability and does not resolve them', function () {
    $resolved = false;
    $dashboard = new Dashboard;
    $dashboard->addNotice(new DashboardNotice(
        key: 'update',
        resolve: function (User $user) use (&$resolved) {
            $resolved = true;

            return ['title' => 'Verso 1.2.0 is available', 'description' => null, 'href' => null, 'action' => null, 'tone' => 'info'];
        },
        capability: Capability::ManageUpdates,
    ));

    expect($dashboard->noticesFor(User::factory()->make(['role' => Role::Author])))->toBe([])
        ->and($resolved)->toBeFalse()
        ->and($dashboard->noticesFor(User::factory()->make(['role' => Role::Admin])))->toHaveCount(1);
});

it('replaces a notice added again with the same key', function () {
    $dashboard = new Dashboard;
    $dashboard->addNotice(new DashboardNotice(key: 'update', resolve: fn () => ['title' => 'Old text', 'description' => null, 'href' => null, 'action' => null, 'tone' => 'info']));
    $dashboard->addNotice(new DashboardNotice(key: 'update', resolve: fn () => ['title' => 'New text', 'description' => null, 'href' => null, 'action' => null, 'tone' => 'info']));

    expect($dashboard->noticesFor(User::factory()->make(['role' => Role::Admin])))->toHaveCount(1)
        ->and($dashboard->noticesFor(User::factory()->make(['role' => Role::Admin]))[0]['title'])->toBe('New text');
});

it('only gives the manage updates capability to admins', function () {
    expect(Role::Admin->capabilities())->toContain(Capability::ManageUpdates)
        ->and(Role::Editor->capabilities())->not->toContain(Capability::ManageUpdates);
});
