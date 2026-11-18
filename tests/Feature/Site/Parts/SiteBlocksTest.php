<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRegistry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Media\Models\Media;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;
use App\Verso\Site\Blocks\SiteBlockRegistry;
use App\Verso\Site\Blocks\SiteLogoBlock;
use App\Verso\Site\Models\Menu;
use App\Verso\Site\Models\MenuItem;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Site\SiteServiceProvider;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    Carbon::setTestNow('2027-03-08 10:00:00');
    app(Settings::class)->set('site.name', 'Kvarnholmen Bakery');
    app(Settings::class)->set('site.tagline', 'Sourdough and cardamom buns since 1998');
});

function renderSiteBlock(string $type, array $attrs = []): string
{
    return app(BlockRenderer::class)->render([['id' => 'b1', 'type' => $type, 'attrs' => $attrs]]);
}

it('registers every site block for saving, rendering and the site editor', function () {
    $names = ['site-logo', 'site-title', 'site-tagline', 'navigation', 'search', 'social-links', 'link-columns', 'copyright', 'announcement-bar', 'color-scheme-toggle'];

    expect(count(SiteServiceProvider::SITE_BLOCKS))->toBe(10)
        ->and(array_keys(app(SiteBlockRegistry::class)->all()))->toBe($names)
        ->and(array_column(app(SiteBlockRegistry::class)->toArray(), 'label'))->toContain('Logo', 'Menu', 'Announcement bar');

    foreach ($names as $name) {
        expect(app(BlockRegistry::class)->has($name))->toBeTrue();
    }
});

it('keeps site blocks out of the entry editor block list', function () {
    expect(app(PluginBlockRegistry::class)->has('site-logo'))->toBeFalse();
});

it('shows the site name when there is no logo', function () {
    expect(renderSiteBlock('site-logo'))->toBe('<a class="verso-site-logo" href="'.url('/').'" rel="home"><span class="verso-site-logo__name">Kvarnholmen Bakery</span></a>')
        ->and(renderSiteBlock('site-logo', ['linkHome' => false]))->toStartWith('<div class="verso-site-logo">');
});

it('renders the site name and tagline from the settings', function () {
    expect(renderSiteBlock('site-title'))->toContain('<a href="'.url('/').'" rel="home">Kvarnholmen Bakery</a>')
        ->and(renderSiteBlock('site-tagline'))->toBe('<p class="verso-site-tagline">Sourdough and cardamom buns since 1998</p>');

    app(Settings::class)->set('site.tagline', '');

    expect(renderSiteBlock('site-tagline'))->toBe('');
});

it('renders the menu of a location with sub items and a fold button', function () {
    $menu = Menu::factory()->at('primary')->create();
    $breads = MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Breads', 'url' => '/breads']);
    MenuItem::factory()->under($breads)->create(['label' => 'Rye loaf', 'url' => '/breads/rye']);
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Order online', 'url' => 'https://order.example.se', 'open_in_new_tab' => true, 'position' => 1]);

    $html = renderSiteBlock('navigation', ['location' => 'primary', 'label' => 'Main']);

    expect($html)->toContain('<nav class="verso-nav" aria-label="Main" data-verso-nav><button class="verso-nav__toggle" type="button" popovertarget="verso-nav-primary" aria-controls="verso-nav-primary">')
        ->toContain('<span>Menu</span></button><ul id="verso-nav-primary" class="verso-nav__list" popover="auto">')
        ->toContain('<li class="has-children"><a href="'.url('/breads').'">Breads</a><button class="verso-nav__more" type="button" aria-expanded="false" aria-controls="verso-nav-primary-0" data-verso-submenu><span class="visually-hidden">More in Breads</span>')
        ->toContain('<ul id="verso-nav-primary-0" class="verso-nav__submenu"><li><a href="'.url('/breads/rye').'">Rye loaf</a></li></ul></li>')
        ->toContain('target="_blank" rel="noopener">Order online</a>')
        ->toContain('<script>');
});

