<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Editor\BlockRenderer;
use App\Verso\Theme\Customizer\ThemeCustomizer;
use App\Verso\Theme\Styles\GlobalStyles;
use App\Verso\Theme\ThemeManager;

beforeEach(function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    $this->theme = app(ThemeManager::class)->find('ashgrove');
    $this->styles = app(GlobalStyles::class);
});

it('reads the tokens of the theme in groups', function () {
    $tokens = $this->styles->tokens($this->theme);

    expect(array_keys($tokens))->toBe([
        'color.accent', 'color.background', 'color.text', 'color.muted', 'color.surface',
        'font.heading', 'font.body', 'font.ui',
        'spacing.small', 'spacing.medium', 'spacing.large', 'spacing.x-large',
        'radius.base', 'width.wide', 'width.content',
    ])
        ->and($tokens['color.accent'])->toMatchArray(['kind' => 'color', 'label' => 'Accent', 'default' => '#2e5e4e', 'setting' => 'accent_color', 'use' => '--accent'])
        ->and($tokens['width.wide']['default'])->toBe('76rem');
});

it('keeps the values saved in the theme customizer', function () {
    app(ThemeCustomizer::class)->save($this->theme, ['accent_color' => '#7a2e12', 'heading_font' => 'instrument-sans', 'layout_width' => 70]);

    expect($this->styles->values($this->theme))->toMatchArray([
        'color.accent' => '#7a2e12',
        'font.heading' => 'instrument-sans',
        'width.wide' => '70rem',
        'color.background' => '#f6f7f3',
    ]);
});

it('saves linked tokens through the customizer and others in their own setting', function () {
    $rejected = $this->styles->save($this->theme, [
        'color.accent' => '#1f4e79',
        'color.background' => '#fffaf2',
        'radius.base' => '8px',
        'width.wide' => '68rem',
        'spacing.small' => '0.5rem',
        'color.text' => 'red',
        'font.ui' => 'comic-sans',
        'color.unknown' => '#000000',
    ]);

    expect($rejected)->toBe(['color.text', 'font.ui'])
        ->and(app(ThemeCustomizer::class)->values($this->theme))->toMatchArray(['accent_color' => '#1f4e79', 'layout_width' => 68])
        ->and(app(Settings::class)->get('theme.styles.ashgrove'))->toEqual(['color.background' => '#fffaf2', 'radius.base' => '8px']);
});

it('prints every token as a --verso variable that follows the theme variable', function () {
    $css = $this->styles->css($this->theme);

    expect($css)->toStartWith(':root { ')
        ->toContain('--verso-color-accent: var(--accent);')
        ->toContain('--verso-color-background: var(--paper);')
        ->toContain('--verso-radius-base: var(--radius);')
        ->toContain("--verso-font-heading: Newsreader, 'Newsreader Fallback', Charter")
        ->not->toContain('--paper-light:');
});

it('writes edited values into the theme variables', function () {
    $this->styles->save($this->theme, ['color.background' => '#fffaf2', 'font.ui' => 'system', 'spacing.large' => '2rem']);
    app()->forgetScopedInstances();

    expect(app(GlobalStyles::class)->css($this->theme))
        ->toContain('--paper-light: #fffaf2;')
        ->toContain('--font-ui: system-ui,')
        ->toContain('--space-3: 2rem;');
});

it('removes a saved value set back to the default', function () {
    $this->styles->save($this->theme, ['radius.base' => '8px']);
    $this->styles->save($this->theme, ['radius.base' => '3px']);

    expect(app(Settings::class)->get('theme.styles.ashgrove'))->toBeNull();
});

it('shows preview values for one request only', function () {
    $this->styles->preview($this->theme, ['color.accent' => '#5b2a86', 'color.surface' => '#efe8f7', 'radius.base' => 'url(x)']);

    expect($this->styles->values($this->theme))->toMatchArray(['color.accent' => '#5b2a86', 'color.surface' => '#efe8f7', 'radius.base' => '3px'])
        ->and(app(ThemeCustomizer::class)->css($this->theme))->toContain('--accent-light: #5b2a86;');
});

it('puts the tokens on public pages', function () {
    $this->styles->save($this->theme, ['color.surface' => '#efe8f7']);

    $this->get('/')->assertOk()->assertSee('--surface-light: #efe8f7;', false)->assertSee('--verso-color-surface: var(--surface);', false);
});

it('lets block styles use the tokens', function () {
    $html = app(BlockRenderer::class)->render([[
        'id' => 'p', 'type' => 'paragraph', 'content' => 'Fika at three', 'attrs' => ['design' => ['color' => ['background' => 'var:color.surface'], 'border' => ['radius' => 'var:radius.base']]],
    ]]);

    expect($html)->toContain('background-color:var(--verso-color-surface)')->toContain('border-radius:var(--verso-radius-base)');
});

it('offers the safe self-hosted fonts', function () {
    expect(array_column($this->styles->fonts(), 'value'))->toContain('newsreader', 'instrument-sans', 'system')
        ->and($this->styles->fonts()[0])->toHaveKeys(['value', 'label', 'stack']);
});

it('saves sizes in any CSS unit and refuses unsafe ones', function () {
    $rejected = $this->styles->save($this->theme, [
        'radius.base' => '0.75REM',
        'spacing.large' => 'clamp(2rem,1rem + 3vw,4rem)',
        'spacing.x-large' => '-2rem',
        'spacing.medium' => 'calc(1rem); background: url(x)',
        'width.wide' => 'min(92vw, 1200px)',
        'width.content' => '68ch',
    ]);

    expect($rejected)->toBe(['spacing.x-large', 'spacing.medium'])
        ->and(app(Settings::class)->get('theme.styles.ashgrove'))->toMatchArray([
            'radius.base' => '0.75rem',
            'spacing.large' => 'clamp(2rem, 1rem + 3vw, 4rem)',
            'width.content' => '68ch',
        ])
        ->and(app(ThemeCustomizer::class)->values($this->theme)['layout_width'])->toBe('min(92vw, 1200px)')
        ->and($this->styles->values($this->theme))->toMatchArray(['width.wide' => 'min(92vw, 1200px)', 'spacing.large' => 'clamp(2rem, 1rem + 3vw, 4rem)']);
});

it('keeps a linked size in the unit of the customizer field as a whole number', function () {
    $this->styles->save($this->theme, ['width.wide' => '80rem']);

    expect(app(Settings::class)->get('theme.ashgrove.layout_width'))->toBe(80)
        ->and($this->styles->values($this->theme)['width.wide'])->toBe('80rem');
});
