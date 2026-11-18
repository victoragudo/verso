<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AnalyticsPlugin;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;

beforeEach(function () {
    AnalyticsPlugin::boot();
    $this->travelTo(CarbonImmutable::parse('2026-11-17 11:00:00', 'UTC'));
    $this->admin = User::factory()->admin()->create(['name' => 'Helena Costa']);
});

it('shows the analytics page with the default range', function () {
    AnalyticsPlugin::event(['day' => '2026-11-17', 'path' => '/menu', 'referrer_host' => 'www.instagram.com', 'device' => 'mobile']);

    $this->actingAs($this->admin)
        ->get(route('admin.analytics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/plugins/analytics/index')
            ->where('range', 30)
            ->has('ranges', 3)
            ->where('ranges.0', ['days' => 7, 'href' => '/admin/analytics?range=7'])
            ->has('report.series', 30)
            ->where('report.totals', ['visitors' => 1, 'views' => 1])
            ->where('report.pages.0.value', '/menu')
            ->where('report.referrers.0.value', 'www.instagram.com')
            ->has('report.devices', 3)
            ->where('settings', ['retention_days' => 30, 'ignore_roles' => ['admin']])
            ->where('roleOptions.0', ['value' => 'admin', 'label' => 'Administrator'])
            ->where('retentionLimits', ['min' => 1, 'max' => 365])
            ->where('settingsUrl', '/admin/analytics/settings')
        );
});

it('switches between the 7, 30 and 90 day ranges', function (int $days) {
    $this->actingAs($this->admin)
        ->get(route('admin.analytics.index', ['range' => $days]))
        ->assertInertia(fn (Assert $page) => $page->where('range', $days)->has('report.series', $days));
})->with([7, 30, 90]);

it('falls back to the default range for unknown values', function (string $range) {
    $this->actingAs($this->admin)
        ->get('/admin/analytics?range='.$range)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('range', 30));
})->with(['365', 'week', '-7']);

it('uses the first range when the default is not offered', function () {
    config(['analytics.default_range' => 14]);

    $this->actingAs($this->admin)
        ->get(route('admin.analytics.index'))
        ->assertInertia(fn (Assert $page) => $page->where('range', 7));
});

it('is hidden from people without the capability', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.analytics.index'))
        ->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));
});

it('saves the settings', function () {
    $this->actingAs($this->admin)
        ->from(route('admin.analytics.index'))
        ->put(route('admin.analytics.settings.update'), ['retention_days' => 60, 'ignore_roles' => ['editor', 'author']])
        ->assertRedirect(route('admin.analytics.index'));

    $settings = app(AnalyticsSettings::class);

    expect($settings->retentionDays())->toBe(60)
        ->and($settings->ignoredRoles())->toBe(['editor', 'author']);
});

it('saves an empty role list so every signed-in visit counts', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.analytics.settings.update'), ['retention_days' => 30, 'ignore_roles' => []])
        ->assertSessionHasNoErrors();

    expect(app(AnalyticsSettings::class)->ignoredRoles())->toBe([]);
});

it('checks the settings', function (array $data, string $field) {
    $this->actingAs($this->admin)
        ->put(route('admin.analytics.settings.update'), $data)
        ->assertSessionHasErrors($field);
})->with([
    'zero days' => [['retention_days' => 0, 'ignore_roles' => ['admin']], 'retention_days'],
    'too many days' => [['retention_days' => 400, 'ignore_roles' => ['admin']], 'retention_days'],
    'text days' => [['retention_days' => 'a month', 'ignore_roles' => ['admin']], 'retention_days'],
    'missing roles' => [['retention_days' => 30], 'ignore_roles'],
    'unknown role' => [['retention_days' => 30, 'ignore_roles' => ['sommelier']], 'ignore_roles.0'],
    'repeated role' => [['retention_days' => 30, 'ignore_roles' => ['admin', 'admin']], 'ignore_roles.0'],
]);

it('does not let editors change the settings', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->put(route('admin.analytics.settings.update'), ['retention_days' => 5, 'ignore_roles' => []])
        ->assertForbidden();

    expect(app(AnalyticsSettings::class)->retentionDays())->toBe(30);
});

it('puts Analytics inside the dashboard instead of a separate sidebar item', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('versoNav', fn ($nav) => collect($nav)->contains(
            fn ($item) => $item['key'] === 'analytics.overview' && $item['icon'] === 'analytics' && $item['group'] === 'dashboard' && $item['parent'] === 'dashboard',
        )));
});

it('shows a visitors today card on the dashboard', function () {
    AnalyticsPlugin::event(['day' => '2026-11-17']);
    AnalyticsPlugin::event(['day' => '2026-11-17', 'visitor' => hash('sha256', 'tiago')]);
    AnalyticsPlugin::event(['day' => '2026-11-16', 'visitor' => hash('sha256', 'last-night')]);

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats', fn ($stats) => collect($stats)->contains(fn ($stat) => $stat['key'] === 'analytics-visitors-today'
            && $stat['label'] === 'Visitors today'
            && $stat['value'] === 2
            && $stat['icon'] === 'analytics'
            && $stat['href'] === '/admin/analytics')));
});

it('hides the dashboard card from editors', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats', fn ($stats) => ! collect($stats)->contains('key', 'analytics-visitors-today')));
});
