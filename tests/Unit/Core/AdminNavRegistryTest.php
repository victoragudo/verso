<?php

use App\Models\User;
use App\Verso\Core\Admin\AdminNav;
use App\Verso\Core\Admin\AdminNavRegistry;
use App\Verso\Core\Admin\NavGroup;
use App\Verso\Core\Admin\NavItem;
use App\Verso\Core\Enums\Capability;
use App\Verso\Core\Enums\Role;

beforeEach(function () {
    $this->registry = new AdminNavRegistry;
});

it('sorts items by order and then by label', function () {
    $this->registry->add(new NavItem(key: 'media', label: 'Media', route: 'admin.dashboard', order: 30));
    $this->registry->add(new NavItem(key: 'pages', label: 'Pages', route: 'admin.dashboard', order: 20));
    $this->registry->add(new NavItem(key: 'comments', label: 'Comments', route: 'admin.dashboard', order: 30));

    expect(array_map(fn (NavItem $item) => $item->key, $this->registry->all()))
        ->toBe(['pages', 'comments', 'media']);
});

it('replaces an item with the same key', function () {
    $this->registry->add(new NavItem(key: 'media', label: 'Media', route: 'admin.dashboard'));
    $this->registry->add(new NavItem(key: 'media', label: 'Media library', route: 'admin.dashboard'));

    expect($this->registry->all())->toHaveCount(1)
        ->and($this->registry->all()[0]->label)->toBe('Media library');
});

it('removes items', function () {
    $this->registry->add(new NavItem(key: 'media', label: 'Media', route: 'admin.dashboard'));
    $this->registry->remove('media');

    expect($this->registry->has('media'))->toBeFalse();
});

it('hides items the user has no capability for', function () {
    $this->registry->add(new NavItem(key: 'dashboard', label: 'Dashboard', route: 'admin.dashboard', icon: 'dashboard', order: 0));
    $this->registry->add(new NavItem(key: 'settings', label: 'Settings', route: 'admin.dashboard', icon: 'settings', capability: Capability::ManageSettings));

    $author = User::factory()->make(['role' => Role::Author]);
    $admin = User::factory()->make(['role' => Role::Admin]);

    expect($this->registry->forUser($author))->toBe([
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin', 'icon' => 'dashboard', 'group' => 'content', 'parent' => null, 'keywords' => [], 'plugin' => null],
    ])->and($this->registry->forUser($admin))->toHaveCount(2);
});

it('shows nothing to guests', function () {
    $this->registry->add(new NavItem(key: 'dashboard', label: 'Dashboard', route: 'admin.dashboard'));

    expect($this->registry->forUser(null))->toBe([]);
});

it('builds links with route parameters', function () {
    $item = new NavItem(key: 'settings', label: 'Settings', route: 'profile.edit', parameters: ['tab' => 'general']);

    expect($item->toArray()['href'])->toBe('/settings/profile?tab=general');
});

it('is a singleton behind the facade', function () {
    AdminNav::add(new NavItem(key: 'newsletter', label: 'Newsletter', route: 'admin.dashboard'));

    expect(app(AdminNavRegistry::class)->has('newsletter'))->toBeTrue()
        ->and(AdminNav::has('dashboard'))->toBeTrue();
});

it('puts items in the content group by default', function () {
    $item = new NavItem(key: 'newsletter', label: 'Newsletter', route: 'admin.dashboard');

    expect($item->group)->toBe(NavGroup::Content)
        ->and($item->toArray()['group'])->toBe('content');
});

it('sends the chosen group to the admin UI', function () {
    $item = new NavItem(key: 'redirects', label: 'Redirects', route: 'admin.dashboard', icon: 'redirects', group: NavGroup::Settings, parent: 'site.permalinks', keywords: ['301']);

    expect($item->toArray())->toBe([
        'key' => 'redirects',
        'label' => 'Redirects',
        'href' => '/admin',
        'icon' => 'redirects',
        'group' => 'settings',
        'parent' => 'site.permalinks',
        'keywords' => ['301'],
        'plugin' => null,
    ]);
});

it('sends translated labels and keeps the English words for the search', function () {
    app('translator')->addLines(['*.shop::Orders' => 'Pedidos', '*.shop::Shop' => 'Tienda', '*.Tools' => 'Herramientas', '*.Redirects' => 'Redirecciones', '*.forwarding' => 'reenvío'], 'es');
    app()->setLocale('es');

    $this->registry->add(new NavItem(key: 'plugin.shop', label: 'Shop', route: '', group: NavGroup::Tools, plugin: 'shop'));
    $this->registry->add(new NavItem(key: 'shop.orders', label: 'Orders', route: 'admin.dashboard', group: NavGroup::Tools, parent: 'plugin.shop', plugin: 'shop'));
    $redirects = new NavItem(key: 'redirects', label: 'Redirects', route: 'admin.dashboard', keywords: ['forwarding', '301']);

    expect($this->registry->get('shop.orders')->toArray())->toMatchArray(['label' => 'Pedidos', 'keywords' => ['Orders']])
        ->and($redirects->toArray())->toMatchArray(['label' => 'Redirecciones', 'keywords' => ['reenvío', '301', 'Redirects', 'forwarding']])
        ->and($this->registry->path('shop.orders'))->toBe(['Herramientas', 'Tienda', 'Pedidos']);
});

it('keeps the keywords as they are in English', function () {
    $item = new NavItem(key: 'shop.orders', label: 'Orders', route: 'admin.dashboard', keywords: ['sales', 'pedidos'], plugin: 'shop');

    expect($item->toArray()['keywords'])->toBe(['sales', 'pedidos']);
});
