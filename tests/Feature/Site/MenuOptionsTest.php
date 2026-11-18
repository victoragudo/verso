<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Site\Enums\MenuItemVisibility;
use App\Verso\Site\Menus\MenuCache;
use App\Verso\Site\Menus\MenuLocations;
use App\Verso\Site\Menus\MenuRenderer;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->menu = Menu::factory()->at('primary')->create(['name' => 'Main navigation']);
});

function saveMenuTree(array $items): TestResponse
{
    return test()->actingAs(test()->admin)->put(route('admin.site.menus.items', test()->menu), ['items' => $items]);
}

it('saves the mega flag, rel words and visibility of items', function () {
    saveMenuTree([
        ['type' => 'custom', 'label' => 'Shop', 'url' => '/shop', 'mega' => true, 'children' => [
            ['type' => 'custom', 'label' => 'Gift cards', 'url' => '/shop/gift-cards'],
        ]],
        ['type' => 'custom', 'label' => 'Partner bakery', 'url' => 'https://partner.example.se', 'rel' => ['sponsored', 'nofollow'], 'open_in_new_tab' => true],
        ['type' => 'custom', 'label' => 'My orders', 'url' => '/account/orders', 'visibility' => 'members'],
        ['type' => 'custom', 'label' => 'Sign up', 'url' => '/register', 'visibility' => 'guests'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $items = MenuItem::query()->orderBy('id')->get();

    expect($items[0]->mega)->toBeTrue()
        ->and($items[2]->rel)->toBe('nofollow sponsored')
        ->and($items[3]->visibility)->toBe(MenuItemVisibility::Members)
        ->and($items[4]->visibility)->toBe(MenuItemVisibility::Guests)
        ->and($items[1]->visibility)->toBe(MenuItemVisibility::Everyone);
});

it('refuses unknown rel words and visibility values', function () {
    saveMenuTree([['type' => 'custom', 'label' => 'Odd', 'url' => '/odd', 'rel' => ['opener']]])->assertSessionHasErrors('items.0.rel.0');
    saveMenuTree([['type' => 'custom', 'label' => 'Odd', 'url' => '/odd', 'visibility' => 'admins']])->assertSessionHasErrors('items.0.visibility');
});

it('sends the item options and choices to the menu builder', function () {
    MenuItem::factory()->create(['menu_id' => $this->menu->id, 'label' => 'Partner', 'url' => 'https://partner.example.se', 'rel' => 'nofollow sponsored', 'mega' => true, 'visibility' => 'guests']);

    $this->actingAs($this->admin)
        ->get(route('admin.site.menus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('menu.items.0.mega', true)
            ->where('menu.items.0.rel', ['nofollow', 'sponsored'])
            ->where('menu.items.0.visibility', 'guests')
            ->where('relWords', ['nofollow', 'sponsored', 'ugc', 'noreferrer'])
            ->where('visibilities.1', ['value' => 'members', 'label' => 'Signed in members only'])
        );
});

it('shows members only and guests only links to the right visitors', function () {
    MenuItem::factory()->create(['menu_id' => $this->menu->id, 'label' => 'Recipes', 'url' => '/recipes']);
    MenuItem::factory()->create(['menu_id' => $this->menu->id, 'label' => 'My orders', 'url' => '/orders', 'visibility' => 'members', 'position' => 1]);
    MenuItem::factory()->create(['menu_id' => $this->menu->id, 'label' => 'Sign up', 'url' => '/register', 'visibility' => 'guests', 'position' => 2]);

    expect(array_column(app(MenuRenderer::class)->links('primary'), 'label'))->toBe(['Recipes', 'Sign up']);

    $this->actingAs(User::factory()->create());

    expect(array_column(app(MenuRenderer::class)->links('primary'), 'label'))->toBe(['Recipes', 'My orders']);
});

it('prints rel words and the mega class in menus', function () {
    $shop = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'label' => 'Shop', 'url' => '/shop', 'mega' => true]);
    MenuItem::factory()->under($shop)->create(['label' => 'Gift cards', 'url' => '/gift-cards']);
    MenuItem::factory()->create(['menu_id' => $this->menu->id, 'label' => 'Partner', 'url' => 'https://partner.example.se', 'rel' => 'sponsored', 'open_in_new_tab' => true, 'position' => 1]);

    $html = Blade::render('<x-verso-menu location="primary" />');

    expect($html)->toContain('verso-menu__item--mega')
        ->toContain('target="_blank" rel="sponsored noopener">Partner</a>');

    $block = app(BlockRenderer::class)->render([['id' => 'n', 'type' => 'navigation', 'attrs' => ['location' => 'primary']]]);

    expect($block)->toContain('<li class="has-children is-mega">')->toContain('rel="sponsored noopener"');
});

it('adds the menu locations of the active theme', function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    app()->forgetInstance(MenuLocations::class);

    expect(app(MenuLocations::class)->all())->toBe([
        'primary' => 'Primary menu',
        'footer' => 'Footer menu',
        'social' => 'Social links',
        'legal' => 'Legal links',
    ]);
});

it('keeps plugin locations next to theme locations', function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    app()->forgetInstance(MenuLocations::class);
    app(MenuLocations::class)->register('shop', 'Shop menu');

    expect(app(MenuLocations::class)->has('shop'))->toBeTrue()
        ->and(app(MenuLocations::class)->has('legal'))->toBeTrue();
});

