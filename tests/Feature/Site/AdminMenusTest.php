<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

it('shows an empty builder when there are no menus', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.site.menus.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site/menus')
            ->where('menus', [])
            ->where('menu', null)
            ->where('locations', [['name' => 'primary', 'label' => 'Primary menu'], ['name' => 'footer', 'label' => 'Footer menu']])
            ->where('maxDepth', 3)
        );
});

it('shows the first menu with its item tree', function () {
    $menu = Menu::factory()->at('primary')->create(['name' => 'Main navigation']);
    Menu::factory()->create(['name' => 'Recipe sections']);
    $about = Entry::factory()->page()->published()->create(['title' => 'About the journal']);
    $parent = MenuItem::factory()->forEntry($about)->create(['menu_id' => $menu->id]);
    MenuItem::factory()->under($parent)->create(['label' => 'Contact', 'url' => '/contact']);

    $this->actingAs($this->admin)
        ->get(route('admin.site.menus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('menus', 2)
            ->where('menus.0.name', 'Main navigation')
            ->where('menus.0.items_count', 2)
            ->where('menu.id', $menu->id)
            ->where('menu.location', 'primary')
            ->where('menu.items.0.type', 'entry')
            ->where('menu.items.0.entry_id', $about->id)
            ->where('menu.items.0.title', 'About the journal')
            ->where('menu.items.0.visible', true)
            ->where('menu.items.0.children.0.label', 'Contact')
            ->where('menu.items.0.children.0.url', '/contact')
        );
});

it('selects a menu from the query string', function () {
    Menu::factory()->create(['name' => 'Club pages']);
    $footer = Menu::factory()->create(['name' => 'Footer links']);

    $this->actingAs($this->admin)
        ->get(route('admin.site.menus.index', ['menu' => $footer->id]))
        ->assertInertia(fn (Assert $page) => $page->where('menu.id', $footer->id));
});

it('offers published entries and terms to add', function () {
    Entry::factory()->published()->create(['title' => 'Cooking Paella for Twelve People']);
    Entry::factory()->create(['title' => 'Draft about bread']);
    Entry::factory()->page()->published()->create(['title' => 'Contact']);
    Term::factory()->create(['name' => 'Travel']);
    Term::factory()->tag()->create(['name' => 'Porto']);

    $this->actingAs($this->admin)
        ->get(route('admin.site.menus.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('sources.entries.0.name', 'post')
            ->where('sources.entries.0.label', 'Posts')
            ->has('sources.entries.0.items', 1)
            ->where('sources.entries.0.items.0.title', 'Cooking Paella for Twelve People')
            ->where('sources.entries.1.items.0.title', 'Contact')
            ->where('sources.terms.0.items.0.title', 'Travel')
            ->where('sources.terms.1.items.0.title', 'Porto')
        );
});

it('limits the picker lists', function () {
    config(['verso.menus.picker_limit' => 2]);
    Entry::factory()->published()->count(4)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.site.menus.index'))
        ->assertInertia(fn (Assert $page) => $page->has('sources.entries.0.items', 2));
});

it('creates a menu in a location', function () {
    $old = Menu::factory()->at('primary')->create(['name' => 'Winter navigation']);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.site.menus.store'), ['name' => 'Main navigation', 'location' => 'primary']);

    $menu = Menu::firstWhere('name', 'Main navigation');
    $response->assertRedirect(route('admin.site.menus.index', ['menu' => $menu->id]));
    expect($menu->location)->toBe('primary')
        ->and($old->fresh()->location)->toBeNull();
});

it('creates a menu without a location', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.site.menus.store'), ['name' => 'Seasonal recipes', 'location' => null])
        ->assertSessionHasNoErrors();

    expect(Menu::firstWhere('name', 'Seasonal recipes')->location)->toBeNull();
});

it('validates the menu form', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.site.menus.store'), ['name' => '', 'location' => 'sidebar'])
        ->assertSessionHasErrors(['name', 'location']);
});

it('renames a menu and changes its location', function () {
    $menu = Menu::factory()->at('primary')->create();

    $this->actingAs($this->admin)
        ->put(route('admin.site.menus.update', $menu), ['name' => 'Footer links', 'location' => 'footer'])
        ->assertRedirect(route('admin.site.menus.index', ['menu' => $menu->id]));

    expect($menu->fresh())->name->toBe('Footer links')->location->toBe('footer');
});

