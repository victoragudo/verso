<?php

use App\Models\User;
use App\Verso\Content\ContentAdminNav;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Plugins\Admin\PluginAdminMenu;
use App\Verso\Plugins\Admin\PluginNavPlacement;
use App\Verso\Plugins\Exceptions\InvalidManifest;
use App\Verso\Plugins\Plugin;
use App\Verso\Plugins\PluginManifest;
use Tests\Support\PluginFixtures;
use Tests\Support\ShopFixtures;

// A valid manifest for a made up "Harbor Bookings" plugin, with an admin part that tests change.
function bookingsManifest(array $admin): array
{
    return [
        'name' => 'Harbor Bookings',
        'slug' => 'harbor-bookings',
        'version' => '1.2.0',
        'provider' => 'Verso\\Plugins\\HarborBookings\\HarborBookingsServiceProvider',
        'requires' => '^0.1',
        'admin' => $admin,
    ];
}

function bookingsPlugin(array $admin): Plugin
{
    $manifest = PluginManifest::fromArray(bookingsManifest($admin), '/plugins/harbor-bookings');

    return new Plugin('harbor-bookings', '/plugins/harbor-bookings', $manifest, enabled: true);
}

function manifestErrors(array $admin): array
{
    try {
        PluginManifest::fromArray(bookingsManifest($admin), '/plugins/harbor-bookings');
    } catch (InvalidManifest $exception) {
        return $exception->errors;
    }

    return [];
}

describe('the admin part of verso.json', function () {
    it('reads the group, label, icon and items', function () {
        $manifest = PluginManifest::fromArray(bookingsManifest([
            'group' => 'tools',
            'label' => 'Bookings',
            'icon' => 'calendar',
            'keywords' => ['Reservas', ' tables '],
            'items' => [
                ['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard', 'capability' => 'edit_any_entry', 'keywords' => ['reservations', 'Reservas', 'reservas']],
                ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin.site.settings.edit'],
            ],
        ]), '/plugins/harbor-bookings');

        expect($manifest->admin)->toBeInstanceOf(PluginAdminMenu::class)
            ->and($manifest->admin->group)->toBe(NavGroup::Tools)
            ->and($manifest->admin->label)->toBe('Bookings')
            ->and($manifest->admin->keywords)->toBe(['reservas', 'tables'])
            ->and($manifest->admin->items)->toHaveCount(2)
            ->and($manifest->admin->items[0]->capability)->toBe(Capability::EditAnyEntry)
            ->and($manifest->admin->items[0]->keywords)->toBe(['reservations', 'reservas'])
            ->and($manifest->admin->items[1]->capability)->toBeNull();
    });

    it('is optional', function () {
        $data = bookingsManifest([]);
        unset($data['admin']);

        expect(PluginManifest::fromArray($data, '/plugins/harbor-bookings')->admin)->toBeNull();
    });

    it('refuses groups that core does not have', function () {
        expect(manifestErrors(['group' => 'marketing', 'items' => [['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard']]]))
            ->toContain('The selected admin.group is invalid.');
    });

    it('refuses links outside the admin', function () {
        $errors = manifestErrors(['group' => 'tools', 'items' => [['key' => 'bookings', 'label' => 'Bookings', 'route' => 'theme.home']]]);

        expect(implode(' ', $errors))->toContain('admin.items.0.route');
    });

    it('refuses the same item key twice, unknown capabilities and bad icons', function () {
        $errors = implode(' ', manifestErrors(['group' => 'tools', 'icon' => 'calendar<script>', 'items' => [
            ['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard', 'capability' => 'rule_the_world'],
            ['key' => 'bookings', 'label' => 'Waiting list', 'route' => 'admin.dashboard'],
        ]]));

        expect($errors)->toContain('admin.icon')
            ->toContain('admin.items.0.capability')
            ->toContain('duplicate value');
    });

    it('needs at least one item and limits how many there are', function () {
        config(['verso.admin.nav.max_plugin_items' => 2]);

        expect(implode(' ', manifestErrors(['group' => 'tools', 'items' => []])))->toContain('admin.items')
            ->and(implode(' ', manifestErrors(['group' => 'tools', 'items' => array_map(
                fn (int $i): array => ['key' => 'page-'.$i, 'label' => 'Page '.$i, 'route' => 'admin.dashboard'],
                range(1, 3),
            )])))->toContain('admin.items');
    });

    it('limits the search words of an item', function () {
        config(['verso.admin.nav.max_keywords' => 2]);

        expect(implode(' ', manifestErrors(['group' => 'tools', 'items' => [
            ['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard', 'keywords' => ['reservations', 'tables', 'guests']],
        ]])))->toContain('admin.items.0.keywords');
    });

    it('is valid in every bundled plugin', function (string $slug, string $group) {
        $manifest = PluginManifest::fromFile(base_path("plugins/{$slug}/verso.json"));

        expect($manifest->admin?->group->value)->toBe($group);
    })->with([
        ['shop', 'tools'],
        ['forms', 'tools'],
        ['newsletter', 'tools'],
        ['memberships', 'tools'],
        ['analytics', 'dashboard'],
        ['ai-assistant', 'settings'],
    ]);
});

