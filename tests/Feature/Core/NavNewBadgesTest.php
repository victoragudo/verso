<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminPreferences;
use App\Verso\Core\Admin\NavNewBadges;
use App\Verso\Core\Settings\Settings;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PluginFixtures;
use Tests\Support\ShopFixtures;

// The isNew flag of each nav item the user gets on a page.
function newFlags($test, User $user, string $url): array
{
    $flags = [];

    $test->actingAs($user)->get($url)->assertInertia(function (Assert $page) use (&$flags) {
        $flags = collect($page->toArray()['props']['versoNav'])->pluck('isNew', 'key')->all();
    });

    return $flags;
}

beforeEach(function () {
    ShopFixtures::boot();
    app(NavNewBadges::class)->pluginEnabled('shop');
    $this->admin = User::factory()->admin()->create(['name' => 'Inês Duarte']);
});

it('marks the items of a newly enabled plugin as new', function () {
    $flags = newFlags($this, $this->admin, route('admin.dashboard'));

    expect($flags['shop.orders'])->toBeTrue()
        ->and($flags['shop.products'])->toBeTrue()
        ->and($flags['entries.product'])->toBeTrue()
        ->and($flags['plugin.shop'])->toBeFalse()
        ->and($flags['media'])->toBeFalse()
        ->and($flags['dashboard'])->toBeFalse();
});

it('stops showing New once the user opens the page, only for that user', function () {
    $flags = newFlags($this, $this->admin, route('admin.shop.orders.index'));

    expect($flags['shop.orders'])->toBeFalse()
        ->and($flags['shop.products'])->toBeTrue()
        ->and(AdminPreferences::of($this->admin->fresh())->hasSeen('shop.orders'))->toBeTrue();

    expect(newFlags($this, $this->admin, route('admin.dashboard'))['shop.orders'])->toBeFalse();

    $colleague = User::factory()->admin()->create(['name' => 'Tomás Ferreira']);
    expect(newFlags($this, $colleague, route('admin.dashboard'))['shop.orders'])->toBeTrue();
});

it('counts a page under the item link as opening it', function () {
    expect(newFlags($this, $this->admin, '/admin/shop/orders?status=paid')['shop.orders'])->toBeFalse();
});

it('drops the badge after the configured number of days', function () {
    config(['verso.admin.nav.new_badge_days' => 14]);
    $this->travel(15)->days();

    expect(newFlags($this, $this->admin, route('admin.dashboard'))['shop.orders'])->toBeFalse();
});

it('can be turned off', function () {
    config(['verso.admin.nav.new_badge_days' => 0]);

    expect(collect(newFlags($this, $this->admin, route('admin.dashboard')))->filter()->all())->toBe([]);
});

it('records the time when a plugin is enabled from the admin', function () {
    PluginFixtures::use();
    app(Settings::class)->set(NavNewBadges::SETTING, []);

    $this->actingAs($this->admin)->post(route('admin.plugins.enable', 'event-calendar'))->assertRedirect();

    expect(app(Settings::class)->get(NavNewBadges::SETTING))->toHaveKey('event-calendar');
});

it('finds the item of the current page by the longest matching link', function () {
    $rows = [
        ['key' => 'dashboard', 'href' => '/admin', 'group' => 'dashboard'],
        ['key' => 'plugins', 'href' => '/admin/plugins', 'group' => 'administration'],
        ['key' => 'newsletter.campaigns', 'href' => '/admin/plugins/newsletter/campaigns', 'group' => 'tools'],
    ];
    $badges = app(NavNewBadges::class);

    expect($badges->activeKey($rows, '/admin/plugins/newsletter/campaigns/12/edit'))->toBe('newsletter.campaigns')
        ->and($badges->activeKey($rows, '/admin/plugins/shop'))->toBe('plugins')
        ->and($badges->activeKey($rows, '/admin/media'))->toBeNull()
        ->and($badges->activeKey($rows, '/admin'))->toBe('dashboard');
});