it('prints the menu fit script once per page', function () {
    $menu = Menu::factory()->at('primary')->create();
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Breads', 'url' => '/breads']);
    $footer = Menu::factory()->at('footer')->create();
    MenuItem::factory()->create(['menu_id' => $footer->id, 'label' => 'Privacy', 'url' => '/privacy']);

    $html = renderSiteBlock('navigation', ['location' => 'primary']).renderSiteBlock('navigation', ['location' => 'footer']);

    expect(substr_count($html, '<script>'))->toBe(1)
        ->and(substr_count($html, 'data-verso-nav>'))->toBe(2);
});

it('renders nothing for an empty or unknown menu location', function () {
    expect(renderSiteBlock('navigation', ['location' => 'footer']))->toBe('')
        ->and(renderSiteBlock('navigation', ['location' => 'sidebar-of-doom']))->toBe('');
});

it('can show the menu without the fold button', function () {
    $menu = Menu::factory()->at('footer')->create();
    MenuItem::factory()->create(['menu_id' => $menu->id, 'label' => 'Privacy', 'url' => '/privacy']);

    expect(renderSiteBlock('navigation', ['location' => 'footer', 'label' => 'Legal', 'collapse' => false]))
        ->not->toContain('popover')->not->toContain('<script>')->toContain('<nav class="verso-nav is-wide" aria-label="Legal">');
});

it('renders a search form for the site search', function () {
    expect(renderSiteBlock('search', ['placeholder' => 'Find a recipe', 'buttonLabel' => 'Go']))
        ->toContain('action="'.route('theme.search').'"')
        ->toContain('name="q" placeholder="Find a recipe"')
        ->toContain('<button type="submit">Go</button>');
});

it('names social networks and skips unsafe addresses', function () {
    $html = renderSiteBlock('social-links', ['links' => "https://www.instagram.com/kvarnholmenbakery\nhttps://mastodon.social/@kvarnholmen\njavascript:alert(1)\nhttps://blog.kvarnholmen.se"]);

    expect($html)->toContain('>Instagram</a>')->toContain('>Mastodon</a>')->toContain('>https://blog.kvarnholmen.se</a>')
        ->not->toContain('javascript')
        ->and(renderSiteBlock('social-links', ['links' => '']))->toBe('');
});

it('builds footer link columns from plain text', function () {
    $html = renderSiteBlock('link-columns', ['columns' => "Shop:\nBreads | /breads\nGift cards | /gift-cards\n\nAbout:\nOur story | /about\nBad | javascript:alert(1)"]);

    expect($html)->toContain('<nav class="verso-link-columns" aria-label="Footer">')
        ->toContain('<h2>Shop</h2><ul><li><a href="/breads">Breads</a></li><li><a href="/gift-cards">Gift cards</a></li></ul>')
        ->toContain('<h2>About</h2><ul><li><a href="/about">Our story</a></li></ul>')
        ->not->toContain('javascript');
});

it('keeps the copyright year up to date', function () {
    expect(renderSiteBlock('copyright'))->toBe('<p class="verso-copyright">© 2027 Kvarnholmen Bakery. All rights reserved.</p>');

    Carbon::setTestNow('2031-01-01 00:00:01');

    expect(renderSiteBlock('copyright', ['text' => '{site} <b>{year}</b>']))->toBe('<p class="verso-copyright">Kvarnholmen Bakery &lt;b&gt;2031&lt;/b&gt;</p>');
});

it('renders an announcement bar with an optional link', function () {
    expect(renderSiteBlock('announcement-bar', ['text' => 'Closed on 24 December', 'linkLabel' => 'Holiday hours', 'url' => '/hours', 'tone' => 'dark']))
        ->toBe('<div class="verso-announcement verso-announcement--dark" role="region" aria-label="Announcement"><p>Closed on 24 December <a href="/hours">Holiday hours</a></p></div>')
        ->and(renderSiteBlock('announcement-bar', ['text' => 'Free delivery', 'tone' => 'neon']))->toContain('verso-announcement--accent')->not->toContain('<a ')
        ->and(renderSiteBlock('announcement-bar'))->toBe('');
});

