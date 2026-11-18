<?php

use App\Verso\Core\Settings\Settings;
use App\Verso\Easy\Styles\FontPair;
use App\Verso\Easy\Styles\FontPairRegistry;
use App\Verso\Easy\Styles\ThemeStyleOptions;
use App\Verso\Theme\Theme;
use App\Verso\Theme\ThemeManager;

beforeEach(function () {
    app(Settings::class)->set('theme.active', 'ashgrove');
    $this->themes = app(ThemeManager::class);
    $this->styleOptions = app(ThemeStyleOptions::class);
});

// A theme with only a few tokens and its own Easy mode extras.
function harborTheme(array $easy = []): Theme
{
    return Theme::fromManifest(base_path('themes/harbor-test'), [
        'name' => 'Harbor',
        'settings' => [
            'heading_font' => ['type' => 'font', 'default' => 'georgia', 'options' => ['georgia', 'system'], 'css' => '--font-heading'],
            'logo' => ['type' => 'media'],
        ],
        'styles' => [
            'color' => ['accent' => ['default' => '#1f4e79'], 'background' => ['default' => '#ffffff'], 'text' => ['default' => '#111827']],
            'font' => ['heading' => ['setting' => 'heading_font'], 'body' => ['default' => 'charter']],
            'radius' => ['base' => ['default' => '4px']],
        ],
        'easy' => $easy,
    ]);
}

it('keeps every preset token the active theme declares', function () {
    $presets = $this->styleOptions->presets($this->themes->find('ashgrove'));

    expect(array_keys($presets))->toBe(['calm', 'bold', 'playful', 'elegant'])
        ->and($presets['bold']->tokens)->toBe([
            'radius.base' => '0px',
            'spacing.small' => '0.5rem',
            'spacing.medium' => '1rem',
            'spacing.large' => '2rem',
            'spacing.x-large' => '3.5rem',
        ]);
});

it('drops preset tokens a theme does not declare', function () {
    $presets = $this->styleOptions->presets(harborTheme());

    expect($presets['playful']->tokens)->toBe(['radius.base' => '16px']);
});

it('adds and changes presets and font pairs from theme.json', function () {
    $theme = harborTheme([
        'presets' => [
            'calm' => ['tokens' => ['radius.base' => '5px']],
            'harbor-night' => ['label' => 'Harbor night', 'description' => 'Deep blue and square.', 'tokens' => ['radius.base' => '0px', 'width.wide' => '90rem'], 'order' => 1],
        ],
        'fontPairs' => ['captain' => ['label' => 'Captain', 'heading' => 'system', 'body' => 'georgia']],
    ]);
    $presets = $this->styleOptions->presets($theme);

    expect(array_key_first($presets))->toBe('harbor-night')
        ->and($presets['harbor-night']->tokens)->toBe(['radius.base' => '0px'])
        ->and($presets['calm']->tokens)->toBe(['radius.base' => '5px'])
        ->and($this->styleOptions->fontPairs($theme))->toHaveKey('captain');
});

it('offers only font pairs whose fonts the theme accepts', function () {
    $pairs = $this->styleOptions->fontPairs(harborTheme());

    expect(array_keys($pairs))->toBe(['classic', 'modern', 'modern-serif'])
        ->and($this->styleOptions->fontPairTokens(harborTheme(), $pairs['classic']))->toBe(['font.heading' => 'georgia', 'font.body' => 'charter']);
});

it('uses the menu font for headings when the theme has no heading font', function () {
    $default = $this->themes->find('default');
    $pairs = $this->styleOptions->fontPairs($default);

    expect($pairs)->toHaveKey('friendly')
        ->not->toHaveKey('editorial')
        ->and($this->styleOptions->fontPairTokens($default, $pairs['friendly']))->toBe(['font.ui' => 'humanist', 'font.body' => 'humanist']);
});

it('skips a font pair when a font is outside the theme options', function () {
    $pair = new FontPair('mono-news', 'Typewriter', heading: 'mono', body: 'charter');

    expect($this->styleOptions->fontPairTokens(harborTheme(), $pair))->toBe([]);
});

it('lists the color roles a theme declares', function () {
    expect($this->styleOptions->colorRoles($this->themes->find('ashgrove')))->toHaveKeys(['accent', 'background', 'text', 'muted', 'surface'])
        ->and($this->styleOptions->colorRoles(harborTheme()))->toBe([
            'accent' => ['token' => 'color.accent', 'label' => 'Accent'],
            'background' => ['token' => 'color.background', 'label' => 'Background'],
            'text' => ['token' => 'color.text', 'label' => 'Text'],
        ]);
});

it('gives the theme font files for real font samples', function () {
    $faces = $this->styleOptions->fontFaces($this->themes->find('ashgrove'));

    expect(array_column($faces, 'family', 'font'))->toBe(['newsreader' => 'Newsreader', 'instrument-sans' => 'Instrument Sans'])
        ->and($faces[0]['url'])->toContain('fonts/newsreader-roman.woff2');
});

it('lets plugins add font pairs that show on fitting themes', function () {
    app(FontPairRegistry::class)->register(new FontPair('harbor-print', 'Harbor print', heading: 'system', body: 'georgia', order: 1));

    expect(array_key_first($this->styleOptions->fontPairs(harborTheme())))->toBe('harbor-print');
});