describe('placement in the sidebar', function () {
    it('puts a Tools plugin under one parent item with its links inside', function () {
        app(PluginNavPlacement::class)->register(bookingsPlugin([
            'group' => 'tools',
            'label' => 'Bookings',
            'icon' => 'calendar',
            'items' => [
                ['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard', 'keywords' => ['reservations']],
                ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin.site.settings.edit', 'capability' => 'manage_settings'],
            ],
        ]));

        $nav = collect(AdminNav::forUser(User::factory()->admin()->create()))->keyBy('key');

        expect($nav['plugin.harbor-bookings'])->toMatchArray(['label' => 'Bookings', 'group' => 'tools', 'parent' => null, 'href' => '/admin', 'plugin' => 'harbor-bookings'])
            ->and($nav['harbor-bookings.bookings'])->toMatchArray(['parent' => 'plugin.harbor-bookings', 'group' => 'tools', 'keywords' => ['reservations']])
            ->and($nav['harbor-bookings.settings'])->toMatchArray(['parent' => 'plugin.harbor-bookings', 'href' => '/admin/site/settings'])
            ->and(AdminNav::path('harbor-bookings.settings'))->toBe(['Tools', 'Bookings', 'Settings']);
    });

    it('checks the capability of each link, so an editor only sees what they may open', function () {
        app(PluginNavPlacement::class)->register(bookingsPlugin([
            'group' => 'tools',
            'items' => [
                ['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard', 'capability' => 'edit_any_entry'],
                ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin.site.settings.edit', 'capability' => 'manage_settings'],
            ],
        ]));

        $keys = collect(AdminNav::forUser(User::factory()->editor()->create()))->pluck('key');
        $authorKeys = collect(AdminNav::forUser(User::factory()->author()->create()))->pluck('key');

        expect($keys)->toContain('plugin.harbor-bookings')->toContain('harbor-bookings.bookings')->not->toContain('harbor-bookings.settings')
            ->and($authorKeys)->not->toContain('plugin.harbor-bookings')->not->toContain('harbor-bookings.bookings');
    });

    it('puts dashboard pages inside the dashboard', function () {
        app(PluginNavPlacement::class)->register(bookingsPlugin([
            'group' => 'dashboard',
            'items' => [['key' => 'overview', 'label' => 'Bookings today', 'route' => 'admin.dashboard']],
        ]));

        $item = AdminNav::get('harbor-bookings.overview');

        expect($item->parent)->toBe('dashboard')
            ->and($item->group)->toBe(NavGroup::Dashboard)
            ->and(AdminNav::has('plugin.harbor-bookings'))->toBeFalse()
            ->and(AdminNav::path('harbor-bookings.overview'))->toBe(['Dashboard', 'Bookings today']);
    });

    it('keeps a single link outside Tools as a plain item of its group', function () {
        app(PluginNavPlacement::class)->register(bookingsPlugin([
            'group' => 'settings',
            'keywords' => ['tables'],
            'items' => [['key' => 'settings', 'label' => 'Bookings', 'route' => 'admin.site.settings.edit', 'keywords' => ['reservations']]],
        ]));

        $item = AdminNav::get('harbor-bookings.settings');

        expect($item->parent)->toBeNull()
            ->and($item->group)->toBe(NavGroup::Settings)
            ->and($item->order)->toBe(PluginNavPlacement::PLUGIN_ORDER)
            ->and($item->keywords)->toBe(['reservations', 'tables']);
    });

    it('moves items a plugin adds in PHP to its declared place', function () {
        $plugin = bookingsPlugin(['group' => 'tools', 'items' => [['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard']]]);
        $placement = app(PluginNavPlacement::class);
        $placement->register($plugin);

        $placed = $placement->place($plugin, new NavItem(key: 'bookings.calendar', label: 'Calendar', route: 'admin.dashboard', group: NavGroup::Settings));

        expect($placed->group)->toBe(NavGroup::Tools)
            ->and($placed->parent)->toBe('plugin.harbor-bookings')
            ->and($placed->plugin)->toBe('harbor-bookings');
    });

    it('puts PHP items of a plugin without an admin part under Tools', function () {
        $manager = PluginFixtures::use();
        $manager->enable('event-calendar');
        $manager->register($manager->get('event-calendar'));

        expect(AdminNav::get('events.calendar')->group)->toBe(NavGroup::Tools)
            ->and(AdminNav::get('events.calendar')->parent)->toBe('plugin.event-calendar')
            ->and(AdminNav::get('plugin.event-calendar')->label)->toBe('Event Calendar');
    });

    it('tells where a plugin is, like Tools > Shop', function () {
        $placement = app(PluginNavPlacement::class);

        expect($placement->location(bookingsPlugin(['group' => 'tools', 'label' => 'Bookings', 'items' => [['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.dashboard']]])))
            ->toBe(['path' => ['Tools', 'Bookings'], 'key' => 'plugin.harbor-bookings'])
            ->and($placement->location(bookingsPlugin(['group' => 'dashboard', 'items' => [['key' => 'overview', 'label' => 'Bookings today', 'route' => 'admin.dashboard']]])))
            ->toBe(['path' => ['Dashboard', 'Bookings today'], 'key' => 'harbor-bookings.overview'])
            ->and($placement->location(bookingsPlugin(['group' => 'settings', 'items' => [['key' => 'settings', 'label' => 'Bookings', 'route' => 'admin.dashboard']]])))
            ->toBe(['path' => ['Settings', 'Bookings'], 'key' => 'harbor-bookings.settings']);
    });

    it('never shows a link to a page that does not exist', function () {
        app(PluginNavPlacement::class)->register(bookingsPlugin([
            'group' => 'tools',
            'items' => [['key' => 'bookings', 'label' => 'Bookings', 'route' => 'admin.harbor-bookings.index']],
        ]));

        expect(collect(AdminNav::forUser(User::factory()->admin()->create()))->pluck('key'))
            ->not->toContain('harbor-bookings.bookings')
            ->not->toContain('plugin.harbor-bookings');
    });
});