it('validates site block fields when saved', function () {
    $valid = fn (array $attrs): bool => Validator::make(
        ['blocks' => [['id' => 'a1', 'type' => 'announcement-bar', 'attrs' => $attrs]]],
        ['blocks' => [new ValidBlocks(User::factory()->admin()->create())]],
    )->passes();

    expect($valid(['text' => 'Fresh bread at 7', 'tone' => 'light']))->toBeTrue()
        ->and($valid(['text' => 'Fresh bread at 7', 'tone' => 'neon']))->toBeFalse()
        ->and($valid(['text' => 'Fresh bread', 'url' => 'javascript:alert(1)']))->toBeFalse()
        ->and($valid([]))->toBeFalse();
});

it('shows an edited header with site blocks on the public site of both themes', function (string $theme) {
    app(Settings::class)->set('theme.active', $theme);
    app(SitePartStore::class)->publish($theme, 'header', [[
        'id' => 'row', 'type' => 'section', 'attrs' => ['design' => ['layout' => ['display' => 'flex', 'justify' => 'between', 'align' => 'center']]],
        'children' => [
            ['id' => 'logo', 'type' => 'site-logo'],
            ['id' => 'search', 'type' => 'search'],
        ],
    ], ['id' => 'news', 'type' => 'announcement-bar', 'attrs' => ['text' => 'New winter menu is here']]]);

    $this->get('/')
        ->assertOk()
        ->assertSee('<header class="site-part site-part-header" data-site-part="header">', false)
        ->assertSee('New winter menu is here')
        ->assertSee('verso-site-logo', false)
        ->assertSee('display:flex;align-items:center;justify-content:space-between', false)
        ->assertDontSee('class="site-header"', false);
})->with(['ashgrove', 'default']);

it('takes the logo height in any CSS unit and keeps old pixel numbers', function () {
    $media = Media::factory()->create(['mime_type' => 'image/png', 'width' => 400, 'height' => 100]);
    app(ThemeCustomizer::class)->save(app(ThemeManager::class)->active(), ['logo' => $media->id]);

    expect(renderSiteBlock('site-logo', ['height' => 60]))->toContain('height="60"')->toContain('width="240"')
        ->and(renderSiteBlock('site-logo', ['height' => '48PX']))->toContain('height="48"')
        ->and(renderSiteBlock('site-logo', ['height' => 9000]))->toContain('height="240"')
        ->and(renderSiteBlock('site-logo', ['height' => '3rem']))->toContain('style="height:3rem;width:auto"')
        ->and(renderSiteBlock('site-logo', ['height' => 'clamp(2rem, 6vw, 4rem)']))->toContain('style="height:clamp(2rem, 6vw, 4rem);width:auto"')
        ->and(renderSiteBlock('site-logo', ['height' => 'url(x)']))->toContain('height="44"');
});

it('checks the logo height as a CSS size when saving', function () {
    $field = collect(app(SiteLogoBlock::class)->fields())->firstWhere('name', 'height');
    $passes = fn (mixed $value): bool => Validator::make(['height' => $value], ['height' => $field->rules()])->passes();

    expect($field->toArray())->toMatchArray(['type' => 'length', 'default' => '44px'])
        ->and($field->toArray()['value']['negative'])->toBeFalse()
        ->and($passes(44))->toBeTrue()
        ->and($passes('2.5rem'))->toBeTrue()
        ->and($passes('min(10vw, 80px)'))->toBeTrue()
        ->and($passes('-4px'))->toBeFalse()
        ->and($passes('44px; background: url(x)'))->toBeFalse();
});
