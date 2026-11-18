<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Site\Actions\SyncMenuItems;
use App\Verso\Site\Enums\MenuItemType;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;

it('replaces the items with a nested tree', function () {
    $menu = Menu::factory()->create();
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Old link']);
    $about = Entry::factory()->page()->published()->create();
    $travel = Term::factory()->create();

    app(SyncMenuItems::class)->handle($menu, [
        ['type' => 'entry', 'entry_id' => $about->id, 'children' => [
            ['type' => 'custom', 'label' => 'Contact', 'url' => '/contact', 'open_in_new_tab' => false],
            ['type' => 'term', 'term_id' => $travel->id, 'label' => ' '],
        ]],
        ['type' => 'custom', 'label' => ' Shop ', 'url' => 'https://market.example.org', 'open_in_new_tab' => true],
    ]);

    $tree = $menu->tree();

    expect(MenuItem::count())->toBe(4)
        ->and($tree)->toHaveCount(2)
        ->and($tree[0]->entry_id)->toBe($about->id)
        ->and($tree[0]->children)->toHaveCount(2)
        ->and($tree[0]->children[0]->url)->toBe('/contact')
        ->and($tree[0]->children[0]->position)->toBe(0)
        ->and($tree[0]->children[1]->type)->toBe(MenuItemType::Term)
        ->and($tree[0]->children[1]->label)->toBeNull()
        ->and($tree[1]->label)->toBe('Shop')
        ->and($tree[1]->open_in_new_tab)->toBeTrue()
        ->and($tree[1]->position)->toBe(1);
});

it('ignores ids and urls that do not match the item type', function () {
    $menu = Menu::factory()->create();
    $term = Term::factory()->create();

    app(SyncMenuItems::class)->handle($menu, [
        ['type' => 'term', 'term_id' => $term->id, 'entry_id' => 99, 'url' => '/ignored'],
    ]);

    $item = MenuItem::first();
    expect($item->entry_id)->toBeNull()->and($item->url)->toBeNull()->and($item->term_id)->toBe($term->id);
});

it('empties a menu', function () {
    $menu = Menu::factory()->create();
    MenuItem::factory()->count(3)->create(['menu_id' => $menu->id]);

    app(SyncMenuItems::class)->handle($menu, []);

    expect(MenuItem::count())->toBe(0);
});

it('leaves other menus alone', function () {
    $footer = MenuItem::factory()->create();

    app(SyncMenuItems::class)->handle(Menu::factory()->create(), []);

    expect($footer->fresh())->not->toBeNull();
});

it('refuses trees deeper than the limit', function () {
    $action = new SyncMenuItems(maxDepth: 2);
    $link = ['type' => 'custom', 'label' => 'Events', 'url' => '/events'];

    $action->handle(Menu::factory()->create(), [[...$link, 'children' => [[...$link, 'children' => [$link]]]]]);
})->throws(InvalidArgumentException::class, 'Menus can be at most 2 levels deep.');

it('measures the depth of item data', function () {
    $link = ['type' => 'custom'];

    expect(SyncMenuItems::depthOf([]))->toBe(0)
        ->and(SyncMenuItems::depthOf([$link, $link]))->toBe(1)
        ->and(SyncMenuItems::depthOf([$link, [...$link, 'children' => [[...$link, 'children' => [$link]]]]]))->toBe(3);
});
