<?php

use App\Verso\Site\Menus\MenuLocations;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;

it('finds the menu of a location', function () {
    $primary = Menu::factory()->at('primary')->create(['name' => 'Main navigation']);
    Menu::factory()->create(['name' => 'Unused links']);

    expect(Menu::forLocation('primary')->is($primary))->toBeTrue()
        ->and(Menu::forLocation('footer'))->toBeNull();
});

it('moves a location from one menu to another', function () {
    $old = Menu::factory()->at('primary')->create();
    $new = Menu::factory()->create();

    $new->assignLocation('primary');

    expect($new->fresh()->location)->toBe('primary')
        ->and($old->fresh()->location)->toBeNull();
});

it('can be removed from every location', function () {
    $menu = Menu::factory()->at('footer')->create();

    $menu->assignLocation(null);

    expect($menu->fresh()->location)->toBeNull();
});

it('refuses unknown locations', function () {
    Menu::factory()->create()->assignLocation('sidebar');
})->throws(InvalidArgumentException::class, 'Unknown menu location [sidebar].');

it('accepts locations registered by themes', function () {
    app(MenuLocations::class)->register('sidebar', 'Sidebar links');

    expect(Menu::factory()->create()->assignLocation('sidebar')->location)->toBe('sidebar');
});

it('builds the item tree in order', function () {
    $menu = Menu::factory()->create();
    $about = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'About', 'position' => 1]);
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Home', 'position' => 0]);
    MenuItem::factory()->under($about)->create(['label' => 'Team', 'position' => 1]);
    MenuItem::factory()->under($about)->create(['label' => 'History', 'position' => 0]);

    $tree = $menu->tree();

    expect($tree->pluck('label')->all())->toBe(['Home', 'About'])
        ->and($tree[1]->children->pluck('label')->all())->toBe(['History', 'Team'])
        ->and($tree[0]->children)->toBeEmpty();
});

it('measures the depth of the tree', function () {
    $menu = Menu::factory()->create();
    expect($menu->depth())->toBe(0);

    $top = MenuItem::factory()->create(['menu_id' => $menu->id]);
    expect($menu->depth())->toBe(1);

    $middle = MenuItem::factory()->under($top)->create();
    MenuItem::factory()->under($middle)->create();
    expect($menu->depth())->toBe(3);
});

it('deletes its items with the menu', function () {
    $menu = Menu::factory()->create();
    $top = MenuItem::factory()->create(['menu_id' => $menu->id]);
    MenuItem::factory()->under($top)->create();

    $menu->delete();

    expect(MenuItem::count())->toBe(0);
});
