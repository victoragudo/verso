<?php

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Editor\Rules\ValidBlocks;
use App\Verso\Site\Blocks\ColorSchemeToggleBlock;
use App\Verso\Site\Blocks\SiteBlockRegistry;
use App\Verso\Site\Parts\SitePartStore;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\ThemeManager;
use Illuminate\Support\Facades\Validator;

function toggleHeader(string $theme, array $attrs = []): void
{
    app(SitePartStore::class)->publish($theme, 'header', [[
        'id' => 'row', 'type' => 'section', 'attrs' => [],
        'children' => [
            ['id' => 'logo', 'type' => 'site-logo'],
            ['id' => 'scheme', 'type' => 'color-scheme-toggle', 'attrs' => $attrs],
        ],
    ]]);
}

it('is offered in the site editor with plain fields', function () {
    $block = app(SiteBlockRegistry::class)->find('color-scheme-toggle');

    expect($block)->toBeInstanceOf(ColorSchemeToggleBlock::class)
        ->and($block->label())->toBe('Dark mode switch')
        ->and(array_map(fn ($field) => $field->name, $block->fields()))->toBe(['darkLabel', 'lightLabel', 'showText']);
});

it('renders a hidden button with both actions and the click script once', function () {
    $html = app(BlockRenderer::class)->render([
        ['id' => 'a', 'type' => 'color-scheme-toggle', 'attrs' => ['darkLabel' => 'Night <reading>', 'showText' => true]],
        ['id' => 'b', 'type' => 'color-scheme-toggle'],
    ]);

    expect(substr_count($html, '<button type="button" class="verso-scheme-toggle" data-verso-scheme-toggle hidden>'))->toBe(2)
        ->and($html)->toContain('Night &lt;reading&gt;')
        ->and($html)->toContain('<span class="verso-scheme-toggle__text">Switch to light mode</span>')
        ->and($html)->toContain('<span class="visually-hidden">Switch to dark mode</span>')
        ->and(substr_count($html, "const key = '".ColorSchemeToggleBlock::STORAGE_KEY."'"))->toBe(1)
        ->and(substr_count($html, '<svg aria-hidden="true"'))->toBe(4);
});

it('checks the fields when saving', function () {
    $valid = fn (array $attrs): bool => Validator::make(
        ['blocks' => [['id' => 't1', 'type' => 'color-scheme-toggle', 'attrs' => $attrs]]],
        ['blocks' => [new ValidBlocks(User::factory()->admin()->create())]],
    )->passes();

    expect($valid(['darkLabel' => 'Lights off', 'showText' => true]))->toBeTrue()
        ->and($valid(['darkLabel' => str_repeat('a', 61)]))->toBeFalse()
        ->and($valid(['showText' => 'yes please']))->toBeFalse();
});

it('adds the early head script only when a published part holds the switch', function (string $theme) {
    app(Settings::class)->set('theme.active', $theme);

    $this->get('/')->assertOk()->assertDontSee(ColorSchemeToggleBlock::STORAGE_KEY, false);

    toggleHeader($theme);
    app()->forgetScopedInstances();

    $html = $this->get('/')->assertOk()->assertSee('data-verso-scheme-toggle', false)->getContent();
    $head = substr($html, 0, (int) strpos($html, '</head>'));

    expect($head)->toContain("localStorage.getItem('".ColorSchemeToggleBlock::STORAGE_KEY."')")
        ->and(strpos($head, '<meta name="color-scheme" content="light dark">'))->toBeLessThan(strpos($head, 'localStorage.getItem'));
})->with(['ashgrove', 'default']);

it('finds the switch in nested blocks and in previews only', function () {
    $store = app(SitePartStore::class);

    expect($store->uses('ashgrove', 'color-scheme-toggle'))->toBeFalse();

    $store->preview('ashgrove', 'footer', [['id' => 'f', 'type' => 'section', 'children' => [['id' => 'c', 'type' => 'section', 'children' => [['id' => 't', 'type' => 'color-scheme-toggle']]]]]]);

    expect($store->uses('ashgrove', 'color-scheme-toggle'))->toBeTrue()
        ->and($store->uses('default', 'color-scheme-toggle'))->toBeFalse();
});

it('keeps the head script tiny and safe without storage', function () {
    $script = ColorSchemeToggleBlock::headScript();

    expect(strlen($script))->toBeLessThan(600)
        ->and($script)->toContain('try {')
        ->and($script)->not->toContain('{{');
});

it('gives both bundled themes light and dark colors that follow the switch', function (string $theme) {
    $css = (string) file_get_contents(app(ThemeManager::class)->find($theme)->filePath('assets/theme.css'));

    expect($css)->toContain('color-scheme: light dark;')
        ->toContain("&:has(> head > meta[name='color-scheme'][content='dark'])")
        ->toContain("&:has(> head > meta[name='color-scheme'][content='light'])")
        ->toContain('light-dark(')
        ->toContain('.verso-scheme-toggle__to-light')
        ->toContain('.verso-scheme-toggle[hidden]');
})->with(['ashgrove', 'default']);

it('keeps the fallback colors for browsers without light-dark() apart from the light-dark() tokens', function (string $theme) {
    $css = (string) file_get_contents(app(ThemeManager::class)->find($theme)->filePath('assets/theme.css'));
    $fallback = substr($css, (int) strpos($css, '@supports not (color: light-dark(white, black))'), (int) strpos($css, '@supports (color: light-dark(white, black))') - (int) strpos($css, '@supports not (color: light-dark(white, black))'));

    preg_match_all('/light-dark\((?!white, black\))([^)]+)\)/', $css, $calls);

    expect($fallback)->toContain('@media (prefers-color-scheme: dark)')
        ->toContain("&:has(> head > meta[name='color-scheme'][content='light'])")
        ->toContain("&:has(> head > meta[name='color-scheme'][content='dark'])")
        ->not->toContain('light-dark(var(')
        ->and($css)->not->toContain(':not(:has(');

    foreach ($calls[1] as $arguments) {
        expect($arguments)->toStartWith('var(--', "{$theme} uses light-dark() with raw colors, which has no fallback");
    }
})->with(['ashgrove', 'default']);

it('lets a custom default theme color change only the light colors', function () {
    $themes = app(ThemeManager::class);
    $customizer = app(ThemeCustomizer::class);
    $customizer->save($themes->find('default'), ['background_color' => '#fff8ee', 'accent_color' => '#7c2d12']);

    expect($customizer->css($themes->find('default')))->toBe(':root { --accent-light: #7c2d12; --bg-light: #fff8ee; }');
});
