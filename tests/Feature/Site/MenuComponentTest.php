<?php

use App\Verso\Content\Models\Entry;
use App\Verso\Content\Models\Term;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Illuminate\Support\Facades\Blade;

it('renders the menu of a location as nested lists', function () {
    $menu = Menu::factory()->at('primary')->create();
    $blog = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Blog', 'url' => '/blog']);
    MenuItem::factory()->under($blog)->forTerm(Term::factory()->create(['name' => 'Travel', 'slug' => 'travel']))->create();
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Shop', 'url' => 'https://market.example.org', 'open_in_new_tab' => true, 'position' => 1]);

    $html = Blade::render('<x-verso-menu location="primary" label="Main" class="site-nav" />');

    expect($html)
        ->toContain('<nav class="verso-menu verso-menu--primary site-nav" aria-label="Main">')
        ->toContain('<a href="'.url('/blog').'"')
        ->toContain('verso-menu__list--depth-1')
        ->toContain('>Travel</a>')
        ->toContain('href="https://market.example.org" target="_blank" rel="noopener"')
        ->toContain('>Shop</a>')
        ->toContain('verso-menu__item--has-children');
});

it('renders nothing when the location has no menu', function () {
    expect(trim(Blade::render('<x-verso-menu location="footer" />')))->toBe('');
});

it('escapes labels', function () {
    $menu = Menu::factory()->at('footer')->create();
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Fish & <b>Chips</b>', 'url' => '/menu']);

    expect(Blade::render('<x-verso-menu location="footer" />'))->toContain('Fish &amp; &lt;b&gt;Chips&lt;/b&gt;');
});

it('marks the current page link', function () {
    $menu = Menu::factory()->at('primary')->create();
    $about = Entry::factory()->page()->published()->create(['slug' => 'about']);
    MenuItem::factory()->forEntry($about)->create(['menu_id' => $menu->id]);

    $this->get('/about');

    expect(Blade::render('<x-verso-menu location="primary" />'))
        ->toContain('verso-menu__item--active')
        ->toContain('aria-current="page"');
});