describe('the bundled Shop plugin', function () {
    beforeEach(function () {
        ShopFixtures::boot();
    });

    it('puts products in Content after Media and Comments, with product categories inside', function () {
        $nav = collect(AdminNav::forUser(User::factory()->admin()->create()));
        $content = $nav->where('group', 'content')->whereNull('parent')->pluck('key')->values()->all();

        expect($content)->toBe(['entries.post', 'entries.page', 'media', 'comments', 'entries.product'])
            ->and($nav->firstWhere('key', 'terms.product_category')['parent'])->toBe('entries.product')
            ->and(AdminNav::get('entries.product')->order)->toBeGreaterThan(ContentAdminNav::PLUGIN_TYPE_ORDER)
            ->and(AdminNav::get('entries.product')->plugin)->toBe('shop');
    });

    it('puts Orders, Products and Settings under Tools > Shop', function () {
        $nav = collect(AdminNav::forUser(User::factory()->admin()->create()));

        expect($nav->where('parent', 'plugin.shop')->pluck('label')->values()->all())->toBe(['Orders', 'Products', 'Settings'])
            ->and($nav->firstWhere('key', 'plugin.shop'))->toMatchArray(['group' => 'tools', 'href' => '/admin/shop/orders'])
            ->and($nav->firstWhere('key', 'shop.orders')['keywords'])->toContain('pedidos')->toContain('sales');
    });
});
