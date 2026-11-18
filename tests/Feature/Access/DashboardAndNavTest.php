<?php

use App\Models\User;
use App\Verso\Plugins\Slots\AdminSlot;
use App\Verso\Plugins\Slots\AdminSlotRegistry;
use App\Verso\Plugins\Slots\SlotItem;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;

require_once __DIR__.'/Helpers.php';

// Keys of the sidebar items and dashboard stats one user gets.
function dashboardFor(User $user): array
{
    $props = [];

    test()->actingAs($user)->get(route('admin.dashboard'))->assertOk()->assertInertia(function (Assert $page) use (&$props) {
        $props = $page->toArray()['props'];
    });

    return [
        'nav' => array_column($props['versoNav'], 'key'),
        'stats' => array_column($props['stats'], 'key'),
        'panels' => array_column($props['panels'], 'key'),
        'can' => array_keys(array_filter($props['can'])),
    ];
}

it('shows nav items and stats for each capability', function (array $capabilities, array $nav, array $stats) {
    $dashboard = dashboardFor(userWith($capabilities));

    expect(array_values(array_intersect($nav, $dashboard['nav'])))->toBe($nav)
        ->and(array_values(array_intersect($stats, $dashboard['stats'])))->toBe($stats)
        ->and($dashboard['can'])->toContain(...$capabilities);
})->with([
    'list users' => [['list_users'], ['users'], ['users']],
    'create users' => [['create_users'], [], ['invitations']],
    'list and create users' => [['list_users', 'create_users'], ['users', 'users.invitations'], ['users', 'invitations']],
    'manage roles' => [['manage_roles'], ['users.roles'], []],
    'moderate comments' => [['moderate_comments'], ['comments'], ['comments.pending']],
    'upload files' => [['upload_files'], ['media'], ['media']],
    'edit site' => [['edit_site'], ['site.editor'], []],
    'manage menus' => [['manage_menus'], ['site.menus'], []],
    'general settings' => [['manage_general_settings'], ['site.settings'], []],
    'permalinks' => [['manage_permalinks'], ['site.permalinks', 'site.redirects'], []],
    'editor settings' => [['manage_editor_settings'], ['editor.settings'], []],
    'fields' => [['manage_fields'], ['fields'], []],
    'plugins' => [['activate_plugins'], ['plugins'], []],
    'import' => [['import_content'], ['importer'], []],
    'updates' => [['manage_updates'], ['updates'], []],
    'system' => [['manage_system'], ['system', 'system-settings', 'server-health'], []],
    'only pages' => [['entries.page.create'], ['entries.page'], ['published.page', 'drafts']],
]);

it('hides nav items, stats and panels without the capability', function () {
    $dashboard = dashboardFor(userWith(['entries.page.edit_own']));

    expect($dashboard['nav'])->toContain('dashboard', 'entries.page')
        ->not->toContain('entries.post', 'users', 'comments', 'media', 'site.settings', 'plugins', 'system.settings', 'users.roles')
        ->and($dashboard['stats'])->toContain('published.page')->not->toContain('published.post', 'users', 'comments.pending', 'media')
        ->and($dashboard['panels'])->toContain('recent-entries')->not->toContain('pending-comments');
});

it('gives a subscriber an empty dashboard', function () {
    $dashboard = dashboardFor(User::factory()->create());

    expect($dashboard['nav'])->toBe(['dashboard'])
        ->and($dashboard['stats'])->toBe([])
        ->and($dashboard['panels'])->toBe([])
        ->and($dashboard['can'])->toBe([]);
});

it('filters dashboard widgets from plugins by plugin capabilities', function () {
    PluginFixtures::boot('analytics');
    app(AdminSlotRegistry::class)->add(new SlotItem(AdminSlot::DashboardWidgets, 'visitors-map', 'analytics.map', capability: 'analytics.view'));

    $viewer = userWith(['analytics.view']);
    $editor = User::factory()->editor()->create();

    $this->actingAs($viewer)->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('versoSlots.dashboard-widgets.0.key', 'visitors-map')
        ->where('versoNav', fn ($items) => collect($items)->contains('key', 'analytics.overview'))
        ->where('stats', fn ($stats) => collect($stats)->contains('key', 'analytics-visitors-today')));

    $this->actingAs($editor)->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
        ->missing('versoSlots.dashboard-widgets')
        ->where('versoNav', fn ($items) => ! collect($items)->contains('key', 'analytics.overview')));
});

it('splits the forms plugin into building forms and reading messages', function () {
    PluginFixtures::boot('forms');
    $reader = userWith(['forms.messages']);
    $builder = userWith(['forms.manage']);

    $this->actingAs($reader)->get(route('admin.forms.submissions.index'))->assertOk();
    $this->actingAs($reader)->get(route('admin.forms.index'))->assertForbidden();
    $this->actingAs($builder)->get(route('admin.forms.index'))->assertOk();
    $this->actingAs($builder)->get(route('admin.forms.submissions.index'))->assertForbidden();
    $this->actingAs(User::factory()->editor()->create())->get(route('admin.forms.submissions.index'))->assertOk();
});
