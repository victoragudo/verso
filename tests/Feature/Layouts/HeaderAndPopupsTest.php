<?php

use App\Models\User;
use App\Verso\Content\Models\Entry;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Layouts\LayoutKind;
use App\Verso\Layouts\LayoutStore;
use App\Verso\Layouts\Models\SiteLayout;
use App\Verso\Layouts\PopupRenderer;
use App\Verso\Plugins\Blocks\PluginBlockRegistry;

function publishedLayout(LayoutKind $kind, string $name, array $blocks, array $conditions, array $settings = []): SiteLayout
{
    $layout = SiteLayout::query()->create(['kind' => $kind, 'name' => $name, 'blocks' => [], 'conditions' => [], 'settings' => [], 'status' => SiteLayout::DRAFT]);

    return app(LayoutStore::class)->publish($layout, ['blocks' => $blocks, 'conditions' => $conditions, 'settings' => $settings], null);
}

$everywhere = [['type' => 'everywhere', 'value' => '', 'exclude' => false]];

it('draws a mega menu as a button with a popover panel of blocks', function () {
    $html = app(BlockRenderer::class)->render([[
        'id' => 'b_mega', 'type' => 'mega-menu', 'attrs' => ['label' => 'Our breads', 'width' => 'full'],
        'children' => [['id' => 'b_list', 'type' => 'list', 'content' => '<li><a href="/rye">Rye</a></li>']],
    ]]);

    expect($html)->toContain('<button type="button" class="verso-mega__button" aria-expanded="false" aria-controls="vb-b_mega-panel" popovertarget="vb-b_mega-panel">Our breads')
        ->toContain('id="vb-b_mega-panel" popover="auto"')
        ->toContain('verso-mega__panel--full')
        ->toContain('data-hover')
        ->toContain('mega-menu.js')
        ->not->toContain('role="menu"');
});

it('draws the mobile menu designer as a drawer dialog for small screens', function () {
    $html = app(BlockRenderer::class)->render([[
        'id' => 'b_mob', 'type' => 'mobile-menu', 'attrs' => ['label' => 'Menu', 'side' => 'start', 'showOn' => 'tablet'],
        'children' => [['id' => 'b_btn', 'type' => 'button', 'attrs' => ['label' => 'Order online', 'url' => '/order']]],
    ]]);

    expect($html)->toContain('verso-mobile-menu verso-mobile-menu--tablet')
        ->toContain('commandfor="vb-b_mob-drawer" command="show-modal"')
        ->toContain('<dialog class="verso-mobile-menu__drawer verso-mobile-menu__drawer--start" id="vb-b_mob-drawer" aria-label="Menu" closedby="any">')
        ->toContain('Order online');
});

it('starts a mobile menu with the menu and social links', function () {
    $mobile = collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'mobile-menu');

    expect(array_column($mobile['starter'], 'type'))->toBe(['navigation', 'social-links']);
});

it('shows a different header on one page by condition', function () use ($everywhere) {
    $landing = Entry::factory()->published()->create(['type' => 'page', 'slug' => 'spring-menu', 'title' => 'Spring menu']);
    publishedLayout(LayoutKind::Header, 'Main', [['id' => 'b_1', 'type' => 'paragraph', 'content' => 'MAIN HEADER']], $everywhere);
    publishedLayout(LayoutKind::Header, 'Landing', [['id' => 'b_2', 'type' => 'paragraph', 'content' => 'LANDING HEADER']], [['type' => 'entry', 'value' => (string) $landing->id, 'exclude' => false]], ['transparent' => true]);

    expect($this->get('/')->getContent())->toContain('MAIN HEADER')->not->toContain('LANDING HEADER')
        ->and($this->get($landing->url())->getContent())->toContain('LANDING HEADER')->toContain('verso-header--transparent');
});

