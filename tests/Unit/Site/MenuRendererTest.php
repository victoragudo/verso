<?php

use App\Models\User;
use App\Verso\Content\Actions\UpdateEntry;
use App\Verso\Content\Models\Entry;
use App\Verso\Site\Menus\MenuCache;
use App\Verso\Site\Menus\MenuRenderer;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use Illuminate\Support\Facades\DB;

it('returns no links for an empty location', function () {
    expect(app(MenuRenderer::class)->links('primary'))->toBe([]);
});

it('returns nested visible links and marks the current page', function () {
    $menu = Menu::factory()->at('primary')->create();
    $about = Entry::factory()->page()->published()->create(['title' => 'About', 'slug' => 'about']);
    $parent = MenuItem::factory()->forEntry($about)->create(['menu_id' => $menu->id]);
    MenuItem::factory()->under($parent)->create(['label' => 'Contact', 'url' => '/contact', 'open_in_new_tab' => true]);

    $links = app(MenuRenderer::class)->links('primary', url('/contact/'));

    expect($links)->toBe([[
        'label' => 'About',
        'url' => url('/about'),
        'new_tab' => false,
        'active' => false,
        'mega' => false,
        'rel' => null,
        'visibility' => 'everyone',
        'children' => [[
            'label' => 'Contact',
            'url' => url('/contact'),
            'new_tab' => true,
            'active' => true,
            'mega' => false,
            'rel' => null,
            'visibility' => 'everyone',
            'children' => [],
        ]],
    ]]);
});

it('leaves out hidden items and their children', function () {
    $menu = Menu::factory()->at('primary')->create();
    $draft = MenuItem::factory()->forEntry(Entry::factory()->page()->create())->create(['menu_id' => $menu->id]);
    MenuItem::factory()->under($draft)->create(['label' => 'Events', 'url' => '/events']);
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Shop', 'url' => 'https://market.example.org', 'position' => 1]);

    expect(array_column(app(MenuRenderer::class)->links('primary'), 'label'))->toBe(['Shop']);
});

describe('menu cache', function () {
    it('builds a location once and then reads it from the cache', function () {
        $menu = Menu::factory()->at('primary')->create();
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Workshops', 'url' => '/workshops']);
        $renderer = app(MenuRenderer::class);
        $renderer->links('primary');

        DB::enableQueryLog();
        $links = $renderer->links('primary', url('/workshops'));

        expect(DB::getQueryLog())->toBe([])
            ->and($links[0]['label'])->toBe('Workshops')
            ->and($links[0]['active'])->toBeTrue();
    });

    it('marks the active link for each request, not from the cache', function () {
        $menu = Menu::factory()->at('primary')->create();
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Recipes', 'url' => '/recipes']);
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Travel notes', 'url' => '/travel', 'position' => 1]);
        $renderer = app(MenuRenderer::class);

        $onRecipes = $renderer->links('primary', url('/recipes'));
        $onTravel = $renderer->links('primary', url('/travel'));

        expect(array_column($onRecipes, 'active'))->toBe([true, false])
            ->and(array_column($onTravel, 'active'))->toBe([false, true]);
    });

    it('shows a renamed item right after the change', function () {
        $menu = Menu::factory()->at('footer')->create();
        $item = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Press kit', 'url' => '/press']);
        $renderer = app(MenuRenderer::class);
        $renderer->links('footer');

        $item->update(['label' => 'Media resources']);

        expect($renderer->links('footer')[0]['label'])->toBe('Media resources');
    });

    it('drops a deleted item right after the change', function () {
        $menu = Menu::factory()->at('footer')->create();
        $item = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Careers', 'url' => '/careers']);
        $renderer = app(MenuRenderer::class);
        $renderer->links('footer');

        $item->delete();

        expect($renderer->links('footer'))->toBe([])
            ->and($renderer->hasItems('footer'))->toBeFalse();
    });

    it('follows a renamed page linked from the menu', function () {
        $menu = Menu::factory()->at('primary')->create();
        $page = Entry::factory()->page()->published()->create(['title' => 'Opening hours', 'slug' => 'opening-hours']);
        MenuItem::factory()->forEntry($page)->create(['menu_id' => $menu->id]);
        $renderer = app(MenuRenderer::class);
        $renderer->links('primary');

        app(UpdateEntry::class)->handle($page, ['title' => 'Visit us', 'slug' => 'visit-us'], User::factory()->admin()->create());

        expect($renderer->links('primary')[0])->toMatchArray(['label' => 'Visit us', 'url' => url('/visit-us')]);
    });

    it('knows when a location has items', function () {
        $renderer = app(MenuRenderer::class);
        expect($renderer->hasItems('primary'))->toBeFalse();

        $menu = Menu::factory()->at('primary')->create();
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Contact', 'url' => '/contact']);

        expect($renderer->hasItems('primary'))->toBeTrue();
    });

    it('builds the tree every time when the ttl is zero', function () {
        config(['verso.menus.cache_ttl' => 0]);
        app()->forgetInstance(MenuCache::class);
        $menu = Menu::factory()->at('primary')->create();
        MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Archive', 'url' => '/archive']);
        $renderer = app(MenuRenderer::class);
        $renderer->links('primary');

        DB::enableQueryLog();
        $renderer->links('primary');

        expect(DB::getQueryLog())->not->toBe([]);
    });
});