it('puts the format version in the menu cache key', function () {
    expect(app(MenuCache::class)->key('primary', url('/')))
        ->toStartWith(config('verso.menus.cache_prefix').'.f'.MenuCache::FORMAT.'.v');
});

it('ignores a link tree cached by an older version', function () {
    config(['verso.menus.cache_ttl' => 3600]);
    app()->forgetInstance(MenuCache::class);
    app()->forgetInstance(MenuRenderer::class);
    MenuItem::factory()->create(['menu_id' => $this->menu->id, 'label' => 'Fresh link', 'url' => '/fresh']);

    $oldKey = config('verso.menus.cache_prefix').'.v1.primary.'.sha1(url('/'));
    cache()->put($oldKey, ['has_items' => true, 'links' => [['label' => 'Stale link', 'url' => url('/stale'), 'new_tab' => false, 'active' => false, 'children' => []]]], 3600);

    expect(array_column(app(MenuRenderer::class)->links('primary'), 'label'))->toBe(['Fresh link']);
});

it('renders links in the old cached shape without the new keys', function () {
    config(['verso.menus.cache_ttl' => 3600]);
    app()->forgetInstance(MenuCache::class);
    app()->forgetInstance(MenuRenderer::class);
    $key = app(MenuCache::class)->key('primary', url('/'));
    $oldShape = ['label' => 'Recipes', 'url' => url('/recipes'), 'new_tab' => true, 'active' => false, 'children' => [
        ['label' => 'Rye bread', 'url' => url('/recipes/rye'), 'new_tab' => false, 'active' => false, 'children' => []],
    ]];
    cache()->put($key, ['has_items' => true, 'links' => [$oldShape]], 3600);

    $links = app(MenuRenderer::class)->links('primary');

    expect(array_column($links, 'label'))->toBe(['Recipes'])
        ->and(Blade::render('<x-verso-menu location="primary" />'))->toContain('target="_blank" rel="noopener">Recipes</a>')
        ->and(app(BlockRenderer::class)->render([['id' => 'n', 'type' => 'navigation', 'attrs' => ['location' => 'primary']]]))->toContain('>Rye bread</a>');
});

it('renders the Ashgrove home page with an old cached menu tree', function () {
    $this->seed(DatabaseSeeder::class);
    config(['verso.menus.cache_ttl' => 3600]);
    app()->forgetInstance(MenuCache::class);
    app()->forgetInstance(MenuRenderer::class);
    $key = app(MenuCache::class)->key('primary', url('/'));
    cache()->put($key, ['has_items' => true, 'links' => [['label' => 'Old home link', 'url' => url('/'), 'new_tab' => false, 'active' => false, 'children' => []]]], 3600);

    $this->get('/')->assertOk()->assertSee('Old home link');
});