it('prints popups at the end of the page with their triggers and no tracking', function () use ($everywhere) {
    $popup = publishedLayout(LayoutKind::Popup, 'Opening offer', [['id' => 'b_p', 'type' => 'paragraph', 'content' => 'Ten percent off this week.']], $everywhere, [
        'type' => 'modal', 'title' => 'Fresh offer', 'trigger' => 'scroll', 'scroll' => 40, 'frequency' => 'days', 'days' => 3, 'devices' => ['computer', 'tablet'],
    ]);

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<dialog data-verso-popup="'.$popup->id.'" data-key="'.PopupRenderer::storageKey($popup).'" data-trigger="scroll" data-delay="5" data-scroll="40" data-frequency="days" data-days="3" data-devices="computer tablet"')
        ->toContain('aria-labelledby="verso-popup-'.$popup->id.'-title"')
        ->toContain('>Fresh offer</h2>')
        ->toContain('verso-blocks/popups.js')
        ->and(strpos($html, 'data-verso-popup'))->toBeGreaterThan(strpos($html, '<main'));
});

it('puts top bars above the header with a script that keeps a closed bar hidden', function () use ($everywhere) {
    $bar = publishedLayout(LayoutKind::Popup, 'Holiday hours', [['id' => 'b_p', 'type' => 'paragraph', 'content' => 'Closed on 25 December.']], $everywhere, ['type' => 'top-bar', 'devices' => ['phone']]);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('<div class="verso-topbar verso-popup--on-phone" id="verso-popup-'.$bar->id.'" role="region" aria-label="Holiday hours"')
        ->toContain('localStorage.getItem(k)')
        ->and(strpos($html, '<div class="verso-topbar'))->toBeLessThan(strpos($html, '<header class="site-header'));
});

it('builds a cookie notice where declining is as easy as accepting', function () use ($everywhere) {
    publishedLayout(LayoutKind::Popup, 'Cookies', [['id' => 'b_p', 'type' => 'paragraph', 'content' => 'We only use cookies we need.']], $everywhere, ['type' => 'cookie', 'acceptLabel' => 'Allow', 'dismissLabel' => 'No thanks']);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('data-cookie')
        ->toContain('<button type="button" class="verso-popup__button" data-consent="declined">No thanks</button>')
        ->toContain('<button type="button" class="verso-popup__button" data-consent="accepted">Allow</button>');
});

it('shows popups to visitors only when their rules fit', function () {
    publishedLayout(LayoutKind::Popup, 'Members news', [['id' => 'b_p', 'type' => 'paragraph', 'content' => 'MEMBERS ONLY']], [['type' => 'visitor', 'value' => 'signed_in', 'exclude' => false]]);

    expect($this->get('/')->getContent())->not->toContain('MEMBERS ONLY')
        ->and($this->actingAs(User::factory()->create())->get('/')->getContent())->toContain('MEMBERS ONLY');
});

it('opens a popup from a popup button', function () use ($everywhere) {
    $popup = publishedLayout(LayoutKind::Popup, 'Book a table', [], $everywhere, ['trigger' => 'click']);

    $html = app(BlockRenderer::class)->render([['id' => 'b_b', 'type' => 'popup-button', 'attrs' => ['label' => 'Book a table', 'popup' => (string) $popup->id]]]);
    $field = collect(collect(app(PluginBlockRegistry::class)->toArray())->firstWhere('name', 'popup-button')['fields'])->firstWhere('name', 'popup');

    expect($html)->toContain('data-verso-open-popup="'.$popup->id.'"')
        ->and($field['options'])->toBe([['value' => (string) $popup->id, 'label' => 'Book a table']]);
});

it('hides turned off popups and popups for no screen', function () use ($everywhere) {
    publishedLayout(LayoutKind::Popup, 'Nowhere', [['id' => 'b_p', 'type' => 'paragraph', 'content' => 'NO SCREENS']], $everywhere, ['devices' => []]);

    expect($this->get('/')->getContent())->not->toContain('NO SCREENS');
});