it('saves the nested items', function () {
    $menu = Menu::factory()->create();
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Old link']);
    $about = Entry::factory()->page()->published()->create();
    $travel = Term::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.site.menus.items', $menu), ['items' => [
            ['type' => 'entry', 'entry_id' => $about->id, 'label' => null, 'url' => null, 'open_in_new_tab' => false, 'children' => [
                ['type' => 'term', 'term_id' => $travel->id, 'children' => []],
            ]],
            ['type' => 'custom', 'label' => 'Newsletter', 'url' => 'https://letters.example.org/signup', 'open_in_new_tab' => true, 'children' => []],
        ]])
        ->assertRedirect(route('admin.site.menus.index', ['menu' => $menu->id]));

    $tree = $menu->tree();
    expect($tree)->toHaveCount(2)
        ->and($tree[0]->entry_id)->toBe($about->id)
        ->and($tree[0]->children[0]->term_id)->toBe($travel->id)
        ->and($tree[1]->label)->toBe('Newsletter')
        ->and($tree[1]->open_in_new_tab)->toBeTrue()
        ->and(MenuItem::where('label', 'Old link')->exists())->toBeFalse();
});

it('empties a menu', function () {
    $menu = Menu::factory()->create();
    MenuItem::factory()->create(['menu_id' => $menu->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.site.menus.items', $menu), ['items' => []])
        ->assertSessionHasNoErrors();

    expect(MenuItem::count())->toBe(0);
});

it('validates menu items', function (array $item, string $error) {
    $menu = Menu::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.site.menus.items', $menu), ['items' => [$item]])
        ->assertSessionHasErrors($error);
})->with([
    'unknown type' => [['type' => 'widget'], 'items.0.type'],
    'entry without id' => [['type' => 'entry'], 'items.0.entry_id'],
    'missing entry' => [['type' => 'entry', 'entry_id' => 999999], 'items.0.entry_id'],
    'term without id' => [['type' => 'term'], 'items.0.term_id'],
    'link without url' => [['type' => 'custom', 'label' => 'Events'], 'items.0.url'],
    'link without label' => [['type' => 'custom', 'url' => '/events'], 'items.0.label'],
    'javascript link' => [['type' => 'custom', 'label' => 'Click', 'url' => 'javascript:alert(1)'], 'items.0.url'],
    'nested bad link' => [['type' => 'custom', 'label' => 'Events', 'url' => '/events', 'children' => [['type' => 'custom', 'label' => 'Bad', 'url' => 'ftp://files']]], 'items.0.children.0.url'],
]);

it('refuses items deeper than the limit', function () {
    config(['verso.menus.max_depth' => 2]);
    $menu = Menu::factory()->create();
    $link = ['type' => 'custom', 'label' => 'Events', 'url' => '/events'];

    $this->actingAs($this->admin)
        ->put(route('admin.site.menus.items', $menu), ['items' => [[...$link, 'children' => [[...$link, 'children' => [$link]]]]]])
        ->assertSessionHasErrors('items.0.children.0.children');

    expect(MenuItem::count())->toBe(0);
});

it('deletes a menu with its items', function () {
    $menu = Menu::factory()->create();
    MenuItem::factory()->count(2)->create(['menu_id' => $menu->id]);

    $this->actingAs($this->admin)
        ->delete(route('admin.site.menus.destroy', $menu))
        ->assertRedirect(route('admin.site.menus.index'));

    expect(Menu::count())->toBe(0)->and(MenuItem::count())->toBe(0);
});

it('forbids users who cannot manage themes', function () {
    $editor = User::factory()->editor()->create();
    $menu = Menu::factory()->create();

    $this->actingAs($editor)->get(route('admin.site.menus.index'))->assertForbidden();
    $this->actingAs($editor)->post(route('admin.site.menus.store'), ['name' => 'Hack'])->assertForbidden();
    $this->actingAs($editor)->put(route('admin.site.menus.update', $menu), ['name' => 'Hack'])->assertForbidden();
    $this->actingAs($editor)->put(route('admin.site.menus.items', $menu), ['items' => []])->assertForbidden();
    $this->actingAs($editor)->delete(route('admin.site.menus.destroy', $menu))->assertForbidden();

    expect(Menu::count())->toBe(1);
});
